<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowTemplateService;

/**
 * Endpoints del marketplace de workflow templates.
 *
 *   GET    /api/v1/workflow-templates           listar (?category=...)
 *   GET    /api/v1/workflow-templates/{id}      detalle + definicion
 *   POST   /api/v1/workflow-templates/{id}/use  clonar (body: { name? })
 */
final class ApiWorkflowTemplateController extends ApiController
{
    public function index(Request $request): void
    {
        $tenantId = $this->tenantId();
        $category = (string) $request->query('category', '');
        $rows = WorkflowTemplate::listAvailable($tenantId, $category !== '' ? $category : null);
        $out = array_map(fn($t) => $this->transform($tenantId, $t), $rows);
        $this->ok($out);
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $tpl = WorkflowTemplate::findById($tenantId, $id);
        if (!$tpl) $this->error('not_found', 'Template not found.', 404);

        $payload = $this->transform($tenantId, $tpl);
        $payload['definition'] = json_decode((string) $tpl['definition'], true) ?: [];
        $this->ok($payload);
    }

    public function use(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $name = trim((string) $request->input('name', '')) ?: null;
        try {
            $wfId = WorkflowTemplateService::clone($tenantId, $id, $this->apiKey()['created_by'] ?? null, $name);
            $this->created([
                'workflow_id' => $wfId,
                'template_id' => $id,
                'note'        => 'Workflow creado en estado pausado. Activalo desde PATCH /workflows/{id}/toggle o en el admin UI.',
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->error('not_found', $e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            $this->error('bad_template', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            $this->error('internal_error', $e->getMessage(), 500);
        }
    }

    private function transform(int $tenantId, array $t): array
    {
        $req = $t['requires'] ? json_decode((string) $t['requires'], true) : null;
        $def = $t['definition'] ? json_decode((string) $t['definition'], true) : null;
        return [
            'id'           => (int) ($t['id'] ?? 0),
            'slug'         => (string) ($t['slug'] ?? ''),
            'name'         => (string) ($t['name'] ?? ''),
            'description'  => (string) ($t['description'] ?? ''),
            'category'     => (string) ($t['category'] ?? 'general'),
            'icon'         => (string) ($t['icon'] ?? '🪄'),
            'is_global'    => empty($t['tenant_id']),
            'requires'     => is_array($req) ? $req : [],
            'meets_requires' => WorkflowTemplateService::tenantMeetsRequires($tenantId, is_array($req) ? $req : null),
            'clone_count'  => (int) ($t['clone_count'] ?? 0),
            'steps_count'  => is_array($def) && isset($def['steps']) && is_array($def['steps']) ? count($def['steps']) : 0,
            'trigger_type' => is_array($def) ? (string) ($def['trigger_type'] ?? 'manual') : 'manual',
        ];
    }
}
