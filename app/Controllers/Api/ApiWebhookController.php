<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatcher;

/**
 * Endpoints de webhooks salientes.
 *
 *   GET    /api/v1/webhooks                       listar endpoints
 *   POST   /api/v1/webhooks                       crear (devuelve secret 1 sola vez)
 *   GET    /api/v1/webhooks/{id}                  detalle
 *   PATCH  /api/v1/webhooks/{id}                  editar (events, url, headers, is_active)
 *   DELETE /api/v1/webhooks/{id}                  eliminar
 *   GET    /api/v1/webhooks/{id}/deliveries       audit de entregas
 *   POST   /api/v1/webhooks/deliveries/{uuid}/replay  reintentar
 */
final class ApiWebhookController extends ApiController
{
    public function index(Request $request): void
    {
        $tenantId = $this->tenantId();
        $rows = WebhookEndpoint::listForTenant($tenantId);
        $this->ok(array_map(fn($r) => $this->transform($r), $rows));
    }

    public function show(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = WebhookEndpoint::findByIdForTenant($tenantId, $id);
        if (!$row) $this->error('not_found', 'Webhook endpoint not found.', 404);
        $this->ok($this->transform($row));
    }

    public function store(Request $request): void
    {
        $tenantId = $this->tenantId();
        $clean = $this->validateApi($request, [
            'name' => 'required|string|min:1|max:120',
            'url'  => 'required|url|max:500',
        ]);

        $events = $request->input('events', ['*']);
        if (!is_array($events) || empty($events)) $events = ['*'];
        $events = array_values(array_map('strval', $events));

        $description = (string) $request->input('description', '');
        $headers = $request->input('headers');
        if ($headers !== null && !is_array($headers)) {
            $this->error('validation_failed', '`headers` must be an object.', 422);
        }

        $secret = WebhookDispatcher::generateSecret();
        $id = WebhookEndpoint::create([
            'tenant_id'   => $tenantId,
            'name'        => mb_substr((string) $clean['name'], 0, 120),
            'url'         => (string) $clean['url'],
            'secret'      => $secret,
            'events'      => json_encode($events, JSON_UNESCAPED_SLASHES),
            'is_active'   => 1,
            'description' => $description !== '' ? mb_substr($description, 0, 500) : null,
            'headers'     => $headers ? json_encode($headers, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $row = WebhookEndpoint::findByIdForTenant($tenantId, $id);
        $payload = $this->transform($row ?? []);
        $payload['secret'] = $secret; // SOLO esta vez
        $this->created($payload);
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = WebhookEndpoint::findByIdForTenant($tenantId, $id);
        if (!$row) $this->error('not_found', 'Webhook endpoint not found.', 404);

        $patch = [];
        if (($v = $request->input('name')) !== null)        $patch['name'] = mb_substr((string) $v, 0, 120);
        if (($v = $request->input('url')) !== null)         $patch['url']  = (string) $v;
        if (($v = $request->input('description')) !== null) $patch['description'] = $v === '' ? null : mb_substr((string) $v, 0, 500);
        if (($v = $request->input('is_active')) !== null)   $patch['is_active'] = $v ? 1 : 0;

        if (($events = $request->input('events')) !== null) {
            if (!is_array($events) || empty($events)) {
                $this->error('validation_failed', '`events` must be a non-empty array.', 422);
            }
            $patch['events'] = json_encode(array_values(array_map('strval', $events)), JSON_UNESCAPED_SLASHES);
        }
        if (($headers = $request->input('headers')) !== null) {
            if (!is_array($headers) && $headers !== false) {
                $this->error('validation_failed', '`headers` must be an object.', 422);
            }
            $patch['headers'] = is_array($headers) && $headers ? json_encode($headers, JSON_UNESCAPED_UNICODE) : null;
        }

        if (!$patch) $this->error('validation_failed', 'No editable fields provided.', 422);

        WebhookEndpoint::update($tenantId, $id, $patch);
        $row = WebhookEndpoint::findByIdForTenant($tenantId, $id);
        $this->ok($this->transform($row ?? []));
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = WebhookEndpoint::findByIdForTenant($tenantId, $id);
        if (!$row) $this->error('not_found', 'Webhook endpoint not found.', 404);

        // Limpia tambien deliveries asociadas
        Database::run("DELETE FROM `webhook_deliveries` WHERE `endpoint_id` = :i", ['i' => $id]);
        WebhookEndpoint::delete($tenantId, $id);
        $this->noContent();
    }

    public function deliveries(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $row = WebhookEndpoint::findByIdForTenant($tenantId, $id);
        if (!$row) $this->error('not_found', 'Webhook endpoint not found.', 404);

        [$page, $perPage, $offset] = $this->pagination($request, 25, 100);
        $rows = WebhookEndpoint::deliveriesForEndpoint($tenantId, $id, $perPage);
        $items = array_map(fn($d) => $this->transformDelivery($d), $rows);
        $this->paginated($items, $page, $perPage);
    }

    public function replay(Request $request, array $params): void
    {
        $tenantId = $this->tenantId();
        $uuid = (string) ($params['uuid'] ?? '');
        $result = WebhookDispatcher::replay($tenantId, $uuid);
        if (!$result) $this->error('not_found', 'Delivery not found.', 404);
        $this->ok($result);
    }

    private function transform(array $r): array
    {
        $events = $r['events'] ? json_decode((string) $r['events'], true) : ['*'];
        $headers = $r['headers'] ? json_decode((string) $r['headers'], true) : null;
        return [
            'id'              => (int) ($r['id'] ?? 0),
            'name'            => (string) ($r['name'] ?? ''),
            'url'             => (string) ($r['url'] ?? ''),
            'events'          => is_array($events) ? $events : ['*'],
            'is_active'       => !empty($r['is_active']),
            'description'     => $r['description'] ?? null,
            'headers'         => is_array($headers) ? $headers : null,
            'last_delivery_at'=> $r['last_delivery_at'] ?? null,
            'last_status'     => isset($r['last_status']) ? (int) $r['last_status'] : null,
            'last_error'      => $r['last_error'] ?? null,
            'success_count'   => (int) ($r['success_count'] ?? 0),
            'failure_count'   => (int) ($r['failure_count'] ?? 0),
            'created_at'      => $r['created_at'] ?? null,
        ];
    }

    private function transformDelivery(array $d): array
    {
        return [
            'id'            => (string) ($d['uuid'] ?? ''),
            'event'         => (string) ($d['event'] ?? ''),
            'status'        => (string) ($d['status'] ?? ''),
            'attempts'      => (int) ($d['attempts'] ?? 0),
            'response_code' => isset($d['response_code']) ? (int) $d['response_code'] : null,
            'latency_ms'    => isset($d['latency_ms'])    ? (int) $d['latency_ms']    : null,
            'error'         => $d['error'] ?? null,
            'next_retry_at' => $d['next_retry_at'] ?? null,
            'created_at'    => $d['created_at'] ?? null,
            'delivered_at'  => $d['delivered_at'] ?? null,
        ];
    }
}
