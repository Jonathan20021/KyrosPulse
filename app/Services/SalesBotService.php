<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Sales Bot autonomo: identifica oportunidades de venta perdidas y envia
 * mensajes proactivos generados por IA. Hoy implementa:
 *   - Cart recovery: cliente armo carrito y no confirmo en X min -> recordatorio.
 *   - Re-engagement: cliente con ordenes previas, silencio N dias -> promo.
 *
 * Diseno:
 *   - El service se llama desde un endpoint /api/cron/sales-bot que el tenant
 *     dispara via cron externo (cPanel cron, GitHub Actions, etc.).
 *   - Cada accion verifica anti-spam contra `conversation_followups`.
 *   - El IA construye el mensaje personalizado usando el contexto disponible
 *     (carrito, nombre del cliente, historial de ordenes).
 *   - El gasto IA se atribuye al tenant (gracias al token-economy de Fase 1).
 */
final class SalesBotService
{
    public function __construct(private int $tenantId) {}

    /**
     * Ejecuta el ciclo completo del sales bot para el tenant: cart recovery
     * primero (mas urgente) y luego re-engagement. Retorna estadisticas para
     * que el endpoint cron pueda loguear/responder.
     *
     * @return array{cart_recovery: int, re_engagement: int, errors: int}
     */
    public function runFullCycle(): array
    {
        $stats = ['cart_recovery' => 0, 're_engagement' => 0, 'errors' => 0];

        $settings = $this->loadSettings();

        if (!empty($settings['cart_recovery_enabled'])) {
            try {
                $stats['cart_recovery'] = $this->scanCartRecovery($settings);
            } catch (\Throwable $e) {
                Logger::error('SalesBot cart_recovery fallo', ['tenant' => $this->tenantId, 'msg' => $e->getMessage()]);
                $stats['errors']++;
            }
        }

        if (!empty($settings['re_engagement_enabled'])) {
            try {
                $stats['re_engagement'] = $this->scanReEngagement($settings);
            } catch (\Throwable $e) {
                Logger::error('SalesBot re_engagement fallo', ['tenant' => $this->tenantId, 'msg' => $e->getMessage()]);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Cart recovery: encuentra conversaciones con carrito armado, ultimo
     * mensaje inbound hace > N minutos, sin orden creada, y manda un nudge.
     */
    public function scanCartRecovery(array $settings): int
    {
        $waitMin     = max(15, (int) ($settings['cart_recovery_min'] ?? 120));
        $maxBatch    = max(1, (int) ($settings['cart_recovery_batch'] ?? 20));
        $maxAgeHours = 48; // no recuperar carritos de mas de 48h (cliente probablemente perdido)

        $candidates = Database::fetchAll(
            "SELECT c.id, c.contact_id, c.cart_state, c.last_inbound_at,
                    co.name AS contact_name, co.phone AS contact_phone,
                    c.channel_id
             FROM conversations c
             INNER JOIN contacts co ON co.id = c.contact_id AND co.tenant_id = c.tenant_id
             WHERE c.tenant_id = :t
               AND c.cart_state IS NOT NULL
               AND c.cart_state <> ''
               AND c.cart_state <> '{}'
               AND c.last_inbound_at IS NOT NULL
               AND c.last_inbound_at <= DATE_SUB(NOW(), INTERVAL :wait MINUTE)
               AND c.last_inbound_at >= DATE_SUB(NOW(), INTERVAL $maxAgeHours HOUR)
               AND co.deleted_at IS NULL
               AND co.status <> 'blocked'
               AND NOT EXISTS (
                   SELECT 1 FROM orders o
                   WHERE o.conversation_id = c.id
                     AND o.created_at > c.last_inbound_at
                     AND o.status <> 'cancelled'
               )
             ORDER BY c.last_inbound_at ASC
             LIMIT $maxBatch",
            ['t' => $this->tenantId, 'wait' => $waitMin]
        );

        $sent = 0;
        foreach ($candidates as $row) {
            try {
                if ($this->sendCartRecovery($row)) $sent++;
            } catch (\Throwable $e) {
                Logger::warning('CartRecovery item fallo', ['conv' => $row['id'], 'msg' => $e->getMessage()]);
            }
        }
        return $sent;
    }

    private function sendCartRecovery(array $row): bool
    {
        $convId    = (int) $row['id'];
        $contactId = (int) $row['contact_id'];
        $phone     = (string) ($row['contact_phone'] ?? '');
        $name      = (string) ($row['contact_name'] ?? 'cliente');

        if ($phone === '') return false;

        $cart = json_decode((string) $row['cart_state'], true);
        if (!is_array($cart) || empty($cart['items'])) return false;

        $signature = $this->cartSignature($cart);

        // Anti-spam: no enviar dos cart-recoveries para el mismo carrito ni mas
        // de 1 cart-recovery por conversacion en 24h.
        $alreadySent = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM conversation_followups
             WHERE tenant_id = :t AND conversation_id = :c AND kind = 'cart_recovery'
               AND (cart_signature = :sig OR created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))",
            ['t' => $this->tenantId, 'c' => $convId, 'sig' => $signature]
        );
        if ($alreadySent > 0) return false;

        // Construir mensaje con IA. Si la IA no esta disponible, fallback a
        // un mensaje generico (mejor enviar algo que nada).
        $message = $this->buildCartRecoveryMessage($name, $cart, $convId);
        if ($message === '') return false;

        // Persistir el mensaje en la conversacion como outbound de IA
        $messageId = Database::insert('messages', [
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $convId,
            'contact_id'      => $contactId,
            'channel_id'      => $row['channel_id'] ? (int) $row['channel_id'] : null,
            'direction'       => 'outbound',
            'type'            => 'text',
            'content'         => $message,
            'is_ai_generated' => 1,
            'status'          => 'queued',
            'metadata'        => json_encode(['proactive' => 'cart_recovery'], JSON_UNESCAPED_UNICODE),
        ]);

        $send = (new ChannelDispatcher($this->tenantId))->sendText(
            $phone,
            $message,
            $row['channel_id'] ? (int) $row['channel_id'] : null
        );

        $ok = !empty($send['success']);
        Database::update('messages', [
            'status'      => $ok ? 'sent' : 'failed',
            'external_id' => $send['body']['id'] ?? null,
            'sent_at'     => $ok ? date('Y-m-d H:i:s') : null,
            'error_message' => $ok ? null : (string) ($send['error'] ?? 'send fail'),
        ], ['id' => $messageId]);

        Database::insert('conversation_followups', [
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $convId,
            'contact_id'      => $contactId,
            'kind'            => 'cart_recovery',
            'cart_signature'  => $signature,
            'message'         => mb_substr($message, 0, 1000),
            'status'          => $ok ? 'sent' : 'failed',
            'error_message'   => $ok ? null : mb_substr((string) ($send['error'] ?? ''), 0, 250),
        ]);

        if ($ok) {
            Database::run(
                "UPDATE conversations SET last_outbound_at = NOW() WHERE id = :id",
                ['id' => $convId]
            );
        }

        return $ok;
    }

    private function buildCartRecoveryMessage(string $name, array $cart, int $convId): string
    {
        $items = $cart['items'] ?? [];
        $itemsList = [];
        foreach ($items as $it) {
            $qty  = (int) ($it['qty'] ?? 1);
            $nm   = (string) ($it['name'] ?? 'producto');
            $itemsList[] = "$qty x $nm";
        }
        $itemsText = implode(', ', $itemsList);

        // Intentar via IA para que sea personalizado y warm
        $provider = new AiProviderService($this->tenantId);
        $provider->withContext(['conversation_id' => $convId]);
        $ai = $provider->call(
            'cart_recovery',
            "El cliente $name armo este pedido en el menu pero no lo confirmo: $itemsText.\n\n"
          . "Escribe UN SOLO mensaje de WhatsApp breve (max 280 caracteres), calido y directo, en espanol, para retomar la conversacion y cerrar la venta. NO uses markdown, NO uses listas. NO emojis excesivos (max 1-2). NO firmes con tu nombre. NO incluyas placeholders. Muestra los items concretos.",
            300,
            false
        );
        $text = trim((string) ($ai['text'] ?? ''));
        if (!empty($ai['success']) && $text !== '') {
            return $text;
        }

        // Fallback determinista (la IA fallo o no esta configurada)
        $shortName = trim(explode(' ', $name)[0] ?? $name);
        $first = $itemsList[0] ?? '';
        $extra = count($itemsList) > 1 ? ' y mas' : '';
        return sprintf(
            "Hola %s! Vi que armaste tu pedido (%s%s) pero no llegamos a confirmarlo. ¿Lo cerramos? Te lo dejamos en camino apenas me digas.",
            $shortName,
            $first,
            $extra
        );
    }

    /**
     * Re-engagement: clientes con al menos 1 orden previa y silencio > N dias.
     * Anti-spam: no enviar mas de 1 re-engagement cada 30 dias por contacto.
     */
    public function scanReEngagement(array $settings): int
    {
        $silentDays  = max(3, (int) ($settings['re_engagement_days'] ?? 14));
        $maxBatch    = max(1, (int) ($settings['re_engagement_batch'] ?? 10));
        $cooldownDays = 30;

        $candidates = Database::fetchAll(
            "SELECT co.id AS contact_id, co.name, co.phone,
                    MAX(o.created_at) AS last_order_at,
                    SUM(o.total) AS total_spend,
                    COUNT(o.id) AS order_count,
                    (SELECT id FROM conversations c2
                       WHERE c2.contact_id = co.id AND c2.tenant_id = co.tenant_id
                       ORDER BY c2.id DESC LIMIT 1) AS conv_id,
                    (SELECT channel_id FROM conversations c3
                       WHERE c3.contact_id = co.id AND c3.tenant_id = co.tenant_id
                       ORDER BY c3.id DESC LIMIT 1) AS channel_id
             FROM contacts co
             INNER JOIN orders o ON o.contact_id = co.id AND o.tenant_id = co.tenant_id
             WHERE co.tenant_id = :t
               AND co.deleted_at IS NULL
               AND co.status <> 'blocked'
               AND co.phone IS NOT NULL AND co.phone <> ''
               AND o.status NOT IN ('cancelled')
             GROUP BY co.id
             HAVING last_order_at <= DATE_SUB(NOW(), INTERVAL :silent DAY)
                AND NOT EXISTS (
                    SELECT 1 FROM conversation_followups cf
                    WHERE cf.tenant_id = co.tenant_id
                      AND cf.contact_id = co.id
                      AND cf.kind = 're_engagement'
                      AND cf.created_at >= DATE_SUB(NOW(), INTERVAL $cooldownDays DAY)
                )
             ORDER BY last_order_at ASC
             LIMIT $maxBatch",
            ['t' => $this->tenantId, 'silent' => $silentDays]
        );

        $sent = 0;
        foreach ($candidates as $row) {
            try {
                if ($this->sendReEngagement($row, $silentDays)) $sent++;
            } catch (\Throwable $e) {
                Logger::warning('ReEngagement item fallo', ['contact' => $row['contact_id'], 'msg' => $e->getMessage()]);
            }
        }
        return $sent;
    }

    private function sendReEngagement(array $row, int $silentDays): bool
    {
        $contactId = (int) $row['contact_id'];
        $phone     = (string) $row['phone'];
        $name      = (string) ($row['name'] ?? 'cliente');
        $convId    = !empty($row['conv_id']) ? (int) $row['conv_id'] : null;
        $channelId = !empty($row['channel_id']) ? (int) $row['channel_id'] : null;
        $orderCount= (int) ($row['order_count'] ?? 0);
        $totalSpend= (float) ($row['total_spend'] ?? 0);
        $lastOrder = (string) ($row['last_order_at'] ?? '');

        // Top items historicos del cliente (para que la IA sugiera lo que ya le gusta)
        $favItems = Database::fetchAll(
            "SELECT oi.name, COUNT(*) AS times
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
             WHERE oi.tenant_id = :t AND o.contact_id = :c
             GROUP BY oi.name
             ORDER BY times DESC
             LIMIT 3",
            ['t' => $this->tenantId, 'c' => $contactId]
        );
        $favList = array_map(fn($f) => (string) $f['name'], $favItems);

        $message = $this->buildReEngagementMessage($name, $silentDays, $favList, $orderCount, $totalSpend, $convId);
        if ($message === '') return false;

        // Persistir como outbound IA (si hay conversacion previa) o crear una nueva
        if ($convId) {
            $messageId = Database::insert('messages', [
                'tenant_id'       => $this->tenantId,
                'conversation_id' => $convId,
                'contact_id'      => $contactId,
                'channel_id'      => $channelId,
                'direction'       => 'outbound',
                'type'            => 'text',
                'content'         => $message,
                'is_ai_generated' => 1,
                'status'          => 'queued',
                'metadata'        => json_encode(['proactive' => 're_engagement', 'silent_days' => $silentDays], JSON_UNESCAPED_UNICODE),
            ]);
        } else {
            $messageId = null;
        }

        $send = (new ChannelDispatcher($this->tenantId))->sendText($phone, $message, $channelId);
        $ok = !empty($send['success']);

        if ($messageId) {
            Database::update('messages', [
                'status'        => $ok ? 'sent' : 'failed',
                'external_id'   => $send['body']['id'] ?? null,
                'sent_at'       => $ok ? date('Y-m-d H:i:s') : null,
                'error_message' => $ok ? null : (string) ($send['error'] ?? 'send fail'),
            ], ['id' => $messageId]);
        }

        Database::insert('conversation_followups', [
            'tenant_id'       => $this->tenantId,
            'conversation_id' => $convId,
            'contact_id'      => $contactId,
            'kind'            => 're_engagement',
            'cart_signature'  => null,
            'message'         => mb_substr($message, 0, 1000),
            'status'          => $ok ? 'sent' : 'failed',
            'error_message'   => $ok ? null : mb_substr((string) ($send['error'] ?? ''), 0, 250),
        ]);

        if ($ok && $convId) {
            Database::run(
                "UPDATE conversations SET last_outbound_at = NOW() WHERE id = :id",
                ['id' => $convId]
            );
        }

        return $ok;
    }

    private function buildReEngagementMessage(string $name, int $silentDays, array $favItems, int $orderCount, float $totalSpend, ?int $convId): string
    {
        $favText = !empty($favItems) ? implode(', ', $favItems) : '';
        $context = "Cliente $name lleva $silentDays dias sin pedir. Historial: $orderCount ordenes previas, total gastado \$" . number_format($totalSpend, 2) . ".";
        if ($favText !== '') {
            $context .= " Items favoritos historicos: $favText.";
        }

        $provider = new AiProviderService($this->tenantId);
        if ($convId) $provider->withContext(['conversation_id' => $convId]);
        $ai = $provider->call(
            're_engagement',
            $context . "\n\n"
          . "Escribe UN SOLO mensaje breve de WhatsApp (max 280 caracteres), calido pero directo, en espanol, para reenganchar al cliente y traerlo de vuelta. Mencionale uno de sus favoritos si tiene sentido. NO uses markdown ni listas, NO firmes con tu nombre, max 1-2 emojis.",
            300,
            false
        );
        $text = trim((string) ($ai['text'] ?? ''));
        if (!empty($ai['success']) && $text !== '') {
            return $text;
        }

        // Fallback
        $shortName = trim(explode(' ', $name)[0] ?? $name);
        $favHook = !empty($favItems) ? " Vi que te gustan " . $favItems[0] . " — los tenemos listos." : '';
        return sprintf(
            "Hola %s! Hace tiempo no nos pides.%s ¿Te tiramos algo rico hoy?",
            $shortName,
            $favHook
        );
    }

    /**
     * Hash determinista del carrito (items + datos cliente) para detectar
     * "mismo carrito" entre runs del worker y evitar nudges duplicados.
     */
    private function cartSignature(array $cart): string
    {
        $items = $cart['items'] ?? [];
        $normalized = [];
        foreach ($items as $it) {
            $normalized[] = [
                'name' => mb_strtolower((string) ($it['name'] ?? '')),
                'qty'  => (int) ($it['qty'] ?? 1),
            ];
        }
        usort($normalized, fn($a, $b) => strcmp($a['name'], $b['name']));
        return md5(json_encode($normalized, JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function loadSettings(): array
    {
        $tenant = Database::fetch(
            "SELECT restaurant_settings FROM tenants WHERE id = :t",
            ['t' => $this->tenantId]
        );
        $stored = !empty($tenant['restaurant_settings'])
            ? (json_decode((string) $tenant['restaurant_settings'], true) ?: [])
            : [];

        // Defaults conservadores. El tenant los sobreescribe en /settings/restaurant.
        return array_merge([
            'cart_recovery_enabled'  => true,
            'cart_recovery_min'      => 120,    // 2h
            'cart_recovery_batch'    => 20,
            're_engagement_enabled'  => false,  // opt-in
            're_engagement_days'     => 14,
            're_engagement_batch'    => 10,
        ], $stored);
    }
}
