<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Workflow;
use App\Models\WorkflowTemplate;

/**
 * Servicio de clonado de templates del marketplace.
 *
 * Toma una definicion (JSON: trigger + steps[]) y produce un workflow real
 * con sus steps en transaccion. Algunos templates usan agent_id: null como
 * placeholder — el servicio los rellena con el agente default del tenant
 * si existe (o deja null para que el usuario lo edite).
 */
final class WorkflowTemplateService
{
    /**
     * Clona el template y devuelve el workflow_id nuevo. Lanza si la definicion
     * esta mal formada.
     */
    public static function clone(int $tenantId, int $templateId, ?int $createdBy = null, ?string $nameOverride = null): int
    {
        $tpl = WorkflowTemplate::findById($tenantId, $templateId);
        if (!$tpl) {
            throw new \InvalidArgumentException('Template no encontrado.');
        }

        $def = json_decode((string) $tpl['definition'], true);
        if (!is_array($def) || empty($def['steps']) || !is_array($def['steps'])) {
            throw new \RuntimeException('Definicion del template invalida.');
        }

        $triggerType = (string) ($def['trigger_type'] ?? 'manual');
        if (!in_array($triggerType, ['event','schedule','webhook','manual'], true)) {
            $triggerType = 'manual';
        }

        $triggerConfig = is_array($def['trigger_config'] ?? null) ? $def['trigger_config'] : [];

        $webhookToken = $triggerType === 'webhook' ? Workflow::generateWebhookToken() : null;

        // Default agent id resolver (usado solo si el template tiene placeholders)
        $defaultAgentId = self::resolveDefaultAgentId($tenantId);

        return Database::transaction(function () use ($tpl, $def, $tenantId, $createdBy, $nameOverride, $triggerType, $triggerConfig, $webhookToken, $defaultAgentId) {
            // 1. Crear workflow
            $wfName = $nameOverride !== null && $nameOverride !== '' ? $nameOverride : (string) $tpl['name'];
            $wfId = Workflow::create([
                'tenant_id'      => $tenantId,
                'name'           => mb_substr($wfName, 0, 120),
                'description'    => mb_substr((string) ($tpl['description'] ?? ''), 0, 500) ?: null,
                'trigger_type'   => $triggerType,
                'trigger_config' => json_encode($triggerConfig, JSON_UNESCAPED_UNICODE),
                'webhook_token'  => $webhookToken,
                'is_active'      => 0, // Comienza desactivado: usuario revisa, edita placeholders y activa
                'created_by'     => $createdBy,
            ]);

            // 2. Crear steps preservando step_key
            $orderIndex = 10;
            foreach ($def['steps'] as $rawStep) {
                if (!is_array($rawStep)) continue;
                $stepKey = (string) ($rawStep['step_key'] ?? '');
                $type    = (string) ($rawStep['type'] ?? 'action');
                if ($stepKey === '' || !in_array($type, ['action','branch','delay','set_var','end'], true)) {
                    continue;
                }
                $config = is_array($rawStep['config'] ?? null) ? $rawStep['config'] : [];

                // Resolver placeholder de agent_id (null) en steps run_agent
                if ($type === 'action' && ($config['action'] ?? '') === 'run_agent') {
                    $params = is_array($config['params'] ?? null) ? $config['params'] : [];
                    if (!isset($params['agent_id']) || $params['agent_id'] === null) {
                        $params['agent_id'] = $defaultAgentId;
                    }
                    $config['params'] = $params;
                }

                Workflow::createStep([
                    'workflow_id'   => $wfId,
                    'tenant_id'     => $tenantId,
                    'step_key'      => mb_substr($stepKey, 0, 40),
                    'type'          => $type,
                    'order_index'   => $orderIndex,
                    'config'        => json_encode($config, JSON_UNESCAPED_UNICODE),
                    'next_step_key' => isset($rawStep['next_step_key']) && $rawStep['next_step_key'] !== '' ? mb_substr((string) $rawStep['next_step_key'], 0, 40) : null,
                    'branch_yes'    => isset($rawStep['branch_yes'])    && $rawStep['branch_yes']    !== '' ? mb_substr((string) $rawStep['branch_yes'],    0, 40) : null,
                    'branch_no'     => isset($rawStep['branch_no'])     && $rawStep['branch_no']     !== '' ? mb_substr((string) $rawStep['branch_no'],     0, 40) : null,
                ]);
                $orderIndex += 10;
            }

            // 3. Bump clone counter
            WorkflowTemplate::incrementCloneCount((int) $tpl['id']);

            try {
                \App\Core\Audit::log('workflow.clone_from_template', 'workflow', $wfId, [], [
                    'template_id'   => (int) $tpl['id'],
                    'template_slug' => (string) $tpl['slug'],
                ]);
            } catch (\Throwable $e) {
                Logger::warning('audit log clone fallo', ['msg' => $e->getMessage()]);
            }

            return $wfId;
        });
    }

    /** Resuelve el agent_id default del tenant para rellenar placeholders. */
    private static function resolveDefaultAgentId(int $tenantId): ?int
    {
        try {
            $row = Database::fetch(
                "SELECT `id` FROM `ai_agents`
                 WHERE `tenant_id` = :t AND `status` = 'active'
                 ORDER BY `is_default` DESC, `id` ASC
                 LIMIT 1",
                ['t' => $tenantId]
            );
            return $row ? (int) $row['id'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Devuelve true si el tenant cumple los requires del template.
     * Por ahora chequea features tipicas de la tabla tenants: is_restaurant, ai_enabled.
     */
    public static function tenantMeetsRequires(int $tenantId, ?array $requires): bool
    {
        if (!$requires || empty($requires)) return true;
        try {
            $row = Database::fetch(
                "SELECT `is_restaurant`, `ai_enabled` FROM `tenants` WHERE `id` = :t LIMIT 1",
                ['t' => $tenantId]
            );
            if (!$row) return false;
            foreach ($requires as $req) {
                $req = (string) $req;
                if ($req === 'is_restaurant' && empty($row['is_restaurant'])) return false;
                if ($req === 'ai_enabled' && empty($row['ai_enabled'])) return false;
            }
            return true;
        } catch (\Throwable) {
            return true; // Si no podemos chequear, no bloqueamos
        }
    }
}
