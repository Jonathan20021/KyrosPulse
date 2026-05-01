<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappChannel;

/**
 * Servicio para WhatsApp Cloud API (Meta Business).
 * https://developers.facebook.com/docs/whatsapp/cloud-api
 *
 * Cada canal almacena phone_number_id, business_account_id, access_token y webhook_verify
 * en la tabla whatsapp_channels.
 */
final class WhatsappCloudService
{
    private const GRAPH_URL = 'https://graph.facebook.com/v20.0';

    private array $channel;
    private array $pendingAiCalls = [];

    public function __construct(private int $tenantId, array $channel)
    {
        $this->channel = $channel;
    }

    /** @return array<int, array> */
    public function pendingAiCalls(): array { return $this->pendingAiCalls; }

    public static function forChannelId(int $tenantId, int $channelId): ?self
    {
        $channel = WhatsappChannel::findById($tenantId, $channelId);
        if (!$channel || $channel['provider'] !== 'cloud') return null;
        return new self($tenantId, $channel);
    }

    public function sendTextMessage(string $phone, string $message): array
    {
        $waId = $this->normalizeWaId($phone);
        if ($waId === '') {
            return ['success' => false, 'error' => 'Numero WhatsApp invalido.'];
        }
        if (empty($this->channel['phone_number_id']) || empty($this->channel['access_token'])) {
            return ['success' => false, 'error' => 'Cloud API no configurado (phone_number_id / access_token).'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $waId,
            'type' => 'text',
            'text' => ['body' => $message, 'preview_url' => true],
        ];

        $resp = HttpClient::post(
            self::GRAPH_URL . '/' . $this->channel['phone_number_id'] . '/messages',
            $payload,
            $this->headers(),
            (int) config('services.wasapi.timeout', 30)
        );
        $resp = $this->normalizeMessageResponse($resp);
        $this->logRequest('messages', $payload, $resp);
        return $resp;
    }

    public function sendMediaMessage(string $phone, string $mediaUrl, string $caption = '', string $type = 'image'): array
    {
        $waId = $this->normalizeWaId($phone);
        if ($waId === '') return ['success' => false, 'error' => 'Numero WhatsApp invalido.'];
        if (empty($this->channel['phone_number_id']) || empty($this->channel['access_token'])) {
            return ['success' => false, 'error' => 'Cloud API no configurado.'];
        }

        $type = strtolower($type);
        if (!in_array($type, ['image','video','audio','document','sticker'], true)) $type = 'image';

        $media = ['link' => $mediaUrl];
        if ($caption !== '' && in_array($type, ['image','video','document'], true)) {
            $media['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $waId,
            'type' => $type,
            $type => $media,
        ];

        $resp = HttpClient::post(
            self::GRAPH_URL . '/' . $this->channel['phone_number_id'] . '/messages',
            $payload,
            $this->headers()
        );
        $resp = $this->normalizeMessageResponse($resp);
        $this->logRequest('messages.media', $payload, $resp);
        return $resp;
    }

    public function sendTemplate(string $phone, string $templateName, string $language = 'es', array $components = []): array
    {
        $waId = $this->normalizeWaId($phone);
        if ($waId === '') return ['success' => false, 'error' => 'Numero invalido.'];

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $waId,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];
        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $resp = HttpClient::post(
            self::GRAPH_URL . '/' . $this->channel['phone_number_id'] . '/messages',
            $payload,
            $this->headers()
        );
        $resp = $this->normalizeMessageResponse($resp);
        $this->logRequest('messages.template', $payload, $resp);
        return $resp;
    }

    /**
     * Verifica un webhook GET (handshake de Meta).
     * Devuelve el hub_challenge si valido, null si no.
     */
    public static function verifyWebhook(array $channel, string $mode, string $verifyToken, string $challenge): ?string
    {
        if ($mode !== 'subscribe') return null;
        $expected = (string) ($channel['webhook_verify'] ?? '');
        if ($expected !== '' && hash_equals($expected, $verifyToken)) {
            return $challenge;
        }
        return null;
    }

    public function processWebhook(array $payload): array
    {
        Logger::info('Cloud API webhook received', ['tenant' => $this->tenantId, 'channel' => $this->channel['id'] ?? null]);

        $entries = $payload['entry'] ?? [];
        if (!is_array($entries)) return ['success' => false, 'error' => 'Sin entries'];

        $results = [];
        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                if (!is_array($value)) continue;

                // Mensajes entrantes
                foreach ($value['messages'] ?? [] as $msg) {
                    $contactName = '';
                    foreach ($value['contacts'] ?? [] as $contact) {
                        if (($contact['wa_id'] ?? '') === ($msg['from'] ?? '')) {
                            $contactName = (string) ($contact['profile']['name'] ?? '');
                        }
                    }
                    $results[] = $this->handleIncoming($msg, $contactName, $value);
                }

                // Estados de mensajes salientes
                foreach ($value['statuses'] ?? [] as $status) {
                    $results[] = $this->handleStatus($status);
                }
            }
        }

        return [
            'success' => true,
            'processed' => count($results),
            'results' => $results,
        ];
    }

    private function handleIncoming(array $msg, string $contactName, array $value): array
    {
        $phone = $this->normalizeDisplayPhone((string) ($msg['from'] ?? ''));
        if ($phone === '') return ['success' => false, 'error' => 'sin numero'];

        $type = (string) ($msg['type'] ?? 'text');
        $text = '';
        $mediaUrl = '';
        switch ($type) {
            case 'text':
                $text = (string) ($msg['text']['body'] ?? '');
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'sticker':
                $text = (string) ($msg[$type]['caption'] ?? '');
                $mediaId = (string) ($msg[$type]['id'] ?? '');
                $mediaUrl = $mediaId !== '' ? $this->resolveMediaUrl($mediaId) : '';
                break;
            case 'button':
                $text = (string) ($msg['button']['text'] ?? '');
                break;
            case 'interactive':
                $text = (string) ($msg['interactive']['button_reply']['title']
                    ?? $msg['interactive']['list_reply']['title']
                    ?? '');
                break;
            case 'location':
                $loc = $msg['location'] ?? [];
                $text = sprintf('[Ubicacion] %s, %s', $loc['latitude'] ?? '', $loc['longitude'] ?? '');
                break;
        }

        $externalId = (string) ($msg['id'] ?? '');
        if ($externalId !== '') {
            $existing = Database::fetch(
                "SELECT id FROM messages WHERE tenant_id = :t AND external_id = :e LIMIT 1",
                ['t' => $this->tenantId, 'e' => $externalId]
            );
            if ($existing) return ['success' => true, 'duplicate' => true, 'message_id' => (int) $existing['id']];
        }

        $contact = $this->findOrCreateContact($phone, $contactName);
        $conv = Conversation::findOpenByContact($this->tenantId, (int) $contact['id']);
        if (!$conv) {
            $convId = Conversation::create([
                'tenant_id'       => $this->tenantId,
                'contact_id'      => (int) $contact['id'],
                'channel'         => 'whatsapp',
                'channel_id'      => (int) $this->channel['id'],
                'from_phone'      => $this->channel['phone'],
                'status'          => 'new',
                'priority'        => 'normal',
                'unread_count'    => 1,
                'last_message'    => mb_substr($text ?: '[' . $type . ']', 0, 200),
                'last_message_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $convId = (int) $conv['id'];
            Database::run(
                "UPDATE conversations
                 SET last_message = :m, last_message_at = NOW(),
                     unread_count = unread_count + 1, channel_id = :c, from_phone = :f,
                     status = CASE WHEN status IN ('resolved','closed') THEN 'open' ELSE status END
                 WHERE id = :id AND tenant_id = :t",
                [
                    'm' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                    'c' => (int) $this->channel['id'],
                    'f' => $this->channel['phone'],
                    'id' => $convId,
                    't' => $this->tenantId,
                ]
            );
        }

        $messageId = Message::create([
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $contact['id'],
            'channel_id'      => (int) $this->channel['id'],
            'from_phone'      => $this->channel['phone'],
            'direction'       => 'inbound',
            'type'            => $this->mapMessageType($type),
            'content'         => $text,
            'media_url'       => $mediaUrl ?: null,
            'external_id'     => $externalId ?: null,
            'status'          => 'received',
            'metadata'        => json_encode($msg, JSON_UNESCAPED_UNICODE),
        ]);

        Contact::touchInteraction($this->tenantId, (int) $contact['id']);
        WhatsappChannel::touchActivity((int) $this->channel['id']);

        // Aplicar reglas de routing
        try {
            (new RoutingEngine($this->tenantId))->apply([
                'conversation_id' => $convId,
                'channel_id'      => (int) $this->channel['id'],
                'channel'         => 'whatsapp',
                'message'         => $text,
                'contact_id'      => (int) $contact['id'],
            ]);
        } catch (\Throwable $e) {
            Logger::error('RoutingEngine fallo (cloud)', ['msg' => $e->getMessage()]);
        }

        \App\Core\Events::dispatch('message.received', [
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $contact['id'],
            'contact_phone'   => $phone,
            'first_name'      => (string) ($contact['first_name'] ?? ''),
            'last_name'       => (string) ($contact['last_name'] ?? ''),
            'message_content' => $text,
            'message_type'    => $type,
            'channel'         => 'whatsapp',
            'channel_id'      => (int) $this->channel['id'],
            'entity_type'     => 'message',
            'entity_id'       => $messageId,
        ]);

        // Encolar AI para despues de responder el webhook
        $this->pendingAiCalls[] = [
            'conversation_id' => $convId,
            'contact_id'      => (int) $contact['id'],
            'phone'           => $phone,
            'text'            => $text,
            'inbound_id'      => $messageId,
        ];

        return [
            'success' => true,
            'contact_id' => (int) $contact['id'],
            'conversation_id' => $convId,
            'message_id' => $messageId,
        ];
    }

    private function handleStatus(array $status): array
    {
        $extId = (string) ($status['id'] ?? '');
        $statusName = $this->normalizeStatus((string) ($status['status'] ?? ''));
        if ($extId === '' || $statusName === '') return ['success' => false];

        $update = ['status' => $statusName];
        if ($statusName === 'sent')      $update['sent_at']      = date('Y-m-d H:i:s');
        if ($statusName === 'delivered') $update['delivered_at'] = date('Y-m-d H:i:s');
        if ($statusName === 'read')      $update['read_at']      = date('Y-m-d H:i:s');

        $set = [];
        $params = ['ext' => $extId, 'tenant' => $this->tenantId];
        foreach ($update as $col => $val) {
            $set[] = "`$col` = :$col";
            $params[$col] = $val;
        }
        Database::run(
            "UPDATE messages SET " . implode(', ', $set) . " WHERE external_id = :ext AND tenant_id = :tenant",
            $params
        );
        return ['success' => true, 'external_id' => $extId, 'status' => $statusName];
    }

    private function findOrCreateContact(string $phone, string $contactName): array
    {
        $digits = $this->normalizeWaId($phone);
        $withPlus = $digits !== '' ? '+' . $digits : $phone;

        $contact = Database::fetch(
            "SELECT * FROM contacts
             WHERE tenant_id = :tenant AND deleted_at IS NULL
               AND (phone IN (:p1, :p2) OR whatsapp IN (:p1, :p2))
             LIMIT 1",
            ['tenant' => $this->tenantId, 'p1' => $withPlus, 'p2' => $digits]
        );

        if ($contact) return $contact;

        $first = trim($contactName) ?: 'Contacto WhatsApp';

        $contactId = Contact::createMinimal($this->tenantId, [
            'first_name' => $first,
            'phone'      => $withPlus,
            'whatsapp'   => $withPlus,
            'source'     => 'whatsapp_cloud',
        ]);
        return Contact::findById($contactId) ?? ['id' => $contactId, 'first_name' => $first, 'phone' => $withPlus, 'whatsapp' => $withPlus];
    }

    public function getNumberInfo(): array
    {
        if (empty($this->channel['phone_number_id'])) return ['success' => false, 'error' => 'phone_number_id requerido'];
        return HttpClient::get(
            self::GRAPH_URL . '/' . $this->channel['phone_number_id'] . '?fields=display_phone_number,verified_name,quality_rating,messaging_limit_tier',
            $this->headers()
        );
    }

    private function resolveMediaUrl(string $mediaId): string
    {
        $resp = HttpClient::get(self::GRAPH_URL . '/' . $mediaId, $this->headers());
        if (empty($resp['success'])) return '';
        return (string) ($resp['body']['url'] ?? '');
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . ($this->channel['access_token'] ?? ''),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];
    }

    private function normalizeWaId(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function normalizeDisplayPhone(string $phone): string
    {
        $digits = $this->normalizeWaId($phone);
        return $digits !== '' ? '+' . $digits : $phone;
    }

    private function mapMessageType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'text', 'image', 'document', 'audio', 'video', 'location', 'sticker', 'template' => $type,
            'button', 'interactive' => 'interactive',
            default => 'text',
        };
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'sent', 'send' => 'sent',
            'delivered'    => 'delivered',
            'read'         => 'read',
            'failed'       => 'failed',
            default        => '',
        };
    }

    private function normalizeMessageResponse(array $resp): array
    {
        $body = $resp['body'] ?? [];
        if (!is_array($body)) return $resp;
        $messages = $body['messages'] ?? [];
        if (is_array($messages) && !empty($messages[0]['id'])) {
            $resp['body']['id']          = $messages[0]['id'];
            $resp['body']['external_id'] = $messages[0]['id'];
        }
        return $resp;
    }

    private function logRequest(string $endpoint, array $payload, array $response): void
    {
        try {
            Database::insert('whatsapp_logs', [
                'tenant_id'     => $this->tenantId,
                'channel_id'    => (int) $this->channel['id'],
                'provider'      => 'cloud',
                'direction'     => 'outbound',
                'endpoint'      => $endpoint,
                'request_body'  => mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '', 0, 4000),
                'response_body' => mb_substr($response['raw'] ?? json_encode($response['body'] ?? '', JSON_UNESCAPED_UNICODE) ?: '', 0, 4000),
                'status_code'   => $response['status'] ?? null,
                'success'       => !empty($response['success']) ? 1 : 0,
                'error_message' => $response['error'] ?? null,
                'duration_ms'   => $response['duration_ms'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar log Cloud API', ['msg' => $e->getMessage()]);
        }
    }
}
