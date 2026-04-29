<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Events;
use App\Core\Logger;
use App\Models\Contact;

/**
 * Motor de automatizaciones.
 *
 * Cada automatizacion tiene:
 *   - trigger_event   (str)  ej: "message.received", "contact.created", "lead.stage_changed"
 *   - conditions      (json) lista de objetos con {type, op, value}
 *   - actions         (json) lista de objetos con {type, params}
 *
 * Cuando llega un evento, este motor:
 *   1. Carga todas las automatizaciones activas del tenant para ese trigger.
 *   2. Evalua las condiciones contra el payload + datos relacionados (contact, message, etc).
 *   3. Si pasa, ejecuta las acciones secuencialmente y registra el resultado.
 */
final class AutomationEngine
{
    public static function bootstrap(): void
    {
        // Suscribir a todos los eventos relevantes con un solo listener wildcard.
        Events::listen('*', [self::class, 'onEvent']);
    }

    public static function onEvent(array $payload): void
    {
        $event    = (string) ($payload['_event'] ?? '');
        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        if ($event === '' || $tenantId === 0 || str_starts_with($event, '_')) return;

        try {
            $rules = Database::fetchAll(
                "SELECT * FROM automations
                 WHERE tenant_id = :t AND is_active = 1 AND trigger_event = :e",
                ['t' => $tenantId, 'e' => $event]
            );
        } catch (\Throwable $e) {
            Logger::error('Engine: no se pudo cargar reglas', ['msg' => $e->getMessage()]);
            return;
        }

        foreach ($rules as $rule) {
            self::runRule($rule, $payload);
        }
    }

    private static function runRule(array $rule, array $payload): void
    {
        $start = microtime(true);
        $conditions = json_decode((string) ($rule['conditions'] ?? '[]'), true) ?: [];
        $actions    = json_decode((string) ($rule['actions']    ?? '[]'), true) ?: [];

        try {
            $passed = self::evaluateConditions($conditions, $payload, (int) $rule['tenant_id']);
            if (!$passed) {
                self::log($rule, $payload, 'skipped', null, [], (int) ((microtime(true) - $start) * 1000));
                return;
            }

            $executed = [];
            foreach ($actions as $action) {
                $result = self::executeAction((array) $action, $payload, (int) $rule['tenant_id']);
                $executed[] = $result;
            }

            Database::run("UPDATE automations SET runs_count = runs_count + 1, last_run_at = NOW() WHERE id = :id", ['id' => $rule['id']]);
            self::log($rule, $payload, 'success', null, $executed, (int) ((microtime(true) - $start) * 1000));
        } catch (\Throwable $e) {
            Logger::error('Engine: error ejecutando regla', ['rule' => $rule['id'], 'msg' => $e->getMessage()]);
            self::log($rule, $payload, 'failed', $e->getMessage(), [], (int) ((microtime(true) - $start) * 1000));
        }
    }

    // ----------- CONDICIONES -----------

    private static function evaluateConditions(array $conditions, array $payload, int $tenantId): bool
    {
        if (empty($conditions)) return true;

        foreach ($conditions as $cond) {
            if (!self::evalSingle((array) $cond, $payload, $tenantId)) {
                return false; // AND implicito
            }
        }
        return true;
    }

    private static function evalSingle(array $cond, array $payload, int $tenantId): bool
    {
        $type  = (string) ($cond['type']  ?? '');
        $op    = (string) ($cond['op']    ?? 'equals');
        $value = $cond['value'] ?? null;

        switch ($type) {
            case 'message_contains':
                $msg = strtolower((string) ($payload['message_content'] ?? ''));
                $needle = strtolower((string) $value);
                return $needle !== '' && str_contains($msg, $needle);

            case 'message_not_contains':
                $msg = strtolower((string) ($payload['message_content'] ?? ''));
                return !str_contains($msg, strtolower((string) $value));

            case 'business_hours':
                return self::isInBusinessHours($tenantId);

            case 'outside_business_hours':
                return !self::isInBusinessHours($tenantId);

            case 'contact_has_tag':
                $contactId = (int) ($payload['contact_id'] ?? 0);
                if (!$contactId) return false;
                $count = Database::fetchColumn(
                    "SELECT COUNT(*) FROM contact_tags ct
                     INNER JOIN tags t ON t.id = ct.tag_id
                     WHERE ct.contact_id = :c AND t.name = :v AND ct.tenant_id = :t",
                    ['c' => $contactId, 'v' => (string) $value, 't' => $tenantId]
                );
                return ((int) $count) > 0;

            case 'contact_status':
                $contactId = (int) ($payload['contact_id'] ?? 0);
                if (!$contactId) return false;
                $status = Database::fetchColumn(
                    "SELECT status FROM contacts WHERE id = :id AND tenant_id = :t",
                    ['id' => $contactId, 't' => $tenantId]
                );
                return $status === $value;

            case 'lead_in_stage':
                $stageSlug = $payload['stage_slug'] ?? null;
                return $stageSlug === $value;

            case 'sentiment':
                return ($payload['sentiment'] ?? null) === $value;

            case 'ai_score_gte':
                return ((int) ($payload['ai_score'] ?? 0)) >= (int) $value;

            case 'ai_score_lte':
                return ((int) ($payload['ai_score'] ?? 0)) <= (int) $value;

            case 'channel_is':
                return ($payload['channel'] ?? null) === $value;

            default:
                return true;
        }
    }

    private static function isInBusinessHours(int $tenantId): bool
    {
        $row = Database::fetch("SELECT business_hours, timezone FROM tenants WHERE id = :id", ['id' => $tenantId]);
        if (!$row || empty($row['business_hours'])) return true;

        $hours = json_decode((string) $row['business_hours'], true);
        if (!is_array($hours)) return true;

        try {
            $tz = new \DateTimeZone((string) ($row['timezone'] ?: 'UTC'));
            $now = new \DateTime('now', $tz);
        } catch (\Throwable) {
            $now = new \DateTime('now');
        }
        $day = strtolower($now->format('l')); // monday, tuesday...

        $slot = $hours[$day] ?? null;
        if (!$slot || empty($slot['enabled'])) return false;

        $cur   = (int) $now->format('Hi');
        $start = (int) str_replace(':', '', (string) ($slot['start'] ?? '0900'));
        $end   = (int) str_replace(':', '', (string) ($slot['end']   ?? '1800'));
        return $cur >= $start && $cur <= $end;
    }

    // ----------- ACCIONES -----------

    private static function executeAction(array $action, array $payload, int $tenantId): array
    {
        $type   = (string) ($action['type']   ?? '');
        $params = (array)  ($action['params'] ?? []);

        switch ($type) {
            case 'send_whatsapp':
                $phone = (string) ($payload['contact_phone'] ?? $params['phone'] ?? '');
                $msg   = self::interpolate((string) ($params['message'] ?? ''), $payload);
                if ($phone === '' || $msg === '') {
                    return ['type' => $type, 'success' => false, 'reason' => 'phone/message vacios'];
                }
                $messageId = null;
                if (!empty($payload['conversation_id']) && !empty($payload['contact_id'])) {
                    $messageId = Database::insert('messages', [
                        'tenant_id'       => $tenantId,
                        'conversation_id' => (int) $payload['conversation_id'],
                        'contact_id'      => (int) $payload['contact_id'],
                        'direction'       => 'outbound',
                        'type'            => 'text',
                        'content'         => $msg,
                        'status'          => 'queued',
                    ]);
                }
                $r = (new WasapiService($tenantId))->sendTextMessage($phone, $msg);
                if ($messageId) {
                    Database::update('messages', [
                        'status'        => !empty($r['success']) ? 'sent' : 'failed',
                        'external_id'   => $r['body']['id'] ?? null,
                        'sent_at'       => !empty($r['success']) ? date('Y-m-d H:i:s') : null,
                        'error_message' => !empty($r['success']) ? null : ($r['error'] ?: 'Error envio Wasapi'),
                    ], ['id' => $messageId]);
                }
                return ['type' => $type, 'success' => !empty($r['success']), 'message_id' => $messageId, 'error' => $r['error'] ?? null];

            case 'send_email':
                $to      = (string) ($params['to'] ?? $payload['contact_email'] ?? '');
                $subject = self::interpolate((string) ($params['subject'] ?? ''), $payload);
                $body    = self::interpolate((string) ($params['body'] ?? ''), $payload);
                if ($to === '') return ['type' => $type, 'success' => false, 'reason' => 'to vacio'];
                $r = (new ResendService($tenantId))->sendEmail($to, $subject, "<p>" . nl2br(e($body)) . "</p>");
                return ['type' => $type, 'success' => !empty($r['success'])];

            case 'add_tag':
                $contactId = (int) ($payload['contact_id'] ?? 0);
                if (!$contactId) return ['type' => $type, 'success' => false];
                $tagName = (string) ($params['tag'] ?? '');
                $tag = Database::fetch(
                    "SELECT id FROM tags WHERE tenant_id = :t AND name = :n",
                    ['t' => $tenantId, 'n' => $tagName]
                );
                if (!$tag) {
                    $tagId = Database::insert('tags', [
                        'tenant_id' => $tenantId, 'name' => $tagName, 'color' => '#7C3AED',
                    ]);
                } else {
                    $tagId = (int) $tag['id'];
                }
                Database::run(
                    "INSERT IGNORE INTO contact_tags (contact_id, tag_id, tenant_id) VALUES (:c, :tg, :t)",
                    ['c' => $contactId, 'tg' => $tagId, 't' => $tenantId]
                );
                return ['type' => $type, 'success' => true, 'tag_id' => $tagId];

            case 'assign_agent':
                $convId = (int) ($payload['conversation_id'] ?? 0);
                $userId = (int) ($params['user_id'] ?? 0);
                if (!$convId || !$userId) return ['type' => $type, 'success' => false];
                Database::run(
                    "UPDATE conversations SET assigned_to = :u WHERE id = :c AND tenant_id = :t",
                    ['u' => $userId, 'c' => $convId, 't' => $tenantId]
                );
                Database::insert('conversation_assignments', [
                    'tenant_id' => $tenantId, 'conversation_id' => $convId,
                    'to_user_id' => $userId, 'action' => 'assigned',
                    'note' => 'Asignado por automatizacion',
                ]);
                return ['type' => $type, 'success' => true];

            case 'change_lead_stage':
                $leadId  = (int) ($payload['lead_id'] ?? 0);
                $stageId = (int) ($params['stage_id'] ?? 0);
                if (!$leadId || !$stageId) return ['type' => $type, 'success' => false];
                Database::run(
                    "UPDATE leads SET stage_id = :s WHERE id = :id AND tenant_id = :t",
                    ['s' => $stageId, 'id' => $leadId, 't' => $tenantId]
                );
                return ['type' => $type, 'success' => true];

            case 'create_ticket':
                $contactId = (int) ($payload['contact_id'] ?? 0);
                $subject   = self::interpolate((string) ($params['subject'] ?? 'Ticket auto'), $payload);
                $code      = 'AT-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
                Database::insert('tickets', [
                    'tenant_id'  => $tenantId,
                    'code'       => $code,
                    'contact_id' => $contactId ?: null,
                    'subject'    => $subject,
                    'description'=> self::interpolate((string) ($params['description'] ?? ''), $payload),
                    'status'     => 'open',
                    'priority'   => (string) ($params['priority'] ?? 'medium'),
                    'category'   => (string) ($params['category'] ?? 'auto'),
                    'channel'    => (string) ($payload['channel'] ?? 'whatsapp'),
                ]);
                return ['type' => $type, 'success' => true, 'code' => $code];

            case 'notify':
                $userId = (int) ($params['user_id'] ?? 0);
                if (!$userId) return ['type' => $type, 'success' => false];
                Database::insert('notifications', [
                    'tenant_id' => $tenantId,
                    'user_id'   => $userId,
                    'type'      => (string) ($params['notif_type'] ?? 'info'),
                    'title'     => self::interpolate((string) ($params['title'] ?? 'Notificacion'), $payload),
                    'body'      => self::interpolate((string) ($params['body']  ?? ''), $payload),
                    'link'      => (string) ($params['link'] ?? ''),
                    'icon'      => (string) ($params['icon'] ?? 'bell'),
                ]);
                return ['type' => $type, 'success' => true];

            case 'call_webhook':
                $url = (string) ($params['url'] ?? '');
                if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                    return ['type' => $type, 'success' => false, 'reason' => 'url invalida'];
                }
                $body = [
                    'event' => (string) ($payload['_event'] ?? ''),
                    'tenant_id' => $tenantId,
                    'payload' => $payload,
                ];
                $resp = HttpClient::post($url, $body, [
                    'Accept' => 'application/json',
                    'X-Kyros-Event' => (string) ($payload['_event'] ?? ''),
                ], 20);
                return [
                    'type' => $type,
                    'success' => !empty($resp['success']),
                    'status' => $resp['status'] ?? null,
                    'error' => $resp['error'] ?? null,
                ];

            case 'run_ai_reply':
                $convId = (int) ($payload['conversation_id'] ?? 0);
                $msg    = (string) ($payload['message_content'] ?? '');
                if (!$convId || $msg === '') return ['type' => $type, 'success' => false];
                $result = (new AiAgentService($tenantId))->autoReplyToConversation(
                    $convId,
                    (int) ($payload['contact_id'] ?? 0),
                    (string) ($payload['contact_phone'] ?? ''),
                    $msg,
                    isset($payload['entity_id']) ? (int) $payload['entity_id'] : null
                );
                return ['type' => $type] + $result;

            default:
                return ['type' => $type, 'success' => false, 'reason' => 'tipo desconocido'];
        }
    }

    /**
     * Interpolacion {{var}} sobre el payload.
     */
    private static function interpolate(string $tpl, array $payload): string
    {
        return preg_replace_callback('/\{\{([a-z0-9_\.]+)\}\}/i', function ($m) use ($payload) {
            $key = $m[1];
            return isset($payload[$key]) ? (string) $payload[$key] : $m[0];
        }, $tpl) ?? $tpl;
    }

    private static function log(array $rule, array $payload, string $status, ?string $error, array $executed, int $duration): void
    {
        try {
            Database::insert('automation_logs', [
                'tenant_id'         => (int) $rule['tenant_id'],
                'automation_id'     => (int) $rule['id'],
                'entity_type'       => (string) ($payload['entity_type'] ?? ''),
                'entity_id'         => isset($payload['entity_id']) ? (int) $payload['entity_id'] : null,
                'status'            => $status,
                'actions_executed'  => $executed ? json_encode($executed, JSON_UNESCAPED_UNICODE) : null,
                'error_message'     => $error,
                'execution_time_ms' => $duration,
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar log de automatizacion', ['msg' => $e->getMessage()]);
        }
    }
}
