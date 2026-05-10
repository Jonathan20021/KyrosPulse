<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Models\Contact;
use App\Models\Conversation;
use App\Services\ChannelDispatcher;

/**
 * Endpoints de mensajeria.
 *
 *   POST /api/v1/messages          enviar texto a un telefono o contact_id
 *   GET  /api/v1/messages          listar ultimos N mensajes (?conversation_id=)
 */
final class ApiMessageController extends ApiController
{
    public function send(Request $request): void
    {
        $tenantId = $this->tenantId();
        $clean = $this->validateApi($request, [
            'text' => 'required|string|min:1|max:4096',
        ]);

        $phone     = trim((string) $request->input('to', ''));
        $contactId = (int) $request->input('contact_id', 0);
        $channelId = $request->input('channel_id');
        $channelId = $channelId === null ? null : (int) $channelId;

        if ($phone === '' && $contactId === 0) {
            $this->error('validation_failed', 'Provide either `to` (phone) or `contact_id`.', 422);
        }

        // Resolver contacto y telefono final
        $contact = null;
        if ($contactId) {
            $contact = Contact::findById($contactId);
            if (!$contact) {
                $this->error('not_found', 'Contact not found.', 404);
            }
            $phone = (string) ($contact['whatsapp'] ?? $contact['phone'] ?? '');
        } else {
            $contact = Contact::findByPhone($tenantId, $phone);
        }

        if ($phone === '') {
            $this->error('validation_failed', 'Resolved phone is empty.', 422);
        }

        // Crear contacto minimo si no existe (para que la conversacion se ate)
        if (!$contact) {
            $cid = Contact::createMinimal($tenantId, [
                'first_name' => 'Lead',
                'whatsapp'   => $phone,
                'phone'      => $phone,
            ]);
            $contact = Contact::findById($cid);
        }

        $conv = Conversation::findOpenByContact($tenantId, (int) $contact['id']);
        if (!$conv) {
            $convId = Conversation::create([
                'tenant_id'  => $tenantId,
                'contact_id' => (int) $contact['id'],
                'channel'    => 'whatsapp',
                'status'     => 'open',
                'channel_id' => $channelId,
            ]);
        } else {
            $convId = (int) $conv['id'];
        }

        $send = (new ChannelDispatcher($tenantId))->sendText($phone, (string) $clean['text'], $channelId);

        $messageId = Database::insert('messages', [
            'tenant_id'       => $tenantId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $contact['id'],
            'channel_id'      => $channelId,
            'direction'       => 'outbound',
            'type'            => 'text',
            'content'         => (string) $clean['text'],
            'is_ai_generated' => 0,
            'status'          => !empty($send['success']) ? 'sent' : 'failed',
            'external_id'     => $send['body']['id'] ?? null,
            'sent_at'         => !empty($send['success']) ? date('Y-m-d H:i:s') : null,
            'error_message'   => !empty($send['success']) ? null : ($send['error'] ?? 'Send failed'),
            'metadata'        => json_encode([
                'source'     => 'api',
                'api_key_id' => $this->apiKey()['id'] ?? null,
                'request_id' => $this->requestId(),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        if (empty($send['success'])) {
            $this->error('send_failed', (string) ($send['error'] ?? 'Send failed'), 502, [
                'message_id'      => $messageId,
                'conversation_id' => $convId,
            ]);
        }

        $this->ok([
            'message_id'      => $messageId,
            'conversation_id' => $convId,
            'contact_id'      => (int) $contact['id'],
            'to'              => $phone,
            'status'          => 'sent',
            'external_id'     => $send['body']['id'] ?? null,
        ], 201);
    }

    public function index(Request $request): void
    {
        $tenantId = $this->tenantId();
        $convId = (int) $request->query('conversation_id', 0);
        if (!$convId) {
            $this->error('validation_failed', '`conversation_id` query param is required.', 422);
        }
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $rows = Database::fetchAll(
            "SELECT id, direction, type, content, status, external_id, is_ai_generated,
                    sent_at, created_at
             FROM messages
             WHERE tenant_id = :t AND conversation_id = :c
             ORDER BY id DESC
             LIMIT $limit",
            ['t' => $tenantId, 'c' => $convId]
        );
        $this->ok(array_map(fn($m) => [
            'id'              => (int) $m['id'],
            'direction'       => $m['direction'],
            'type'            => $m['type'],
            'content'         => $m['content'],
            'status'          => $m['status'],
            'external_id'     => $m['external_id'],
            'is_ai_generated' => (bool) $m['is_ai_generated'],
            'sent_at'         => $m['sent_at'],
            'created_at'      => $m['created_at'],
        ], $rows));
    }
}
