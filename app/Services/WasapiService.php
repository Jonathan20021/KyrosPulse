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
 * https://api.wasapi.io
 *
 * Cada tenant guarda su propia API key. Si no, se usa la global del .env como fallback.
 */
final class WasapiService
{
    public function __construct(private int $tenantId) {}

    private function credentials(): array
    {
        $tenant  = Tenant::findById($this->tenantId);
        $apiKey  = $tenant['wasapi_api_key'] ?? '';
        $baseUrl = (string) config('services.wasapi.base_url');

        if ($apiKey === '') {
            $apiKey = (string) config('services.wasapi.api_key');
        }

        return ['api_key' => $apiKey, 'base_url' => rtrim($baseUrl, '/')];
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

        $payload = [
            'phone'   => $this->normalizePhone($phone),
            'message' => $message,
        ];

        $resp = HttpClient::post(
            $cred['base_url'] . '/numbers/send_message',
            $payload,
            $this->authHeaders($cred['api_key']),
            (int) config('services.wasapi.timeout', 30)
        );

        $this->logRequest('/numbers/send_message', $payload, $resp);

        return $resp;
    }

    public function sendMediaMessage(string $phone, string $mediaUrl, string $caption = '', string $type = 'image'): array
    {
        $cred = $this->credentials();
        if ($cred['api_key'] === '') {
            return ['success' => false, 'error' => 'No hay API key Wasapi configurada.'];
        }

        $payload = [
            'phone'     => $this->normalizePhone($phone),
            'media_url' => $mediaUrl,
            'type'      => $type,
            'caption'   => $caption,
        ];

        $resp = HttpClient::post(
            $cred['base_url'] . '/numbers/send_media',
            $payload,
            $this->authHeaders($cred['api_key'])
        );

        $this->logRequest('/numbers/send_media', $payload, $resp);
        return $resp;
    }

    public function sendTemplate(string $phone, string $templateName, array $variables = []): array
    {
        $cred = $this->credentials();
        if ($cred['api_key'] === '') {
            return ['success' => false, 'error' => 'No hay API key Wasapi configurada.'];
        }

        $payload = [
            'phone'         => $this->normalizePhone($phone),
            'template_name' => $templateName,
            'variables'     => $variables,
        ];

        $resp = HttpClient::post(
            $cred['base_url'] . '/templates/send',
            $payload,
            $this->authHeaders($cred['api_key'])
        );

        $this->logRequest('/templates/send', $payload, $resp);
        return $resp;
    }

    public function getTemplates(): array
    {
        $cred = $this->credentials();
        if ($cred['api_key'] === '') {
            return ['success' => false, 'data' => []];
        }
        $resp = HttpClient::get(
            $cred['base_url'] . '/templates',
            $this->authHeaders($cred['api_key'])
        );
        $this->logRequest('/templates', [], $resp);
        return $resp;
    }

    /**
     * Procesa un payload entrante del webhook de Wasapi.
     * Estructura esperada (esquema flexible):
     *   {
     *     "type": "message" | "status",
     *     "from": "+18091234567",
     *     "message": { "text": "...", "type": "text", "external_id": "...", ... },
     *     "status":  { "external_id": "...", "status": "delivered" }
     *   }
     */
    public function processWebhook(array $payload): array
    {
        Logger::info('Wasapi webhook received', ['tenant' => $this->tenantId]);

        $type = $payload['type'] ?? ($payload['event'] ?? 'message');

        if ($type === 'status' || isset($payload['status'])) {
            return $this->handleStatusUpdate($payload);
        }

        return $this->handleIncomingMessage($payload);
    }

    private function handleIncomingMessage(array $payload): array
    {
        $from    = (string) ($payload['from'] ?? $payload['phone'] ?? ($payload['contact']['phone'] ?? ''));
        $msgData = $payload['message'] ?? $payload;
        $text    = (string) ($msgData['text'] ?? $msgData['body'] ?? '');
        $extId   = (string) ($msgData['external_id'] ?? $msgData['id'] ?? '');
        $type    = (string) ($msgData['type'] ?? 'text');
        $media   = (string) ($msgData['media_url'] ?? '');

        if ($from === '') {
            return ['success' => false, 'error' => 'Falta numero remitente.'];
        }

        $phone = $this->normalizePhone($from);
        $contactName = (string) ($payload['contact']['name'] ?? $payload['name'] ?? 'Contacto WhatsApp');

        // Crear contacto si no existe
        $contact = Contact::findByPhone($this->tenantId, $phone);
        if (!$contact) {
            $contactId = Contact::createMinimal($this->tenantId, [
                'first_name' => $contactName,
                'phone'      => $phone,
                'whatsapp'   => $phone,
                'source'     => 'whatsapp',
            ]);
            $contact = Contact::findById($contactId);
        }

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
                'last_message'=> mb_substr($text ?: '[' . $type . ']', 0, 200),
                'last_message_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $convId = (int) $conv['id'];
            Conversation::touch($convId, $text ?: '[' . $type . ']');
        }

        // Guardar mensaje
        Message::create([
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $contact['id'],
            'direction'       => 'inbound',
            'type'            => in_array($type, ['text','image','document','audio','video','location','contact','sticker','template'], true) ? $type : 'text',
            'content'         => $text,
            'media_url'       => $media ?: null,
            'external_id'     => $extId ?: null,
            'status'          => 'received',
            'metadata'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        Contact::touchInteraction($this->tenantId, (int) $contact['id']);

        // Disparar evento para automatizaciones
        \App\Core\Events::dispatch('message.received', [
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $contact['id'],
            'contact_phone'   => $phone,
            'contact_email'   => (string) ($contact['email'] ?? ''),
            'first_name'      => (string) ($contact['first_name'] ?? ''),
            'last_name'       => (string) ($contact['last_name']  ?? ''),
            'message_content' => $text,
            'message_type'    => $type,
            'channel'         => 'whatsapp',
            'entity_type'     => 'message',
            'entity_id'       => (int) ($contact['id']),
        ]);

        return [
            'success'         => true,
            'contact_id'      => (int) $contact['id'],
            'conversation_id' => $convId,
        ];
    }

    private function handleStatusUpdate(array $payload): array
    {
        $statusBlock = $payload['status'] ?? $payload;
        $extId  = (string) ($statusBlock['external_id'] ?? $statusBlock['id'] ?? '');
        $status = (string) ($statusBlock['status'] ?? '');

        if ($extId === '' || $status === '') {
            return ['success' => false, 'error' => 'Datos incompletos en status.'];
        }

        $allowed = ['queued','sent','delivered','read','failed','received'];
        if (!in_array($status, $allowed, true)) {
            return ['success' => false, 'error' => 'Status no soportado.'];
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
}
