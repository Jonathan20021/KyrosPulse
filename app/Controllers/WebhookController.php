<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Integration;
use App\Models\WhatsappChannel;
use App\Services\AiAgentService;
use App\Services\WasapiService;
use App\Services\WhatsappCloudService;

final class WebhookController extends Controller
{
    /**
     * Webhook Wasapi (legacy).
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

        $rawBody = $request->rawBody();
        $signature = (string) $request->header('x-wasapi-signature', '');
        $logId = $this->logIncomingWebhook($tenantId, '/webhooks/wasapi', $rawBody, 'wasapi', null);

        // Resolver canal por wa_id (numero destino) si esta disponible
        $payload = $request->input();
        $channel = $this->resolveWasapiChannel($tenantId, $payload);

        $service = new WasapiService($tenantId, $channel);

        if (!$service->validateWebhook($signature, $rawBody)) {
            Logger::warning('Webhook Wasapi con firma invalida', ['tenant' => $tenantId]);
            $this->updateWebhookLog($logId, ['status_code' => 401, 'success' => 0, 'error_message' => 'Firma invalida.']);
            $this->json(['error' => 'Firma invalida.'], 401);
            return;
        }

        $result = $service->processWebhook($payload);
        $this->updateWebhookLog($logId, [
            'channel_id'    => $channel['id'] ?? null,
            'response_body' => mb_substr(json_encode($result, JSON_UNESCAPED_UNICODE) ?: '', 0, 4000),
            'status_code'   => 200,
            'success'       => !empty($result['success']) ? 1 : 0,
            'error_message' => !empty($result['success']) ? null : ($result['error'] ?? 'Error procesando webhook.'),
        ]);

        // Responder a Wasapi de inmediato y luego procesar las IA en background
        $this->json($result);
        $this->releaseConnection();
        $this->flushAiQueue($tenantId, $service->pendingAiCalls());
    }

    /**
     * Webhook Cloud API (Meta) por canal.
     * URL: GET/POST /webhooks/cloud/{channel_uuid}
     *
     * GET: handshake hub.* de Meta → debe responder hub.challenge.
     * POST: eventos.
     */
    public function cloudApi(Request $request, array $params): void
    {
        $uuid = (string) ($params['channel_uuid'] ?? '');
        $channel = WhatsappChannel::findByUuid($uuid);
        if (!$channel) {
            $this->json(['error' => 'Canal no encontrado.'], 404);
            return;
        }
        if ($channel['provider'] !== 'cloud') {
            $this->json(['error' => 'Este canal no es Cloud API.'], 400);
            return;
        }

        // GET = verificacion
        if ($request->method() === 'GET') {
            $mode      = (string) $request->query('hub_mode', '');
            $verify    = (string) $request->query('hub_verify_token', '');
            $challenge = (string) $request->query('hub_challenge', '');

            $resp = WhatsappCloudService::verifyWebhook($channel, $mode, $verify, $challenge);
            if ($resp !== null) {
                http_response_code(200);
                header('Content-Type: text/plain');
                echo $resp;
                return;
            }
            http_response_code(403);
            echo 'forbidden';
            return;
        }

        $tenantId = (int) $channel['tenant_id'];
        $rawBody  = $request->rawBody();
        $logId    = $this->logIncomingWebhook($tenantId, '/webhooks/cloud', $rawBody, 'cloud', (int) $channel['id']);

        // Validacion firma X-Hub-Signature-256 (opcional)
        $sig = (string) $request->header('x-hub-signature-256', '');
        $secret = (string) ($channel['webhook_secret'] ?? '');
        if ($sig !== '' && $secret !== '') {
            $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
            if (!hash_equals($expected, $sig)) {
                $this->updateWebhookLog($logId, ['status_code' => 401, 'success' => 0, 'error_message' => 'Firma invalida.']);
                $this->json(['error' => 'Firma invalida.'], 401);
                return;
            }
        }

        $payload = $request->input();
        $svc = new WhatsappCloudService($tenantId, $channel);
        $result = $svc->processWebhook($payload);

        $this->updateWebhookLog($logId, [
            'response_body' => mb_substr(json_encode($result, JSON_UNESCAPED_UNICODE) ?: '', 0, 4000),
            'status_code'   => 200,
            'success'       => !empty($result['success']) ? 1 : 0,
            'error_message' => !empty($result['success']) ? null : ($result['error'] ?? 'Error procesando.'),
        ]);
        $this->json($result);
        $this->releaseConnection();
        $this->flushAiQueue($tenantId, $svc->pendingAiCalls());
    }

    /**
     * Webhook generico para integraciones (Stripe, MercadoPago, Telegram, etc).
     * URL: POST /webhooks/integration/{slug}/{tenant_uuid}
     */
    public function integration(Request $request, array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $uuid = (string) ($params['tenant_uuid'] ?? '');

        $tenant = Database::fetch("SELECT id FROM tenants WHERE uuid = :u AND deleted_at IS NULL", ['u' => $uuid]);
        if (!$tenant) {
            $this->json(['error' => 'Tenant no encontrado.'], 404);
            return;
        }
        $tenantId = (int) $tenant['id'];

        $entry = Integration::findCatalog($slug);
        if (!$entry) {
            $this->json(['error' => 'Integracion desconocida.'], 404);
            return;
        }

        $rawBody = $request->rawBody();
        Integration::logEvent($tenantId, $slug, 'webhook.received', [
            'direction' => 'webhook',
            'request'   => $rawBody,
            'success'   => 1,
        ]);

        // Aqui se podrian rutar eventos especificos (stripe.checkout.session.completed, etc).
        // Por ahora respondemos OK y dejamos el log para procesamiento async.
        $this->json(['success' => true, 'received' => true]);
    }

    /**
     * Intenta resolver el canal Wasapi correcto a partir del payload.
     */
    private function resolveWasapiChannel(int $tenantId, array $payload): ?array
    {
        $data = $payload['data'] ?? $payload;
        $fromId   = $data['from_id'] ?? $data['number_id'] ?? null;
        $fromPhone = $data['to'] ?? $data['phone_number'] ?? null;

        if ($fromId) {
            $row = Database::fetch(
                "SELECT * FROM whatsapp_channels WHERE tenant_id = :t AND provider = 'wasapi' AND from_id = :f LIMIT 1",
                ['t' => $tenantId, 'f' => (string) $fromId]
            );
            if ($row) return $row;
        }
        if ($fromPhone) {
            $digits = preg_replace('/\D+/', '', (string) $fromPhone);
            $row = Database::fetch(
                "SELECT * FROM whatsapp_channels
                 WHERE tenant_id = :t AND provider = 'wasapi'
                   AND REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = :p
                 LIMIT 1",
                ['t' => $tenantId, 'p' => $digits]
            );
            if ($row) return $row;
        }

        // Fallback: canal default Wasapi
        $row = Database::fetch(
            "SELECT * FROM whatsapp_channels
             WHERE tenant_id = :t AND provider = 'wasapi' AND status = 'active' AND deleted_at IS NULL
             ORDER BY is_default DESC, id ASC LIMIT 1",
            ['t' => $tenantId]
        );
        return $row ?: null;
    }

    private function logIncomingWebhook(int $tenantId, string $endpoint, string $rawBody, string $provider, ?int $channelId): ?int
    {
        try {
            return Database::insert('whatsapp_logs', [
                'tenant_id'    => $tenantId,
                'channel_id'   => $channelId,
                'provider'     => $provider,
                'direction'    => 'webhook',
                'endpoint'     => $endpoint,
                'request_body' => mb_substr($rawBody, 0, 8000),
                'status_code'  => 200,
                'success'      => 0,
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo guardar log de webhook', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    private function updateWebhookLog(?int $logId, array $data): void
    {
        if (!$logId) return;
        try {
            Database::update('whatsapp_logs', $data, ['id' => $logId]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo actualizar log de webhook', ['msg' => $e->getMessage()]);
        }
    }

    /**
     * Cierra la conexion HTTP con el cliente (Wasapi/Meta) sin terminar el script.
     * Permite que la respuesta llegue al webhook en <100ms y que la IA se procese
     * en background sin que el proveedor reintente por timeout.
     */
    private function releaseConnection(): void
    {
        @ignore_user_abort(true);
        @set_time_limit(120);

        // PHP-FPM: cierra la conexion limpiamente
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
            return;
        }

        // mod_php fallback: forzar flush + cerrar
        if (!headers_sent()) {
            @header('Connection: close');
            @header('Content-Encoding: none');
            @header('Content-Length: ' . ob_get_length());
        }
        while (ob_get_level() > 0) @ob_end_flush();
        @flush();
    }

    /**
     * Procesa las auto-respuestas IA encoladas. Cualquier error queda como
     * nota interna en la conversacion para visibilidad del operador.
     */
    private function flushAiQueue(int $tenantId, array $queue): void
    {
        if (empty($queue)) return;

        $service = new AiAgentService($tenantId);
        foreach ($queue as $call) {
            try {
                $service->autoReplyToConversation(
                    (int) ($call['conversation_id'] ?? 0),
                    (int) ($call['contact_id'] ?? 0),
                    (string) ($call['phone'] ?? ''),
                    (string) ($call['text'] ?? ''),
                    isset($call['inbound_id']) ? (int) $call['inbound_id'] : null
                );
            } catch (\Throwable $e) {
                Logger::error('AI auto-reply fallo en webhook async', [
                    'tenant'        => $tenantId,
                    'conversation'  => $call['conversation_id'] ?? null,
                    'msg'           => $e->getMessage(),
                ]);
                $this->writeSystemNote(
                    $tenantId,
                    (int) ($call['conversation_id'] ?? 0),
                    (int) ($call['contact_id'] ?? 0),
                    'IA fallo: ' . mb_substr($e->getMessage(), 0, 200)
                );
            }
        }
    }

    private function writeSystemNote(int $tenantId, int $convId, int $contactId, string $text): void
    {
        if ($convId <= 0) return;
        try {
            Database::insert('messages', [
                'tenant_id'       => $tenantId,
                'conversation_id' => $convId,
                'contact_id'      => $contactId,
                'direction'       => 'outbound',
                'type'            => 'system',
                'content'         => $text,
                'is_internal'     => 1,
                'is_ai_generated' => 1,
                'status'          => 'sent',
                'sent_at'         => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {}
    }
}
