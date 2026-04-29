<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;

/**
 * Servicio de integracion con Wasapi (WhatsApp).
 * https://api-ws.wasapi.io
 *
 * Cada tenant guarda su propia API key. Si no, se usa la global del .env como fallback.
 */
final class WasapiService
{
    public function __construct(private int $tenantId) {}

    private ?int $resolvedFromId = null;

    /** Cache en memoria por request: wa_id => ['first_name' => ..., 'last_name' => ...] */
    private array $contactProfileCache = [];

    private function credentials(): array
    {
        $tenant  = Tenant::findById($this->tenantId);
        $apiKey  = trim((string) ($tenant['wasapi_api_key'] ?? ''));
        $phone   = (string) ($tenant['wasapi_phone'] ?? '');
        $baseUrl = $this->normalizeBaseUrl((string) config('services.wasapi.base_url'));

        if ($apiKey === '') {
            $apiKey = trim((string) config('services.wasapi.api_key'));
        }

        return ['api_key' => $apiKey, 'base_url' => rtrim($baseUrl, '/'), 'phone' => $phone];
    }

    private function authHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];
    }

    public function sendTextMessage(string $phone, string $message): array
    {
        $cred = $this->credentials();
        if ($cred['api_key'] === '') {
            return ['success' => false, 'error' => 'No hay API key Wasapi configurada para este tenant.'];
        }

        $waId = $this->normalizeWaId($phone);
        if ($waId === '') {
            return ['success' => false, 'error' => 'Numero WhatsApp invalido.'];
        }

        $from = $this->resolveFromId($cred);
        if (!$from['success']) {
            return $from;
        }

        $payload = [
            'wa_id'   => $waId,
            'message' => $message,
            'from_id' => $from['from_id'],
        ];

        $resp = HttpClient::post(
            $cred['base_url'] . '/whatsapp-messages',
            $payload,
            $this->authHeaders($cred['api_key']),
            (int) config('services.wasapi.timeout', 30)
        );
        $resp = $this->normalizeMessageResponse($resp);

        $this->logRequest('/whatsapp-messages', $payload, $resp);

        return $resp;
    }

    public function sendMediaMessage(string $phone, string $mediaUrl, string $caption = '', string $type = 'image'): array
    {
        $cred = $this->credentials();
        if ($cred['api_key'] === '') {
            return ['success' => false, 'error' => 'No hay API key Wasapi configurada.'];
        }

        $waId = $this->normalizeWaId($phone);
        if ($waId === '') {
            return ['success' => false, 'error' => 'Numero WhatsApp invalido.'];
        }

        $from = $this->resolveFromId($cred);
        if (!$from['success']) {
            return $from;
        }

        $fileKey = $this->mediaFileKey($type);
        $payload = [
            'from_id' => $from['from_id'],
            'wa_id'   => $waId,
            'file'    => $fileKey,
            $fileKey  => $mediaUrl,
        ];
        if ($caption !== '') {
            $payload['caption'] = $caption;
        }

        $resp = HttpClient::post(
            $cred['base_url'] . '/whatsapp-messages/attachment',
            $payload,
            $this->authHeaders($cred['api_key'])
        );
        $resp = $this->normalizeMessageResponse($resp);

        $this->logRequest('/whatsapp-messages/attachment', $payload, $resp);
        return $resp;
    }

    public function sendTemplate(string $phone, string $templateName, array $variables = []): array
    {
        $cred = $this->credentials();
        if ($cred['api_key'] === '') {
            return ['success' => false, 'error' => 'No hay API key Wasapi configurada.'];
        }

        $waId = $this->normalizeWaId($phone);
        if ($waId === '') {
            return ['success' => false, 'error' => 'Numero WhatsApp invalido.'];
        }

        $from = $this->resolveFromId($cred);
        if (!$from['success']) {
            return $from;
        }

        $payload = [
            'recipients'    => $waId,
            'template_id'   => $templateName,
            'contact_type'  => 'phone',
            'from_id'       => $from['from_id'],
            'conversation_status' => 'open',
            'chatbot_status' => 'enable',
        ];
        if (!empty($variables)) {
            $payload['body_vars'] = $this->normalizeTemplateVars($variables);
        }

        $resp = HttpClient::post(
            $cred['base_url'] . '/whatsapp-messages/send-template',
            $payload,
            $this->authHeaders($cred['api_key'])
        );
        $resp = $this->normalizeMessageResponse($resp);

        $this->logRequest('/whatsapp-messages/send-template', $payload, $resp);
        return $resp;
    }

    public function getTemplates(): array
    {
        $cred = $this->credentials();
        if ($cred['api_key'] === '') {
            return ['success' => false, 'data' => []];
        }
        $resp = HttpClient::get(
            $cred['base_url'] . '/whatsapp-templates',
            $this->authHeaders($cred['api_key'])
        );
        $this->logRequest('/whatsapp-templates', [], $resp);
        return $resp;
    }

    public function syncTemplates(): array
    {
        $resp = $this->getTemplates();
        if (empty($resp['success'])) {
            return ['success' => false, 'synced' => 0, 'error' => $resp['error'] ?? 'No se pudieron consultar plantillas.'];
        }

        $items = $this->extractDataList($resp['body'] ?? []);
        $synced = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $externalId = (string) ($item['uuid'] ?? $item['template_id'] ?? $item['id'] ?? '');
            if ($externalId === '') {
                continue;
            }

            $status = strtolower((string) ($item['status'] ?? 'draft'));
            if (!in_array($status, ['draft','pending','approved','rejected','disabled'], true)) {
                $status = $status === 'APPROVED' ? 'approved' : strtolower($status);
            }
            if (!in_array($status, ['draft','pending','approved','rejected','disabled'], true)) {
                $status = 'draft';
            }

            $existing = Database::fetch(
                "SELECT id FROM templates
                 WHERE tenant_id = :t AND (external_id = :ext OR name = :name)
                 LIMIT 1",
                ['t' => $this->tenantId, 'ext' => $externalId, 'name' => (string) ($item['template_id'] ?? $item['name'] ?? $externalId)]
            );

            $data = [
                'tenant_id'   => $this->tenantId,
                'name'        => (string) ($item['template_id'] ?? $item['name'] ?? $externalId),
                'category'    => (string) ($item['category'] ?? 'wasapi'),
                'language'    => (string) ($item['language'] ?? 'es'),
                'body'        => (string) ($item['body'] ?? ''),
                'variables'   => !empty($item['variables']) ? json_encode($item['variables'], JSON_UNESCAPED_UNICODE) : null,
                'external_id' => $externalId,
                'status'      => $status,
            ];

            if ($existing) {
                Database::update('templates', $data, ['id' => (int) $existing['id']]);
            } else {
                Database::insert('templates', $data);
            }
            $synced++;
        }

        return ['success' => true, 'synced' => $synced];
    }

    /**
     * Consulta el perfil del contacto en Wasapi para obtener su nombre real
     * de WhatsApp (el que el cliente puso en su perfil de WA).
     *
     * Retorna ['first_name' => '...', 'last_name' => '...', 'email' => '...']
     * o null si no se encuentra / falla la llamada.
     *
     * Resultado cacheado en memoria por request.
     */
    public function getContactProfile(string $phone): ?array
    {
        $waId = $this->normalizeWaId($phone);
        if ($waId === '') return null;
        if (isset($this->contactProfileCache[$waId])) return $this->contactProfileCache[$waId];

        $cred = $this->credentials();
        if ($cred['api_key'] === '') return $this->contactProfileCache[$waId] = null;

        $resp = HttpClient::get(
            $cred['base_url'] . '/contacts/' . $waId,
            $this->authHeaders($cred['api_key']),
            (int) config('services.wasapi.timeout', 15)
        );

        if (empty($resp['success'])) {
            return $this->contactProfileCache[$waId] = null;
        }

        $data = $resp['body']['data'] ?? null;
        if (!is_array($data)) return $this->contactProfileCache[$waId] = null;

        $first = trim((string) ($data['first_name'] ?? ''));
        $last  = trim((string) ($data['last_name']  ?? ''));
        if ($first === '' && $last === '') return $this->contactProfileCache[$waId] = null;

        return $this->contactProfileCache[$waId] = [
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => trim((string) ($data['email'] ?? '')),
        ];
    }

    public function getWhatsappNumbers(): array
    {
        $cred = $this->credentials();
        if ($cred['api_key'] === '') {
            return ['success' => false, 'data' => [], 'error' => 'No hay API key Wasapi configurada.'];
        }

        $resp = HttpClient::get(
            $cred['base_url'] . '/whatsapp-numbers',
            $this->authHeaders($cred['api_key']),
            (int) config('services.wasapi.timeout', 30)
        );
        $this->logRequest('/whatsapp-numbers', [], $resp);
        return $resp;
    }

    /**
     * Procesa un payload entrante del webhook de Wasapi.
     * Wasapi envia eventos como:
     *   { "event": "receive_message", "data": { "wa_id": "...", "message": "...", "wam_id": "..." } }
     *   { "event": "status_message", "data": { "type": "out", "status": "delivered", ... } }
     * Tambien mantenemos compatibilidad con el esquema flexible anterior.
     */
    public function processWebhook(array $payload): array
    {
        Logger::info('Wasapi webhook received', ['tenant' => $this->tenantId]);

        $event = $this->normalizeEventName((string) ($payload['event'] ?? $payload['type'] ?? ''));
        $data  = $this->eventData($payload);
        $wasapiDirection = strtolower((string) ($data['type'] ?? ''));

        if ($event === 'receive_message' || $wasapiDirection === 'in') {
            return $this->handleIncomingMessage($payload);
        }

        if ($event === 'status_message') {
            $saved = null;
            if ($wasapiDirection === 'out') {
                $saved = $this->handleOutgoingMessage($payload);
            }

            $status = $this->handleStatusUpdate($payload);
            if (is_array($saved) && !empty($saved['success'])) {
                $saved['status_updated'] = !empty($status['success']);
                return $saved;
            }

            return $status;
        }

        if ($event === 'status' || isset($payload['status'])) {
            return $this->handleStatusUpdate($payload);
        }

        return $this->handleIncomingMessage($payload);
    }

    private function handleIncomingMessage(array $payload): array
    {
        $message = $this->extractMessageData($payload);

        if ($message['phone'] === '') {
            return ['success' => false, 'error' => 'Falta numero remitente.'];
        }

        if ($message['external_id'] !== '') {
            $existing = $this->findExistingMessage($message['external_id']);
            if ($existing) {
                return [
                    'success'         => true,
                    'duplicate'       => true,
                    'contact_id'      => (int) $existing['contact_id'],
                    'conversation_id' => (int) $existing['conversation_id'],
                    'message_id'      => (int) $existing['id'],
                ];
            }
        }

        $contact = $this->findOrCreateContact($message['phone'], $message['contact_name']);

        // Buscar o crear conversacion abierta
        $conv = Conversation::findOpenByContact($this->tenantId, (int) $contact['id']);
        if (!$conv) {
            $convId = Conversation::create([
                'tenant_id'   => $this->tenantId,
                'contact_id'  => (int) $contact['id'],
                'channel'     => 'whatsapp',
                'status'      => 'new',
                'priority'    => 'normal',
                'unread_count'=> 1,
                'last_message'=> mb_substr($message['text'] ?: '[' . $message['type'] . ']', 0, 200),
                'last_message_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $convId = (int) $conv['id'];
            $this->touchConversation($convId, $message['text'] ?: '[' . $message['type'] . ']', true);
        }

        // Guardar mensaje
        $messageId = Message::create([
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $contact['id'],
            'direction'       => 'inbound',
            'type'            => $message['type'],
            'content'         => $message['text'],
            'media_url'       => $message['media_url'] ?: null,
            'external_id'     => $message['external_id'] ?: null,
            'status'          => 'received',
            'metadata'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        Contact::touchInteraction($this->tenantId, (int) $contact['id']);
        $this->markCampaignReply($message['phone']);

        // Disparar evento para automatizaciones
        \App\Core\Events::dispatch('message.received', [
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $contact['id'],
            'contact_phone'   => $message['phone'],
            'contact_email'   => (string) ($contact['email'] ?? ''),
            'first_name'      => (string) ($contact['first_name'] ?? ''),
            'last_name'       => (string) ($contact['last_name']  ?? ''),
            'message_content' => $message['text'],
            'message_type'    => $message['type'],
            'channel'         => 'whatsapp',
            'entity_type'     => 'message',
            'entity_id'       => $messageId,
        ]);

        try {
            (new AiAgentService($this->tenantId))->autoReplyToConversation(
                $convId,
                (int) $contact['id'],
                $message['phone'],
                $message['text'],
                $messageId
            );
        } catch (\Throwable $e) {
            Logger::error('No se pudo ejecutar agente IA automatico', [
                'tenant' => $this->tenantId,
                'conversation' => $convId,
                'msg' => $e->getMessage(),
            ]);
        }

        return [
            'success'         => true,
            'contact_id'      => (int) $contact['id'],
            'conversation_id' => $convId,
            'message_id'      => $messageId,
        ];
    }

    private function handleOutgoingMessage(array $payload): array
    {
        $message = $this->extractMessageData($payload);
        if ($message['phone'] === '') {
            return ['success' => false, 'error' => 'Falta numero destinatario.'];
        }

        if ($message['external_id'] !== '') {
            $existing = $this->findExistingMessage($message['external_id']);
            if ($existing) {
                return [
                    'success'         => true,
                    'duplicate'       => true,
                    'contact_id'      => (int) $existing['contact_id'],
                    'conversation_id' => (int) $existing['conversation_id'],
                    'message_id'      => (int) $existing['id'],
                ];
            }
        }

        $contact = $this->findOrCreateContact($message['phone'], $message['contact_name']);
        $conv = Conversation::findOpenByContact($this->tenantId, (int) $contact['id']);
        if (!$conv) {
            $convId = Conversation::create([
                'tenant_id'   => $this->tenantId,
                'contact_id'  => (int) $contact['id'],
                'channel'     => 'whatsapp',
                'status'      => 'open',
                'priority'    => 'normal',
                'unread_count'=> 0,
                'last_message'=> mb_substr($message['text'] ?: '[' . $message['type'] . ']', 0, 200),
                'last_message_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $convId = (int) $conv['id'];
            $this->touchConversation($convId, $message['text'] ?: '[' . $message['type'] . ']', false);
        }

        $status = $this->normalizeStatus($message['status']) ?: 'sent';
        $messageId = Message::create([
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $contact['id'],
            'direction'       => 'outbound',
            'type'            => $message['type'],
            'content'         => $message['text'],
            'media_url'       => $message['media_url'] ?: null,
            'external_id'     => $message['external_id'] ?: null,
            'status'          => $status,
            'sent_at'         => in_array($status, ['sent','delivered','read'], true) ? date('Y-m-d H:i:s') : null,
            'delivered_at'    => in_array($status, ['delivered','read'], true) ? date('Y-m-d H:i:s') : null,
            'read_at'         => $status === 'read' ? date('Y-m-d H:i:s') : null,
            'metadata'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        Contact::touchInteraction($this->tenantId, (int) $contact['id']);

        return [
            'success'         => true,
            'contact_id'      => (int) $contact['id'],
            'conversation_id' => $convId,
            'message_id'      => $messageId,
        ];
    }

    private function handleStatusUpdate(array $payload): array
    {
        $statusBlock = $this->eventData($payload);
        if (isset($payload['status']) && is_array($payload['status'])) {
            $statusBlock = $payload['status'];
        }
        $extId  = (string) ($statusBlock['wam_id'] ?? $statusBlock['external_id'] ?? $statusBlock['message_id'] ?? $statusBlock['id'] ?? '');
        $status = $this->normalizeStatus((string) ($statusBlock['status'] ?? ''));

        if ($extId === '' || $status === '') {
            return ['success' => false, 'error' => 'Datos incompletos en status.'];
        }

        $update = ['status' => $status];
        if ($status === 'sent')      $update['sent_at']      = date('Y-m-d H:i:s');
        if ($status === 'delivered') $update['delivered_at'] = date('Y-m-d H:i:s');
        if ($status === 'read')      $update['read_at']      = date('Y-m-d H:i:s');

        $set = [];
        $params = ['ext' => $extId, 'tenant' => $this->tenantId];
        foreach ($update as $col => $val) {
            $set[] = "`$col` = :$col";
            $params[$col] = $val;
        }

        Database::run(
            "UPDATE `messages` SET " . implode(', ', $set) . " WHERE `external_id` = :ext AND `tenant_id` = :tenant",
            $params
        );

        Database::run(
            "UPDATE `campaign_recipients`
             SET `status` = CASE
                    WHEN :status = 'delivered' THEN 'delivered'
                    WHEN :status = 'read' THEN 'read'
                    WHEN :status = 'failed' THEN 'failed'
                    ELSE `status`
                 END,
                 `delivered_at` = CASE WHEN :status IN ('delivered','read') AND `delivered_at` IS NULL THEN NOW() ELSE `delivered_at` END,
                 `read_at` = CASE WHEN :status = 'read' THEN NOW() ELSE `read_at` END,
                 `error_message` = CASE WHEN :status = 'failed' THEN 'Wasapi reporto fallo' ELSE `error_message` END
             WHERE `tenant_id` = :tenant AND `external_id` = :ext",
            ['status' => $status, 'tenant' => $this->tenantId, 'ext' => $extId]
        );

        return ['success' => true];
    }

    /**
     * Valida la firma/secret del webhook contra la configuracion del tenant.
     */
    public function validateWebhook(string $signature, string $rawBody): bool
    {
        $secret = (string) config('services.wasapi.webhook_secret');
        if ($secret === '') {
            // Si no hay secret configurado, no se rechaza (modo dev). En produccion DEBE estar.
            return true;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }

    private function logRequest(string $endpoint, array $payload, array $response): void
    {
        try {
            Database::insert('whatsapp_logs', [
                'tenant_id'     => $this->tenantId,
                'direction'     => 'outbound',
                'endpoint'      => $endpoint,
                'request_body'  => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'response_body' => mb_substr($response['raw'] ?? '', 0, 4000),
                'status_code'   => $response['status'] ?? null,
                'success'       => !empty($response['success']) ? 1 : 0,
                'error_message' => $response['error'] ?? null,
                'duration_ms'   => $response['duration_ms'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar log de Wasapi', ['msg' => $e->getMessage()]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone) ?? $phone;
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '' || str_contains($baseUrl, 'api.wasapi.io')) {
            return 'https://api-ws.wasapi.io/api/v1';
        }

        return $baseUrl;
    }

    private function normalizeWaId(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function normalizeDisplayPhone(string $phone): string
    {
        $digits = $this->normalizeWaId($phone);
        return $digits !== '' ? '+' . $digits : $this->normalizePhone($phone);
    }

    private function resolveFromId(array $cred): array
    {
        if ($this->resolvedFromId !== null) {
            return ['success' => true, 'from_id' => $this->resolvedFromId];
        }

        $resp = HttpClient::get(
            $cred['base_url'] . '/whatsapp-numbers',
            $this->authHeaders((string) $cred['api_key']),
            (int) config('services.wasapi.timeout', 30)
        );
        $this->logRequest('/whatsapp-numbers', [], $resp);

        if (empty($resp['success'])) {
            return [
                'success' => false,
                'error'   => 'No se pudo consultar /whatsapp-numbers en Wasapi.',
                'status'  => $resp['status'] ?? null,
                'body'    => $resp['body'] ?? null,
            ];
        }

        $numbers = $this->extractDataList($resp['body'] ?? []);
        if (empty($numbers)) {
            return ['success' => false, 'error' => 'La cuenta Wasapi no tiene numeros configurados.'];
        }

        $configured = $this->normalizeWaId((string) ($cred['phone'] ?? ''));
        $fallback = null;
        foreach ($numbers as $number) {
            if (!is_array($number) || empty($number['id'])) {
                continue;
            }
            $fallback ??= (int) $number['id'];
            $numberPhone = $this->normalizeWaId((string) ($number['phone_number'] ?? $number['phone'] ?? $number['wa_id'] ?? ''));
            if ($configured !== '' && $numberPhone === $configured) {
                $this->resolvedFromId = (int) $number['id'];
                return ['success' => true, 'from_id' => $this->resolvedFromId];
            }
        }

        if ($configured !== '') {
            return ['success' => false, 'error' => 'El numero configurado no existe en /whatsapp-numbers de Wasapi.'];
        }

        if ($fallback !== null) {
            $this->resolvedFromId = $fallback;
            return ['success' => true, 'from_id' => $fallback];
        }

        return ['success' => false, 'error' => 'No se pudo resolver from_id de Wasapi.'];
    }

    private function extractDataList(array $body): array
    {
        if (isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }
        return array_is_list($body) ? $body : [];
    }

    private function mediaFileKey(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'video', 'video_url'       => 'video_url',
            'document', 'document_url' => 'document_url',
            'audio', 'audio_url'       => 'audio_url',
            default                    => 'image_url',
        };
    }

    private function normalizeTemplateVars(array $variables): array
    {
        $vars = [];
        foreach ($variables as $value) {
            $vars[] = is_array($value) ? $value : ['text' => '{{' . (count($vars) + 1) . '}}', 'val' => (string) $value];
        }
        return $vars;
    }

    private function normalizeMessageResponse(array $resp): array
    {
        $body = $resp['body'] ?? [];
        if (!is_array($body)) {
            return $resp;
        }

        $externalId = $this->extractResponseExternalId($body);
        if ($externalId !== '') {
            $resp['body']['id'] = $externalId;
            $resp['body']['external_id'] = $externalId;
        }

        return $resp;
    }

    private function extractResponseExternalId(array $body): string
    {
        foreach (['wam_id', 'external_id', 'message_id', 'id'] as $key) {
            if (!empty($body[$key]) && is_scalar($body[$key])) {
                return (string) $body[$key];
            }
        }

        if (isset($body['data']) && is_array($body['data'])) {
            if (array_is_list($body['data'])) {
                foreach ($body['data'] as $item) {
                    if (is_array($item)) {
                        $id = $this->extractResponseExternalId($item);
                        if ($id !== '') {
                            return $id;
                        }
                    }
                }
            }
            return $this->extractResponseExternalId($body['data']);
        }

        return '';
    }

    private function normalizeEventName(string $event): string
    {
        return strtolower(str_replace(['-', ' '], '_', trim($event)));
    }

    private function eventData(array $payload): array
    {
        return isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
    }

    private function extractMessageData(array $payload): array
    {
        $data = $this->eventData($payload);
        $messageBlock = isset($data['message']) && is_array($data['message']) ? $data['message'] : $data;

        $text = '';
        if (isset($data['message']) && is_string($data['message'])) {
            $text = $data['message'];
        } else {
            $text = (string) (
                $messageBlock['text']
                ?? $messageBlock['body']
                ?? $data['caption']
                ?? $messageBlock['caption']
                ?? ''
            );
        }

        $rawType = (string) ($data['message_type'] ?? $messageBlock['message_type'] ?? $messageBlock['type'] ?? 'text');
        $type = $this->mapMessageType($rawType);

        $phone = (string) (
            $data['wa_id']
            ?? $data['from']
            ?? $data['phone']
            ?? $payload['from']
            ?? $payload['phone']
            ?? ($payload['contact']['phone'] ?? '')
        );

        $contactName = (string) (
            ($data['contact']['name'] ?? null)
            ?? ($payload['contact']['name'] ?? null)
            ?? ($payload['name'] ?? null)
            ?? 'Contacto WhatsApp'
        );

        $externalId = (string) (
            $data['wam_id']
            ?? $messageBlock['wam_id']
            ?? $messageBlock['external_id']
            ?? $data['external_id']
            ?? $data['message_id']
            ?? $messageBlock['id']
            ?? $data['id']
            ?? ''
        );

        return [
            'phone'        => $this->normalizeDisplayPhone($phone),
            'contact_name' => $contactName,
            'text'         => $text,
            'type'         => $type,
            'media_url'    => $this->extractMediaUrl($data, $messageBlock),
            'external_id'  => $externalId,
            'status'       => (string) ($data['status'] ?? $messageBlock['status'] ?? ''),
        ];
    }

    private function extractMediaUrl(array $data, array $messageBlock): string
    {
        $candidates = [
            $messageBlock['media_url'] ?? null,
            $messageBlock['url'] ?? null,
            $messageBlock['image_url'] ?? null,
            $messageBlock['video_url'] ?? null,
            $messageBlock['document_url'] ?? null,
            $messageBlock['audio_url'] ?? null,
            $data['media_url'] ?? null,
            $data['url'] ?? null,
            $data['image_url'] ?? null,
            $data['video_url'] ?? null,
            $data['document_url'] ?? null,
            $data['audio_url'] ?? null,
        ];

        if (isset($data['data']) && is_string($data['data'])) {
            $candidates[] = $data['data'];
        }
        if (isset($data['data']) && is_array($data['data'])) {
            foreach (['media_url', 'url', 'header_image', 'image_url', 'video_url', 'document_url', 'audio_url'] as $key) {
                $candidates[] = $data['data'][$key] ?? null;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_URL)) {
                return $candidate;
            }
        }

        return '';
    }

    private function mapMessageType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'image', 'document', 'audio', 'video', 'location', 'contact', 'sticker', 'template', 'text' => $type,
            'button', 'buttons', 'list', 'interactive' => 'interactive',
            default => 'text',
        };
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'queued', 'pending' => 'queued',
            'sent', 'send' => 'sent',
            'delivered', 'delivery' => 'delivered',
            'read', 'seen' => 'read',
            'failed', 'error' => 'failed',
            'received' => 'received',
            default => '',
        };
    }

    private function findExistingMessage(string $externalId): ?array
    {
        return Database::fetch(
            "SELECT id, contact_id, conversation_id, direction, status
             FROM messages
             WHERE tenant_id = :tenant AND external_id = :ext
             ORDER BY id DESC LIMIT 1",
            ['tenant' => $this->tenantId, 'ext' => $externalId]
        );
    }

    private function findOrCreateContact(string $phone, string $contactName): array
    {
        $digits = $this->normalizeWaId($phone);
        $withPlus = $digits !== '' ? '+' . $digits : $phone;
        $withoutPlus = $digits;

        $contact = Database::fetch(
            "SELECT * FROM contacts
             WHERE tenant_id = :tenant AND deleted_at IS NULL
               AND (phone IN (:p1, :p2) OR whatsapp IN (:w1, :w2))
             LIMIT 1",
            [
                'tenant' => $this->tenantId,
                'p1' => $withPlus,
                'p2' => $withoutPlus,
                'w1' => $withPlus,
                'w2' => $withoutPlus,
            ]
        );

        // Si el contacto existe pero tiene nombre generico, intentamos enriquecerlo
        if ($contact) {
            $existingName = trim((string) ($contact['first_name'] ?? ''));
            $isGeneric = $existingName === '' || $existingName === 'Contacto WhatsApp' || $existingName === 'Contacto';
            if ($isGeneric) {
                $profile = $this->getContactProfile($phone);
                if ($profile && ($profile['first_name'] !== '' || $profile['last_name'] !== '')) {
                    Database::update('contacts', [
                        'first_name' => $profile['first_name'] ?: 'Contacto WhatsApp',
                        'last_name'  => $profile['last_name'] ?: null,
                        'email'      => $contact['email'] ?: ($profile['email'] ?: null),
                    ], ['id' => (int) $contact['id'], 'tenant_id' => $this->tenantId]);
                    $contact['first_name'] = $profile['first_name'] ?: 'Contacto WhatsApp';
                    $contact['last_name']  = $profile['last_name'] ?: null;
                }
            }
            return $contact;
        }

        // Para nuevos: preferimos el perfil real de Wasapi sobre el nombre del payload del webhook
        $first = trim($contactName);
        $last  = '';
        $email = '';
        $profile = $this->getContactProfile($phone);
        if ($profile && ($profile['first_name'] !== '' || $profile['last_name'] !== '')) {
            $first = $profile['first_name'];
            $last  = $profile['last_name'];
            $email = $profile['email'];
        }
        if ($first === '') $first = 'Contacto WhatsApp';

        $contactId = Contact::createMinimal($this->tenantId, [
            'first_name' => $first,
            'last_name'  => $last !== '' ? $last : null,
            'email'      => $email !== '' ? $email : null,
            'phone'      => $withPlus,
            'whatsapp'   => $withPlus,
            'source'     => 'whatsapp',
        ]);

        return Contact::findById($contactId) ?? [
            'id' => $contactId,
            'first_name' => $first,
            'last_name'  => $last ?: null,
            'phone' => $withPlus,
            'whatsapp' => $withPlus,
        ];
    }

    private function touchConversation(int $id, string $lastMessage, bool $incrementUnread): void
    {
        Database::run(
            "UPDATE conversations
             SET last_message = :message,
                 last_message_at = NOW(),
                 unread_count = unread_count + :inc,
                 status = CASE WHEN status IN ('resolved','closed') THEN 'open' ELSE status END
             WHERE id = :id AND tenant_id = :tenant",
            [
                'message' => mb_substr($lastMessage, 0, 200),
                'inc'     => $incrementUnread ? 1 : 0,
                'id'      => $id,
                'tenant'  => $this->tenantId,
            ]
        );
    }

    private function markCampaignReply(string $phone): void
    {
        $digits = $this->normalizeWaId($phone);
        if ($digits === '') {
            return;
        }

        Database::run(
            "UPDATE campaign_recipients
             SET status = 'replied', replied_at = NOW()
             WHERE tenant_id = :tenant
               AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '') LIKE :phone
               AND status IN ('sent','delivered','read')
             ORDER BY id DESC
             LIMIT 1",
            ['tenant' => $this->tenantId, 'phone' => '%' . $digits]
        );
    }
}
