<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Models\NotificationDestination;

/**
 * Sistema de alertas inteligentes.
 *
 * Dos modos de disparo:
 *   1. INLINE: codigo de la app llama AlertService::fire(slug, tenantId, context)
 *      cuando detecta una condicion (ej. ApiQuotaService::consume al cruzar 80%).
 *   2. CRON:   AlertService::evaluateAll() corre cada 10 min y revisa reglas
 *      pasivas (webhook.dead, agent.error_rate, workflow.failed).
 *
 * Cooldown anti-spam: alert_rules.cooldown_minutes desde last_triggered_at.
 * Si no hay destinations configurados para el evento "alert.*", solo se
 * registra en alert_history y NotificationDispatcher devuelve silencio.
 *
 * Reglas builtin (tenant_id NULL en alert_rules) sirven como "templates":
 * la primera vez que un tenant tendria una alerta, se clona en alert_rules
 * con tenant_id seteado para que pueda personalizar el cooldown / disable.
 */
final class AlertService
{
    /** Evento bajo el cual NotificationDispatcher despacha las alertas. */
    private const NOTIF_EVENT = 'alert.fired';

    /**
     * Dispara una alerta de forma inline. Llama desde codigo cuando detectes
     * la condicion (cuota crossed, security event critical, etc).
     */
    public static function fire(string $slug, int $tenantId, array $context = []): bool
    {
        try {
            $rule = self::resolveRuleForTenant($tenantId, $slug);
            if (!$rule || empty($rule['is_active'])) return false;

            // Cooldown
            if (self::inCooldown($rule)) {
                Logger::debug('Alert in cooldown', ['slug' => $slug, 'tenant' => $tenantId]);
                return false;
            }

            [$title, $body, $fields] = self::renderForRule($rule, $tenantId, $context);

            return self::deliverAndRecord($rule, $tenantId, $title, $body, $fields, $context);
        } catch (\Throwable $e) {
            Logger::warning('AlertService::fire fallo', ['slug' => $slug, 'msg' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Cron: evalua TODAS las reglas pasivas (las que dependen de queries periodicas).
     * Las inline (api.quota.threshold, security.critical) NO se evaluan aqui
     * porque ya disparan desde el codigo en tiempo real.
     */
    public static function evaluateAll(int $maxTenantsPerRun = 100): array
    {
        $stats = ['evaluated' => 0, 'fired' => 0, 'tenants' => 0];

        // Tenants activos (procesamos solo los que tienen actividad reciente para ahorrar)
        $tenants = Database::fetchAll(
            "SELECT DISTINCT t.id
             FROM `tenants` t
             WHERE t.deleted_at IS NULL AND t.status IN ('active','trial')
             LIMIT $maxTenantsPerRun"
        );

        foreach ($tenants as $row) {
            $tenantId = (int) $row['id'];
            $stats['tenants']++;

            // Por cada rule_type pasivo, evaluar para este tenant
            foreach (['webhook.dead.count', 'agent.error_rate', 'workflow.failed'] as $type) {
                $rule = self::resolveRuleByType($tenantId, $type);
                if (!$rule || empty($rule['is_active'])) continue;
                if (self::inCooldown($rule)) continue;

                $stats['evaluated']++;
                $ctx = self::evaluatePassiveRule($rule, $tenantId);
                if ($ctx === null) continue; // no triggered

                [$title, $body, $fields] = self::renderForRule($rule, $tenantId, $ctx);
                if (self::deliverAndRecord($rule, $tenantId, $title, $body, $fields, $ctx)) {
                    $stats['fired']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Lista reglas con flag `_effective_id` (clone del tenant si existe, sino builtin).
     * Usado por la UI admin para mostrar reglas con sus configs aplicables.
     */
    public static function listForTenant(int $tenantId): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM `alert_rules`
             WHERE (`tenant_id` IS NULL OR `tenant_id` = :t)
             ORDER BY `tenant_id` IS NULL DESC, `slug` ASC",
            ['t' => $tenantId]
        );
        // Reducir a uno por slug: prefiere tenant override sobre builtin
        $bySlug = [];
        foreach ($rows as $r) {
            $slug = (string) $r['slug'];
            // Tenant rule sobreescribe builtin
            if (!isset($bySlug[$slug]) || !empty($r['tenant_id'])) {
                $bySlug[$slug] = $r;
            }
        }
        return array_values($bySlug);
    }

    public static function historyForTenant(int $tenantId, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT * FROM `alert_history`
             WHERE tenant_id = :t
             ORDER BY id DESC LIMIT $limit",
            ['t' => $tenantId]
        );
    }

    /**
     * Toggle de una regla. Si tenant_id es NULL (builtin), clona como propia
     * antes de modificar. Asi cada tenant puede customizar sin afectar el global.
     */
    public static function toggle(int $tenantId, string $slug, bool $active): void
    {
        $existing = Database::fetch(
            "SELECT * FROM `alert_rules` WHERE `tenant_id` = :t AND `slug` = :s LIMIT 1",
            ['t' => $tenantId, 's' => $slug]
        );
        if ($existing) {
            Database::update('alert_rules', ['is_active' => $active ? 1 : 0], ['id' => (int) $existing['id']]);
            return;
        }
        // Clonar desde builtin
        $builtin = Database::fetch(
            "SELECT * FROM `alert_rules` WHERE `tenant_id` IS NULL AND `slug` = :s LIMIT 1",
            ['s' => $slug]
        );
        if (!$builtin) return;
        Database::insert('alert_rules', [
            'tenant_id'        => $tenantId,
            'slug'             => $builtin['slug'],
            'name'             => $builtin['name'],
            'description'      => $builtin['description'],
            'rule_type'        => $builtin['rule_type'],
            'config'           => $builtin['config'],
            'severity'         => $builtin['severity'],
            'cooldown_minutes' => $builtin['cooldown_minutes'],
            'is_active'        => $active ? 1 : 0,
        ]);
    }

    // ============================================================
    // Internal: resolucion de reglas
    // ============================================================

    /** Tenant override -> builtin -> null. */
    private static function resolveRuleForTenant(int $tenantId, string $slug): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `alert_rules` WHERE `tenant_id` = :t AND `slug` = :s LIMIT 1",
            ['t' => $tenantId, 's' => $slug]
        );
        if ($row) return $row;
        $row = Database::fetch(
            "SELECT * FROM `alert_rules` WHERE `tenant_id` IS NULL AND `slug` = :s LIMIT 1",
            ['s' => $slug]
        );
        return $row ?: null;
    }

    private static function resolveRuleByType(int $tenantId, string $type): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `alert_rules`
             WHERE (`tenant_id` = :t OR `tenant_id` IS NULL)
               AND `rule_type` = :rt
             ORDER BY `tenant_id` IS NULL ASC
             LIMIT 1",
            ['t' => $tenantId, 'rt' => $type]
        );
        return $row ?: null;
    }

    private static function inCooldown(array $rule): bool
    {
        if (empty($rule['last_triggered_at'])) return false;
        $cooldown = (int) ($rule['cooldown_minutes'] ?? 60);
        $last = strtotime((string) $rule['last_triggered_at']);
        return $last && (time() - $last) < ($cooldown * 60);
    }

    // ============================================================
    // Internal: evaluacion de reglas pasivas (cron)
    // ============================================================

    /**
     * Devuelve context si la regla matchea (-> dispara), null si no.
     */
    private static function evaluatePassiveRule(array $rule, int $tenantId): ?array
    {
        $type = (string) $rule['rule_type'];
        $cfg  = is_array($rule['config'] ?? null) ? $rule['config'] : (json_decode((string) ($rule['config'] ?? '[]'), true) ?: []);

        switch ($type) {
            case 'webhook.dead.count':
                $threshold = (int) ($cfg['threshold'] ?? 5);
                $window    = (int) ($cfg['window_hours'] ?? 24);
                $row = Database::fetch(
                    "SELECT e.name AS endpoint_name, e.url AS endpoint_url, COUNT(*) AS n
                     FROM `webhook_deliveries` d
                     INNER JOIN `webhook_endpoints` e ON e.id = d.endpoint_id
                     WHERE d.tenant_id = :t
                       AND d.status = 'dead'
                       AND d.created_at >= DATE_SUB(NOW(), INTERVAL :w HOUR)
                     GROUP BY d.endpoint_id, e.name, e.url
                     HAVING n >= :th
                     ORDER BY n DESC LIMIT 1",
                    ['t' => $tenantId, 'w' => $window, 'th' => $threshold]
                );
                if (!$row) return null;
                return [
                    'endpoint'      => (string) $row['endpoint_name'],
                    'url'           => (string) $row['endpoint_url'],
                    'dead_count'    => (int) $row['n'],
                    'window_hours'  => $window,
                ];

            case 'agent.error_rate':
                $pct      = (int) ($cfg['pct'] ?? 20);
                $minRuns  = (int) ($cfg['min_runs'] ?? 10);
                $row = Database::fetch(
                    "SELECT COUNT(*) AS total,
                            SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed
                     FROM `agent_runs`
                     WHERE tenant_id = :t AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                    ['t' => $tenantId]
                );
                $total  = (int) ($row['total']  ?? 0);
                $failed = (int) ($row['failed'] ?? 0);
                if ($total < $minRuns) return null;
                $errRate = (int) round(($failed / $total) * 100);
                if ($errRate < $pct) return null;
                return [
                    'error_rate' => $errRate,
                    'failed'     => $failed,
                    'total'      => $total,
                    'threshold'  => $pct,
                ];

            case 'workflow.failed':
                $window = (int) ($cfg['window_minutes'] ?? 60);
                $row = Database::fetch(
                    "SELECT wr.uuid, wr.status, wr.error, w.name AS wf_name
                     FROM `workflow_runs` wr
                     LEFT JOIN `workflows` w ON w.id = wr.workflow_id
                     WHERE wr.tenant_id = :t
                       AND wr.status = 'failed'
                       AND wr.finished_at >= DATE_SUB(NOW(), INTERVAL :w MINUTE)
                     ORDER BY wr.id DESC LIMIT 1",
                    ['t' => $tenantId, 'w' => $window]
                );
                if (!$row) return null;
                return [
                    'workflow' => (string) ($row['wf_name'] ?? '?'),
                    'run_uuid' => (string) $row['uuid'],
                    'error'    => mb_substr((string) ($row['error'] ?? '—'), 0, 200),
                ];
        }
        return null;
    }

    // ============================================================
    // Internal: render + delivery
    // ============================================================

    /** Devuelve [title, body, fields[]] para una regla + contexto. */
    private static function renderForRule(array $rule, int $tenantId, array $ctx): array
    {
        $tenant = Database::fetch("SELECT name FROM tenants WHERE id = :t", ['t' => $tenantId]);
        $brand  = (string) ($tenant['name'] ?? 'Tu cuenta');
        $slug   = (string) $rule['slug'];

        switch ($slug) {
            case 'api.quota.80':
                $pct = (int) ($ctx['pct'] ?? 80);
                return [
                    '⚠ API: ' . $pct . '% de cuota usada · ' . $brand,
                    sprintf("Tu cuenta uso %d%% (%s de %s requests) de la cuota mensual de API.\nPlan: %s. Renueva el %s.",
                        $pct, number_format((int) ($ctx['used'] ?? 0)), number_format((int) ($ctx['quota'] ?? 0)),
                        (string) ($ctx['plan'] ?? '—'), (string) ($ctx['resets_at'] ?? '—')
                    ),
                    [
                        ['label' => 'Usado',     'value' => number_format((int) ($ctx['used'] ?? 0))],
                        ['label' => 'Cuota',     'value' => number_format((int) ($ctx['quota'] ?? 0))],
                        ['label' => 'Plan',      'value' => (string) ($ctx['plan'] ?? '—')],
                        ['label' => 'Renueva',   'value' => (string) ($ctx['resets_at'] ?? '—')],
                    ],
                ];
            case 'api.quota.100':
                return [
                    '🚨 API: cuota agotada · ' . $brand,
                    "Agotaste el 100% de tu cuota mensual de API. Las requests siguientes se rechazan con HTTP 429 hasta el reset.\n\nPara desbloquear: upgrade tu plan o ajusta la cuota desde admin.",
                    [
                        ['label' => 'Cuota', 'value' => number_format((int) ($ctx['quota'] ?? 0))],
                        ['label' => 'Plan',  'value' => (string) ($ctx['plan'] ?? '—')],
                    ],
                ];
            case 'webhook.dead':
                return [
                    '⚠ Webhook con entregas muertas · ' . $brand,
                    sprintf("El endpoint \"%s\" tiene %d entregas marcadas como dead en las ultimas %dh.\nURL: %s\n\nReintenta manualmente o revisa la URL/secret.",
                        (string) ($ctx['endpoint'] ?? '?'),
                        (int) ($ctx['dead_count'] ?? 0),
                        (int) ($ctx['window_hours'] ?? 24),
                        (string) ($ctx['url'] ?? '?')
                    ),
                    [
                        ['label' => 'Endpoint',    'value' => (string) ($ctx['endpoint'] ?? '?')],
                        ['label' => 'Dead 24h',    'value' => (string) ($ctx['dead_count'] ?? 0)],
                        ['label' => 'URL',         'value' => (string) ($ctx['url'] ?? '?')],
                    ],
                ];
            case 'agent.error_rate':
                return [
                    '⚠ Agentes IA: tasa de error alta · ' . $brand,
                    sprintf("Los agentes IA tienen %d%% de fallos en las ultimas 24h (%d de %d runs).\nUmbral configurado: %d%%.\nRevisa logs en Configuracion > IA.",
                        (int) ($ctx['error_rate'] ?? 0),
                        (int) ($ctx['failed'] ?? 0),
                        (int) ($ctx['total'] ?? 0),
                        (int) ($ctx['threshold'] ?? 20)
                    ),
                    [
                        ['label' => 'Error rate', 'value' => ($ctx['error_rate'] ?? 0) . '%'],
                        ['label' => 'Failed/Total', 'value' => ($ctx['failed'] ?? 0) . ' / ' . ($ctx['total'] ?? 0)],
                    ],
                ];
            case 'security.critical':
                return [
                    '🚨 Evento de seguridad critico · ' . $brand,
                    sprintf("Se detecto un evento de seguridad critico: %s.\nIP: %s.\nRevisa el log en Configuracion > Seguridad.",
                        (string) ($ctx['event'] ?? '?'),
                        (string) ($ctx['ip'] ?? '?')
                    ),
                    [
                        ['label' => 'Evento', 'value' => (string) ($ctx['event'] ?? '?')],
                        ['label' => 'IP',     'value' => (string) ($ctx['ip']    ?? '?')],
                    ],
                ];
            case 'workflow.failed':
                return [
                    '⚠ Workflow fallo · ' . $brand,
                    sprintf("El workflow \"%s\" termino con status=failed.\nRun: %s\nError: %s",
                        (string) ($ctx['workflow'] ?? '?'),
                        (string) ($ctx['run_uuid'] ?? '?'),
                        (string) ($ctx['error'] ?? '—')
                    ),
                    [
                        ['label' => 'Workflow', 'value' => (string) ($ctx['workflow'] ?? '?')],
                        ['label' => 'Run',      'value' => (string) ($ctx['run_uuid'] ?? '?')],
                    ],
                ];
        }
        return [
            ($rule['name'] ?? 'Alerta') . ' · ' . $brand,
            (string) ($rule['description'] ?? ''),
            [],
        ];
    }

    /**
     * Envia la alerta a notification_destinations + registra en alert_history
     * + actualiza last_triggered_at de la regla.
     */
    private static function deliverAndRecord(array $rule, int $tenantId, string $title, string $body, array $fields, array $context): bool
    {
        $destinations = NotificationDestination::activeForEvent($tenantId, self::NOTIF_EVENT);

        // Construir HTML basico (NotificationDispatcher::sendToDestination espera html para email)
        $html = '<h3 style="margin:0 0 10px">' . htmlspecialchars($title, ENT_QUOTES) . '</h3>'
              . '<p style="margin:0 0 10px;white-space:pre-line">' . htmlspecialchars($body, ENT_QUOTES) . '</p>';
        if (!empty($fields)) {
            $html .= '<table style="border-collapse:collapse;font-size:13px"><tbody>';
            foreach ($fields as $f) {
                $html .= '<tr><td style="padding:4px 12px 4px 0;color:#64748b;text-transform:uppercase;font-size:11px">' . htmlspecialchars((string) $f['label'], ENT_QUOTES) . '</td>'
                       . '<td style="padding:4px 0;font-weight:600">' . htmlspecialchars((string) $f['value'], ENT_QUOTES) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        $payload = [
            'subject' => '[Kyros Pulse] ' . $title,
            'title'   => $title,
            'text'    => $body,
            'html'    => $html,
            'fields'  => $fields,
            'event'   => self::NOTIF_EVENT,
            'rule'    => (string) $rule['slug'],
            'severity'=> (string) ($rule['severity'] ?? 'warning'),
        ];

        $delivered = 0;
        if (!empty($destinations)) {
            $dispatcher = new NotificationDispatcher($tenantId);
            $sendMethod = self::resolveSendMethod($dispatcher);
            foreach ($destinations as $dest) {
                try {
                    $sendMethod($dest, self::NOTIF_EVENT, $payload, 'alert', (int) $rule['id']);
                    $delivered++;
                } catch (\Throwable $e) {
                    Logger::warning('AlertService delivery fallo', ['dest_id' => $dest['id'] ?? null, 'msg' => $e->getMessage()]);
                }
            }
        }

        // Registrar en alert_history SIEMPRE (aunque no haya destinations)
        try {
            Database::insert('alert_history', [
                'tenant_id'          => $tenantId,
                'rule_id'            => (int) $rule['id'],
                'rule_slug'          => (string) $rule['slug'],
                'severity'           => (string) ($rule['severity'] ?? 'warning'),
                'title'              => mb_substr($title, 0, 255),
                'body'               => $body,
                'metadata'           => json_encode($context, JSON_UNESCAPED_UNICODE),
                'destinations_count' => count($destinations),
                'delivered_count'    => $delivered,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('alert_history insert fallo', ['msg' => $e->getMessage()]);
        }

        // Update last_triggered + counter
        try {
            Database::run(
                "UPDATE `alert_rules`
                 SET `last_triggered_at` = NOW(), `trigger_count` = `trigger_count` + 1
                 WHERE `id` = :i",
                ['i' => (int) $rule['id']]
            );
        } catch (\Throwable) {}

        return true;
    }

    /**
     * NotificationDispatcher::sendToDestination es privado. Lo invocamos via
     * reflection para reusar la logica existente sin duplicar 200 lineas de
     * adaptadores email/slack/discord/teams/telegram/webhook/whatsapp.
     */
    private static function resolveSendMethod(NotificationDispatcher $dispatcher): callable
    {
        return function (array $dest, string $event, array $payload, string $entityType, int $entityId) use ($dispatcher) {
            $ref = new \ReflectionClass($dispatcher);
            if (!$ref->hasMethod('sendToDestination')) return;
            $m = $ref->getMethod('sendToDestination');
            $m->setAccessible(true);
            $m->invoke($dispatcher, $dest, $event, $payload, $entityType, $entityId);
        };
    }
}
