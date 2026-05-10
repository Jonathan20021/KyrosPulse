<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\WebhookEndpoint;

/**
 * Despachador de webhooks salientes con HMAC.
 *
 * Flujo:
 *   1. Sistema dispara un evento (order.created, agent.run.completed, etc).
 *   2. AutomationEngine listener wildcard llama a WebhookDispatcher::dispatch().
 *   3. Por cada endpoint suscrito y activo, encolamos un row en webhook_deliveries.
 *   4. Intentamos entrega inmediata (sincrona, timeout corto). Si falla, queda
 *      en estado 'pending' con next_retry_at calculado por backoff exponencial.
 *   5. Cron worker (cron/webhooks_retry.php) reintenta hasta 5 veces y marca
 *      'dead' los que fallen mas alla del ultimo intento.
 *
 * Firma HMAC SHA-256:
 *   header X-Kyros-Signature: t=<unix>,v1=<hex>
 *   donde hex = hash_hmac('sha256', "<unix>." . body_json, $secret)
 *
 * El receptor debe:
 *   1. Verificar |t - now| < 5 minutos (defensa contra replay)
 *   2. Recalcular hex y comparar con hash_equals()
 */
final class WebhookDispatcher
{
    /** Backoff: 1m, 5m, 30m, 2h, 12h. attempt_index 0..4. */
    private const BACKOFF_SECONDS = [60, 300, 1800, 7200, 43200];
    private const MAX_ATTEMPTS    = 5;

    /**
     * Eventos del sistema que SI viajan a webhooks externos. Filtramos los
     * internos para no spammear (ej. AutomationEngine logs).
     */
    private const PUBLIC_EVENTS = [
        'order.created', 'order.status_changed', 'order.cancelled', 'order.delivered',
        'agent.run.completed', 'agent.run.failed',
        'contact.created', 'contact.updated',
        'message.received', 'message.sent',
        'conversation.opened', 'conversation.closed',
        'lead.stage_changed',
        'ticket.created', 'ticket.updated',
    ];

    public static function isPublicEvent(string $event): bool
    {
        return in_array($event, self::PUBLIC_EVENTS, true);
    }

    /**
     * Despacha un evento a todos los endpoints suscritos. Llamado desde el
     * listener wildcard de Events.
     */
    public static function dispatch(string $event, array $payload): void
    {
        if (!self::isPublicEvent($event)) return;
        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        if ($tenantId === 0) return;

        try {
            $endpoints = WebhookEndpoint::listSubscribed($tenantId, $event);
        } catch (\Throwable $e) {
            Logger::error('WebhookDispatcher: no se pudo cargar endpoints', ['msg' => $e->getMessage()]);
            return;
        }

        if (empty($endpoints)) return;

        // Limpiamos campos internos del payload antes de serializar
        $clean = $payload;
        unset($clean['_event']);
        $body = [
            'event'     => $event,
            'tenant_id' => $tenantId,
            'data'      => $clean,
            'timestamp' => time(),
        ];

        foreach ($endpoints as $ep) {
            self::queueAndDeliver($ep, $event, $body);
        }
    }

    /**
     * Inserta un delivery y trata de entregarlo de inmediato. Si falla,
     * queda en cola para retry.
     */
    public static function queueAndDeliver(array $endpoint, string $event, array $body): int
    {
        $uuid    = self::uuid4();
        $bodyStr = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sig     = self::sign($bodyStr, (string) $endpoint['secret']);

        $deliveryId = Database::insert('webhook_deliveries', [
            'uuid'        => $uuid,
            'endpoint_id' => (int) $endpoint['id'],
            'tenant_id'   => (int) $endpoint['tenant_id'],
            'event'       => substr($event, 0, 80),
            'payload'     => $bodyStr,
            'signature'   => $sig,
            'status'      => 'pending',
            'attempts'    => 0,
        ]);

        // Intento inmediato (sincrono, timeout corto). El cron retoma si falla.
        self::attemptDelivery($deliveryId);

        return $deliveryId;
    }

    /**
     * Intenta entregar un delivery especifico. Actualiza estado y agenda
     * proximo retry si falla. Devuelve true si quedo entregado.
     */
    public static function attemptDelivery(int $deliveryId): bool
    {
        $d = Database::fetch(
            "SELECT d.*, e.url, e.secret, e.headers
             FROM `webhook_deliveries` d
             INNER JOIN `webhook_endpoints` e ON e.id = d.endpoint_id
             WHERE d.id = :i LIMIT 1",
            ['i' => $deliveryId]
        );
        if (!$d) return false;
        if (in_array($d['status'], ['delivered','dead'], true)) return $d['status'] === 'delivered';

        $url = (string) $d['url'];
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            self::markDead($deliveryId, 'Invalid endpoint URL');
            WebhookEndpoint::recordDeliveryResult((int) $d['endpoint_id'], false, null, 'Invalid URL');
            return false;
        }

        $bodyStr = (string) $d['payload'];
        $sig     = (string) ($d['signature'] ?: self::sign($bodyStr, (string) $d['secret']));

        $headers = [
            'Content-Type'        => 'application/json',
            'User-Agent'          => 'KyrosPulse-Webhook/1.0',
            'X-Kyros-Event'       => (string) $d['event'],
            'X-Kyros-Delivery-Id' => (string) $d['uuid'],
            'X-Kyros-Signature'   => $sig,
            'X-Kyros-Timestamp'   => (string) time(),
        ];

        // Headers custom del endpoint
        $custom = $d['headers'] ? json_decode((string) $d['headers'], true) : null;
        if (is_array($custom)) {
            foreach ($custom as $k => $v) {
                if (is_string($k) && is_scalar($v)) {
                    // Bloquear sobrescribir headers de seguridad
                    if (stripos($k, 'x-kyros-') === 0) continue;
                    $headers[$k] = (string) $v;
                }
            }
        }

        $start = microtime(true);
        $resp = HttpClient::request('POST', $url, $headers, $bodyStr, 15);
        $latency = (int) round((microtime(true) - $start) * 1000);

        $code      = (int) ($resp['status'] ?? 0);
        $bodySnip  = is_string($resp['raw'] ?? null) ? mb_substr((string) $resp['raw'], 0, 4000) : null;
        $error     = (string) ($resp['error'] ?? '');
        $success   = $code >= 200 && $code < 300;
        $attempts  = (int) $d['attempts'] + 1;

        if ($success) {
            Database::update('webhook_deliveries', [
                'status'        => 'delivered',
                'attempts'      => $attempts,
                'response_code' => $code,
                'response_body' => $bodySnip,
                'latency_ms'    => $latency,
                'error'         => null,
                'delivered_at'  => date('Y-m-d H:i:s'),
                'next_retry_at' => null,
            ], ['id' => $deliveryId]);
            WebhookEndpoint::recordDeliveryResult((int) $d['endpoint_id'], true, $code, null);
            return true;
        }

        // Fallo: 4xx que no sean rate-limit/auth se consideran "dead" sin retry
        // (el receptor rechaza permanentemente). 5xx y errores de red reintentan.
        $isPermanent = $code >= 400 && $code < 500 && $code !== 408 && $code !== 429;

        $patch = [
            'attempts'      => $attempts,
            'response_code' => $code ?: null,
            'response_body' => $bodySnip,
            'latency_ms'    => $latency,
            'error'         => mb_substr($error !== '' ? $error : ('HTTP ' . $code), 0, 500),
        ];

        if ($isPermanent || $attempts >= self::MAX_ATTEMPTS) {
            $patch['status']        = 'dead';
            $patch['next_retry_at'] = null;
        } else {
            $patch['status']        = 'pending';
            $delay = self::BACKOFF_SECONDS[min($attempts - 1, count(self::BACKOFF_SECONDS) - 1)];
            $patch['next_retry_at'] = date('Y-m-d H:i:s', time() + $delay);
        }

        Database::update('webhook_deliveries', $patch, ['id' => $deliveryId]);
        WebhookEndpoint::recordDeliveryResult((int) $d['endpoint_id'], false, $code, $patch['error']);
        return false;
    }

    /**
     * Worker: procesa todos los deliveries pendientes que ya cumplieron next_retry_at.
     * Llamado desde cron/webhooks_retry.php cada minuto.
     */
    public static function processRetries(int $maxBatch = 100): array
    {
        $rows = Database::fetchAll(
            "SELECT id FROM `webhook_deliveries`
             WHERE `status` = 'pending'
               AND (`next_retry_at` IS NULL OR `next_retry_at` <= NOW())
             ORDER BY `id` ASC
             LIMIT $maxBatch"
        );

        $delivered = 0; $failed = 0;
        foreach ($rows as $r) {
            $ok = self::attemptDelivery((int) $r['id']);
            if ($ok) $delivered++; else $failed++;
        }

        return [
            'processed' => count($rows),
            'delivered' => $delivered,
            'failed'    => $failed,
        ];
    }

    /** Permite reintentar manualmente un delivery muerto desde la UI. */
    public static function replay(int $tenantId, string $uuid): ?array
    {
        $d = WebhookEndpoint::findDeliveryByUuid($tenantId, $uuid);
        if (!$d) return null;

        Database::update('webhook_deliveries', [
            'status'        => 'pending',
            'attempts'      => 0,
            'next_retry_at' => null,
            'error'         => null,
            'response_body' => null,
            'response_code' => null,
        ], ['id' => (int) $d['id']]);

        $ok = self::attemptDelivery((int) $d['id']);
        return [
            'delivered' => $ok,
            'delivery_id' => (int) $d['id'],
        ];
    }

    public static function sign(string $body, string $secret): string
    {
        $ts = time();
        $hex = hash_hmac('sha256', $ts . '.' . $body, $secret);
        return 't=' . $ts . ',v1=' . $hex;
    }

    public static function generateSecret(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $aLen = strlen($alphabet) - 1;
        $out = 'whsec_';
        for ($i = 0; $i < 40; $i++) {
            $out .= $alphabet[random_int(0, $aLen)];
        }
        return $out;
    }

    private static function markDead(int $deliveryId, string $reason): void
    {
        Database::update('webhook_deliveries', [
            'status' => 'dead',
            'error'  => mb_substr($reason, 0, 500),
        ], ['id' => $deliveryId]);
    }

    private static function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
