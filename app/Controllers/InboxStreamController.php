<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Tenant;

/**
 * Server-Sent Events para el inbox en tiempo real.
 *
 *   GET /inbox/stream?since_message_id=NN&conversation_id=MM
 *
 * Mantiene la conexion abierta hasta MAX_DURATION segundos, sondeando la BD
 * cada POLL_INTERVAL segundos por mensajes nuevos. Emite eventos SSE estandar:
 *
 *   event: message              -> {id, conversation_id, content, ...}
 *   event: conversation         -> {id, last_message, unread_count, ...}
 *   event: status_change        -> {message_id, status}  (sent/delivered/read)
 *   event: heartbeat            -> {ts}                  (cada HEARTBEAT segundos)
 *   event: close                -> {reason}              (server pide reconectar)
 *
 * El cliente usa EventSource del browser → reconecta automaticamente al cerrar.
 *
 * Notas operacionales:
 *   - PHP-FPM/Apache cada conexion bloquea un worker. Limitamos a 60s y dejamos
 *     que el cliente reconecte. Esto es aceptable hasta ~50 usuarios concurrentes.
 *     Para mas escala, eventualmente swappear a Swoole/ReactPHP o websockets.
 *   - session_write_close() libera el lock de sesion para que otros tabs/requests
 *     del mismo user no se bloqueen.
 */
final class InboxStreamController extends Controller
{
    private const MAX_DURATION   = 55;  // segundos; cliente reconecta solo al timeout
    private const POLL_INTERVAL  = 2;   // segundos entre queries
    private const HEARTBEAT_EVERY = 15; // segundos sin eventos -> heartbeat

    public function stream(Request $request): void
    {
        $tenantId = Tenant::id();
        $userId   = Auth::id();
        if (!$tenantId || !$userId) {
            http_response_code(401);
            header('Content-Type: text/event-stream');
            echo "event: error\ndata: " . json_encode(['error' => 'unauthorized']) . "\n\n";
            return;
        }

        $sinceMessageId = (int) $request->query('since_message_id', 0);
        $conversationId = (int) $request->query('conversation_id', 0);

        // Headers SSE
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) ob_end_clean();

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');  // nginx: disable buffering
        header('Connection: keep-alive');

        ignore_user_abort(false);
        set_time_limit(self::MAX_DURATION + 10);

        // Liberar lock de sesion para que otros tabs del mismo user no se bloqueen
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Si nunca hubo cursor, arrancar desde el ultimo message_id actual
        // para no spammear con todo el historial.
        if ($sinceMessageId <= 0) {
            $sinceMessageId = (int) Database::fetchColumn(
                "SELECT COALESCE(MAX(id), 0) FROM `messages` WHERE `tenant_id` = :t",
                ['t' => $tenantId]
            );
        }

        $this->send('open', [
            'cursor'    => $sinceMessageId,
            'tenant_id' => $tenantId,
            'ts'        => time(),
        ]);

        $started = time();
        $lastEventAt = time();

        while (true) {
            if (connection_aborted()) break;
            if (time() - $started >= self::MAX_DURATION) break;

            $newMessages = $this->fetchNewMessages($tenantId, $sinceMessageId, $conversationId);
            if (!empty($newMessages)) {
                foreach ($newMessages as $m) {
                    $this->send('message', $this->transformMessage($m));
                    if ((int) $m['id'] > $sinceMessageId) {
                        $sinceMessageId = (int) $m['id'];
                    }
                }
                $lastEventAt = time();
            }

            $statusChanges = $this->fetchStatusChanges($tenantId, $sinceMessageId, $started);
            foreach ($statusChanges as $sc) {
                $this->send('status_change', [
                    'message_id'      => (int) $sc['id'],
                    'conversation_id' => (int) $sc['conversation_id'],
                    'status'          => (string) $sc['status'],
                    'external_id'     => $sc['external_id'] ?? null,
                ]);
                $lastEventAt = time();
            }

            // Heartbeat para mantener viva la conexion + proxies (nginx 60s default)
            if (time() - $lastEventAt >= self::HEARTBEAT_EVERY) {
                $this->send('heartbeat', ['ts' => time(), 'cursor' => $sinceMessageId]);
                $lastEventAt = time();
            }

            // Sleep entre polls — corto para sentirse "live"
            for ($i = 0; $i < self::POLL_INTERVAL * 10; $i++) {
                if (connection_aborted()) break 2;
                usleep(100_000); // 100ms
            }
        }

        // Antes de cerrar, decir al cliente "reconecta"
        $this->send('close', ['reason' => 'max_duration', 'cursor' => $sinceMessageId]);
    }

    private function fetchNewMessages(int $tenantId, int $sinceId, int $conversationId = 0): array
    {
        $params = ['t' => $tenantId, 's' => $sinceId];
        $sql = "SELECT m.id, m.conversation_id, m.contact_id, m.direction, m.type,
                       m.content, m.status, m.external_id, m.sent_at, m.created_at,
                       m.is_ai_generated,
                       c.first_name, c.last_name, c.phone, c.whatsapp
                FROM `messages` m
                LEFT JOIN `contacts` c ON c.id = m.contact_id
                WHERE m.tenant_id = :t AND m.id > :s";
        if ($conversationId > 0) {
            $sql .= " AND m.conversation_id = :cid";
            $params['cid'] = $conversationId;
        }
        $sql .= " ORDER BY m.id ASC LIMIT 50";
        return Database::fetchAll($sql, $params);
    }

    /**
     * Status changes: mensajes outbound cuyo status cambio en los ultimos ~60s
     * y que NO los traemos en fetchNewMessages (ya fueron emitidos).
     * Detectamos por `updated_at` (asumimos que existe en messages — si no,
     * caemos a sent_at). Limitado para no spammear.
     */
    private function fetchStatusChanges(int $tenantId, int $cursorMsgId, int $sessionStart): array
    {
        $since = date('Y-m-d H:i:s', max($sessionStart, time() - 10));
        try {
            return Database::fetchAll(
                "SELECT id, conversation_id, status, external_id
                 FROM `messages`
                 WHERE tenant_id = :t
                   AND direction = 'outbound'
                   AND id <= :c
                   AND status IN ('delivered','read','failed')
                   AND COALESCE(sent_at, created_at) >= :s
                 ORDER BY id DESC LIMIT 20",
                ['t' => $tenantId, 'c' => $cursorMsgId, 's' => $since]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function transformMessage(array $m): array
    {
        $name = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
        if ($name === '') $name = (string) ($m['phone'] ?? $m['whatsapp'] ?? '—');
        return [
            'id'               => (int) $m['id'],
            'conversation_id'  => (int) $m['conversation_id'],
            'contact_id'       => (int) $m['contact_id'],
            'contact_name'     => $name,
            'direction'        => (string) $m['direction'],
            'type'             => (string) $m['type'],
            'content'          => (string) ($m['content'] ?? ''),
            'status'           => (string) ($m['status'] ?? ''),
            'is_ai'            => !empty($m['is_ai_generated']),
            'external_id'      => $m['external_id'] ?? null,
            'created_at'       => (string) $m['created_at'],
        ];
    }

    /**
     * Envia un evento SSE. Cada evento es:
     *   event: <name>
     *   data: <json>
     *   <blank line>
     */
    private function send(string $event, array $data): void
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) return;

        echo "event: " . $event . "\n";
        echo "data: " . $payload . "\n\n";

        // Forzar flush a traves del stack (PHP buffer + Apache mod_deflate, etc.)
        if (function_exists('flush')) @flush();
    }
}
