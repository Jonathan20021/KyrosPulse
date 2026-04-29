<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Services\WasapiService;

final class WebhookController extends Controller
{
    /**
     * Webhook publico de Wasapi.
     * URL: POST /webhooks/wasapi/{tenant_uuid}
     */
    public function wasapi(Request $request, array $params): void
    {
        $tenantUuid = (string) ($params['tenant_uuid'] ?? '');

        $tenant = Database::fetch("SELECT id FROM tenants WHERE uuid = :u AND deleted_at IS NULL", ['u' => $tenantUuid]);
        if (!$tenant) {
            $this->json(['error' => 'Tenant no encontrado.'], 404);
            return;
        }

        $tenantId = (int) $tenant['id'];

        // Log raw payload
        $rawBody = $request->rawBody();
        $signature = (string) $request->header('x-wasapi-signature', '');

        try {
            Database::insert('whatsapp_logs', [
                'tenant_id'    => $tenantId,
                'direction'    => 'webhook',
                'endpoint'     => '/webhooks/wasapi',
                'request_body' => mb_substr($rawBody, 0, 8000),
                'status_code'  => 200,
                'success'      => 1,
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo guardar log de webhook', ['msg' => $e->getMessage()]);
        }

        $service = new WasapiService($tenantId);

        // Validar firma si hay secret configurado
        if (!$service->validateWebhook($signature, $rawBody)) {
            Logger::warning('Webhook Wasapi con firma invalida', ['tenant' => $tenantId]);
            $this->json(['error' => 'Firma invalida.'], 401);
            return;
        }

        $payload = $request->input();
        $result  = $service->processWebhook($payload);

        $this->json($result);
    }
}
