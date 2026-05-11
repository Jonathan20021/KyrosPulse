<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Models\NotificationDestination;

/**
 * Dispatcher central para enviar notificaciones a destinos configurables.
 *
 * Uso tipico:
 *   (new NotificationDispatcher($tenantId))->dispatchOrderEvent($order, 'order.ready');
 *
 * Soporta drivers:
 *  - email      via ResendService
 *  - slack      via incoming webhook
 *  - discord    via incoming webhook
 *  - teams      via incoming webhook (Microsoft Teams)
 *  - telegram   via Bot API (sendMessage)
 *  - webhook    POST JSON a URL custom (con HMAC opcional)
 *  - whatsapp   reutiliza el primer canal WA activo del tenant via ChannelDispatcher
 *
 * Cada destino registra resultado en notification_logs y actualiza success/failure
 * count en notification_destinations.
 */
final class NotificationDispatcher
{
    public function __construct(private int $tenantId) {}

    /**
     * Dispara un evento de orden hacia todos los destinos suscritos.
     * No bloquea el flujo: errores se loguean, no se relanzan.
     */
    public function dispatchOrderEvent(array $order, string $event): void
    {
        $destinations = NotificationDestination::activeForEvent($this->tenantId, $event);
        if (empty($destinations)) return;

        $payload = $this->buildOrderPayload($order, $event);

        foreach ($destinations as $dest) {
            $this->sendToDestination($dest, $event, $payload, 'order', (int) $order['id']);
        }
    }

    /**
     * Dispara una alerta de presupuesto IA (token economy) a destinos suscritos
     * al evento `ai.budget_alert`. Se invoca desde AiProviderService cuando el
     * uso del periodo cruza el umbral configurado por el tenant (default 80%).
     *
     * $info: ['pct' => int, 'threshold' => int, 'usd_used' => float,
     *        'usd_budget' => float, 'period_start' => 'YYYY-MM-01']
     */
    public function dispatchAiBudgetAlert(array $info): void
    {
        $event = 'ai.budget_alert';
        $destinations = NotificationDestination::activeForEvent($this->tenantId, $event);
        if (empty($destinations)) return;

        $tenant = Database::fetch(
            "SELECT name FROM tenants WHERE id = :t",
            ['t' => $this->tenantId]
        );
        $brand = (string) ($tenant['name'] ?? 'Tu cuenta');

        $pct        = (int) ($info['pct'] ?? 0);
        $threshold  = (int) ($info['threshold'] ?? 80);
        $usdUsed    = (float) ($info['usd_used'] ?? 0);
        $usdBudget  = (float) ($info['usd_budget'] ?? 0);
        $remaining  = max(0.0, $usdBudget - $usdUsed);

        $title    = sprintf('⚠️ Presupuesto IA al %d%% — %s', $pct, $brand);
        $subject  = sprintf('[Evallish Pulse] Alerta presupuesto IA · %s al %d%%', $brand, $pct);
        $textBody = sprintf(
            "Tu uso de IA en este periodo cruzo el umbral del %d%%.\n\n"
          . "• Gastado: $%s de $%s\n"
          . "• Restante: $%s\n"
          . "• Periodo desde: %s\n\n"
          . "Si llegas al 100%% el agente IA se pausara hasta el proximo mes o hasta que aumentes el budget.\n"
          . "Ajustalo en Configuracion > IA.",
            $threshold,
            number_format($usdUsed, 2),
            number_format($usdBudget, 2),
            number_format($remaining, 2),
            (string) ($info['period_start'] ?? date('Y-m-01'))
        );

        $baseUrl = rtrim((string) (\App\Core\Config::get('app.url', '')), '/');
        $usageUrl = ($baseUrl !== '' ? $baseUrl : 'https://pulse.kyrosrd.com') . '/ai/usage';

        $htmlBody = '<p><strong>Tu uso de IA cruzo el ' . $threshold . '% del budget mensual.</strong></p>'
            . '<ul>'
            . '<li>Gastado: <strong>$' . number_format($usdUsed, 2) . '</strong> de $' . number_format($usdBudget, 2) . '</li>'
            . '<li>Restante: <strong>$' . number_format($remaining, 2) . '</strong></li>'
            . '<li>Periodo desde: ' . htmlspecialchars((string) ($info['period_start'] ?? date('Y-m-01')), ENT_QUOTES) . '</li>'
            . '</ul>'
            . '<p>Si llegas al 100% el agente IA se pausara hasta el proximo mes o hasta que aumentes el budget.</p>'
            . '<p><a href="' . htmlspecialchars($usageUrl, ENT_QUOTES) . '" style="display:inline-block;padding:10px 20px;background:#0EA572;color:#fff;border-radius:8px;text-decoration:none">Ver uso de IA</a></p>';

        $payload = [
            'subject' => $subject,
            'title'   => $title,
            'text'    => $textBody,
            'html'    => $htmlBody,
            'fields'  => [
                ['label' => 'Uso',         'value' => $pct . '%'],
                ['label' => 'Gastado',     'value' => '$' . number_format($usdUsed, 2)],
                ['label' => 'Budget',      'value' => '$' . number_format($usdBudget, 2)],
                ['label' => 'Restante',    'value' => '$' . number_format($remaining, 2)],
                ['label' => 'Umbral',      'value' => $threshold . '%'],
                ['label' => 'Periodo',     'value' => (string) ($info['period_start'] ?? date('Y-m-01'))],
            ],
            'event'   => $event,
        ];

        foreach ($destinations as $dest) {
            $this->sendToDestination($dest, $event, $payload, 'ai_budget', $this->tenantId);
        }
    }

    /**
     * Envia un mensaje de prueba a un destino concreto. Lo usa el boton "Probar"
     * en la UI para validar que la config funciona antes de guardar.
     */
    public function testDestination(array $destination): array
    {
        $payload = [
            'subject' => '[Prueba] Notificacion de Evallish Pulse',
            'title'   => '✅ Conexion correcta',
            'text'    => 'Este es un mensaje de prueba enviado desde Evallish Pulse. Si lo ves, la integracion funciona.',
            'html'    => '<p>Este es un mensaje de prueba enviado desde <strong>Evallish Pulse</strong>. Si lo ves, la integracion funciona.</p>',
            'fields'  => [
                ['label' => 'Tenant',    'value' => (string) $this->tenantId],
                ['label' => 'Tipo',      'value' => (string) $destination['type']],
                ['label' => 'Timestamp', 'value' => date('Y-m-d H:i:s')],
            ],
            'event'   => 'test.ping',
        ];
        return $this->sendToDestination($destination, 'test.ping', $payload, 'system', null);
    }

    // ============================================================
    //  Internals
    // ============================================================

    private function sendToDestination(array $dest, string $event, array $payload, string $entityType, ?int $entityId): array
    {
        $type = (string) ($dest['type'] ?? '');
        $config = is_array($dest['config']) ? $dest['config'] : (json_decode((string) $dest['config'], true) ?: []);

        try {
            $result = match ($type) {
                'email'    => $this->sendEmail($config, $payload),
                'slack'    => $this->sendSlack($config, $payload),
                'discord'  => $this->sendDiscord($config, $payload),
                'teams'    => $this->sendTeams($config, $payload),
                'telegram' => $this->sendTelegram($config, $payload),
                'webhook'  => $this->sendWebhook($config, $payload, $event),
                'whatsapp' => $this->sendWhatsapp($config, $payload),
                default    => ['success' => false, 'error' => 'Tipo de destino desconocido: ' . $type],
            };
        } catch (\Throwable $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        }

        $success = !empty($result['success']);
        $this->log($dest, $event, $entityType, $entityId, $payload, $result);

        if (!empty($dest['id'])) {
            NotificationDestination::recordResult((int) $dest['id'], $success, $result['error'] ?? null);
        }

        return $result;
    }

    private function log(array $dest, string $event, string $entityType, ?int $entityId, array $payload, array $result): void
    {
        try {
            Database::insert('notification_logs', [
                'tenant_id'      => $this->tenantId,
                'destination_id' => $dest['id'] ?? null,
                'type'           => (string) ($dest['type'] ?? ''),
                'event'          => $event,
                'entity_type'    => $entityType,
                'entity_id'      => $entityId,
                'status'         => !empty($result['success']) ? 'success' : 'failed',
                'payload'        => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'response'       => isset($result['response']) ? mb_substr((string) $result['response'], 0, 4000) : null,
                'error'          => isset($result['error']) ? mb_substr((string) $result['error'], 0, 1000) : null,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('notification_log_insert_failed', ['err' => $e->getMessage()]);
        }
    }

    private function buildOrderPayload(array $order, string $event): array
    {
        $statusMeta = [
            'order.new'              => ['Nueva orden',         '🆕', '#06B6D4'],
            'order.confirmed'        => ['Orden confirmada',    '✅', '#0EA572'],
            'order.preparing'        => ['En preparacion',      '👨‍🍳', '#F59E0B'],
            'order.ready'            => ['Lista para entregar', '🛎️', '#0EA572'],
            'order.out_for_delivery' => ['En camino',           '🛵', '#0EA5E9'],
            'order.delivered'        => ['Entregada',           '🎉', '#0B7C56'],
            'order.cancelled'        => ['Cancelada',           '❌', '#DC2A47'],
        ];
        $orderId  = (int) ($order['id'] ?? 0);
        $code     = (string) ($order['code'] ?? ('#' . $orderId));
        $customer = trim((string) ($order['customer_name'] ?? ($order['contact_name'] ?? 'Cliente')));
        $phone    = (string) ($order['customer_phone'] ?? ($order['contact_phone'] ?? ''));
        $total    = number_format((float) ($order['total'] ?? 0), 2);
        $currency = (string) ($order['currency'] ?? 'USD');
        $subtotal = (float) ($order['subtotal'] ?? 0);
        $delivery = (float) ($order['delivery_fee'] ?? 0);
        $tax      = (float) ($order['tax'] ?? 0);
        $address  = (string) ($order['delivery_address'] ?? '');
        $notes    = (string) ($order['delivery_notes'] ?? ($order['kitchen_notes'] ?? ''));
        $deliveryType = (string) ($order['delivery_type'] ?? 'delivery');
        [$statusLb, $emoji, $color] = $statusMeta[$event] ?? [ucfirst((string) ($order['status'] ?? '')), 'ℹ️', '#0EA572'];
        $items    = $order['items'] ?? [];

        // URL absoluta a la orden en el panel del SaaS
        $baseUrl  = rtrim((string) (\App\Core\Config::get('app.url', '')), '/');
        $orderUrl = $baseUrl . '/orders/' . $orderId;

        // Versión texto plano (Slack/Telegram fallback + email plain)
        $itemsTextLines = [];
        foreach ($items as $it) {
            $line = sprintf('  • %dx %s', (int) ($it['qty'] ?? 1), (string) ($it['name'] ?? 'Producto'));
            if (!empty($it['notes'])) $line .= ' (' . $it['notes'] . ')';
            $line .= ' — ' . $currency . ' ' . number_format((float) ($it['subtotal'] ?? 0), 2);
            $itemsTextLines[] = $line;
        }
        $itemsText = empty($itemsTextLines) ? '  (sin items)' : implode("\n", $itemsTextLines);

        $taxRateForLabel = \App\Models\Order::tenantTaxRate($this->tenantId);
        $taxLabelText    = $taxRateForLabel > 0
            ? 'ITBIS (' . rtrim(rtrim(number_format($taxRateForLabel, 2, '.', ''), '0'), '.') . '%)'
            : 'ITBIS';
        $totalsTextLines = [];
        if ($subtotal > 0) $totalsTextLines[] = sprintf('Subtotal: %s %s', $currency, number_format($subtotal, 2));
        if ($delivery > 0) $totalsTextLines[] = sprintf('Delivery: %s %s', $currency, number_format($delivery, 2));
        if ($tax > 0)      $totalsTextLines[] = sprintf('%s: %s %s', $taxLabelText, $currency, number_format($tax, 2));
        $totalsText = empty($totalsTextLines) ? '' : implode("\n", $totalsTextLines) . "\n";

        $title = sprintf('%s %s · %s', $emoji, $statusLb, $code);
        $text  = "{$title}\n\n"
               . "Cliente: {$customer}\n"
               . "Telefono: {$phone}\n"
               . ($address ? "Direccion: {$address}\n" : '')
               . ($notes ? "Notas: {$notes}\n" : '')
               . "\nItems:\n{$itemsText}\n\n"
               . $totalsText
               . "TOTAL: {$currency} {$total}\n\n"
               . "Ver orden: {$orderUrl}";

        // ============================================================
        //  Email HTML profesional (responsive, table-based para Outlook)
        // ============================================================
        $itemsRowsHtml = '';
        foreach ($items as $it) {
            $qty   = (int) ($it['qty'] ?? 1);
            $name  = htmlspecialchars((string) ($it['name'] ?? 'Producto'), ENT_QUOTES);
            $note  = !empty($it['notes'])
                ? '<div style="font-size:12px;color:#6B7588;margin-top:2px">' . htmlspecialchars((string) $it['notes'], ENT_QUOTES) . '</div>'
                : '';
            $sub   = $currency . ' ' . number_format((float) ($it['subtotal'] ?? 0), 2);
            $itemsRowsHtml .=
                '<tr>'
              . '<td style="padding:14px 12px;border-bottom:1px solid #EEF1F4;font-size:14px;color:#0B1220;width:42px;vertical-align:top"><span style="display:inline-block;min-width:28px;padding:2px 8px;background:#ECFDF5;color:#0B7C56;border-radius:999px;font-weight:700;font-size:12px;text-align:center">' . $qty . '×</span></td>'
              . '<td style="padding:14px 12px;border-bottom:1px solid #EEF1F4;font-size:14px;color:#0B1220;font-weight:500">' . $name . $note . '</td>'
              . '<td style="padding:14px 12px;border-bottom:1px solid #EEF1F4;font-size:14px;color:#0B1220;text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap">' . $sub . '</td>'
              . '</tr>';
        }
        if ($itemsRowsHtml === '') {
            $itemsRowsHtml = '<tr><td colspan="3" style="padding:20px;text-align:center;color:#6B7588;font-size:13px">Sin items</td></tr>';
        }

        // Filas de totales
        $totalsRows = '';
        if ($subtotal > 0 && ($subtotal !== (float) $total || $delivery > 0 || $tax > 0)) {
            $totalsRows .= '<tr><td style="padding:6px 12px;color:#6B7588;font-size:13px">Subtotal</td><td style="padding:6px 12px;text-align:right;font-size:13px;color:#0B1220">' . $currency . ' ' . number_format($subtotal, 2) . '</td></tr>';
        }
        if ($delivery > 0) {
            $totalsRows .= '<tr><td style="padding:6px 12px;color:#6B7588;font-size:13px">Delivery</td><td style="padding:6px 12px;text-align:right;font-size:13px;color:#0B1220">' . $currency . ' ' . number_format($delivery, 2) . '</td></tr>';
        }
        if ($tax > 0) {
            $taxRate  = \App\Models\Order::tenantTaxRate($this->tenantId);
            $taxLabel = $taxRate > 0
                ? 'ITBIS (' . rtrim(rtrim(number_format($taxRate, 2, '.', ''), '0'), '.') . '%)'
                : 'ITBIS';
            $totalsRows .= '<tr><td style="padding:6px 12px;color:#6B7588;font-size:13px">' . $taxLabel . '</td><td style="padding:6px 12px;text-align:right;font-size:13px;color:#0B1220">' . $currency . ' ' . number_format($tax, 2) . '</td></tr>';
        }

        $deliveryTypeLabels = ['delivery' => '🛵 Delivery', 'pickup' => '🏪 Pickup', 'dine_in' => '🍽️ Dine-in'];
        $deliveryTypeLb = $deliveryTypeLabels[$deliveryType] ?? ucfirst($deliveryType);

        $appUrl  = $baseUrl !== '' ? $baseUrl : 'https://pulse.kyrosrd.com';
        $brandName = htmlspecialchars((string) (\App\Core\Config::get('app.name', 'Evallish Pulse')), ENT_QUOTES);
        $year = date('Y');

        // Email HTML — table-based, mobile-first, compatible Outlook/Gmail/Apple
        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#F6F8FA;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#0B1220;-webkit-font-smoothing:antialiased">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F6F8FA;padding:32px 16px">
  <tr>
    <td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(11,18,32,0.06);border:1px solid #EEF1F4">

        <!-- Header con brand -->
        <tr>
          <td style="padding:20px 24px;background:#0B1220;border-bottom:3px solid {$color}">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="font-size:18px;font-weight:700;color:#FFFFFF;letter-spacing:-0.01em">
                  <span style="display:inline-block;width:32px;height:32px;background:linear-gradient(135deg,#10B981,#0EA572);border-radius:8px;text-align:center;line-height:32px;color:#fff;font-size:16px;vertical-align:middle;margin-right:8px">⚡</span>
                  {$brandName}
                </td>
                <td style="text-align:right;font-size:11px;color:#7C8699;text-transform:uppercase;letter-spacing:0.08em;font-weight:600">
                  Notificacion de orden
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Hero status -->
        <tr>
          <td style="padding:32px 24px 24px;text-align:center;background:linear-gradient(180deg,rgba(16,185,129,0.04),#FFFFFF)">
            <div style="font-size:48px;line-height:1;margin-bottom:12px">{$emoji}</div>
            <div style="display:inline-block;padding:5px 14px;background:{$color}1A;color:{$color};border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:14px">{$statusLb}</div>
            <h1 style="margin:0 0 4px;font-size:28px;font-weight:800;color:#0B1220;letter-spacing:-0.02em">Orden {$code}</h1>
            <p style="margin:0;font-size:14px;color:#6B7588">{$deliveryTypeLb}</p>
          </td>
        </tr>

        <!-- Datos del cliente -->
        <tr>
          <td style="padding:8px 24px 24px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F4F6F8;border-radius:12px;border:1px solid #EEF1F4">
              <tr>
                <td style="padding:14px 16px">
                  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#6B7588;margin-bottom:4px">Cliente</div>
                  <div style="font-size:15px;font-weight:600;color:#0B1220">{$customer}</div>
                </td>
                <td style="padding:14px 16px;border-left:1px solid #EEF1F4">
                  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#6B7588;margin-bottom:4px">Telefono</div>
                  <div style="font-size:14px;color:#0B1220;font-family:'SF Mono',Consolas,monospace">{$phone}</div>
                </td>
              </tr>
HTML;

        if ($address !== '') {
            $addressEsc = htmlspecialchars($address, ENT_QUOTES);
            $html .= '<tr><td colspan="2" style="padding:0 16px 14px"><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#6B7588;margin-bottom:4px">Direccion</div><div style="font-size:13px;color:#364152;line-height:1.5">' . $addressEsc . '</div></td></tr>';
        }
        if ($notes !== '') {
            $notesEsc = htmlspecialchars($notes, ENT_QUOTES);
            $html .= '<tr><td colspan="2" style="padding:0 16px 14px"><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#F59E0B;margin-bottom:4px">⚠ Notas</div><div style="font-size:13px;color:#364152;line-height:1.5;font-style:italic">"' . $notesEsc . '"</div></td></tr>';
        }

        $totalFmt = $currency . ' ' . $total;
        $html .= <<<HTML
            </table>
          </td>
        </tr>

        <!-- Items -->
        <tr>
          <td style="padding:0 24px 8px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#6B7588;margin-bottom:8px;padding-left:4px">Items del pedido</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFFFFF;border:1px solid #EEF1F4;border-radius:12px;overflow:hidden">
              {$itemsRowsHtml}
            </table>
          </td>
        </tr>

        <!-- Totales -->
        <tr>
          <td style="padding:16px 24px 8px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              {$totalsRows}
              <tr>
                <td style="padding:14px 12px;background:linear-gradient(135deg,rgba(16,185,129,0.08),rgba(16,185,129,0.02));border-radius:10px;border:1px solid rgba(16,185,129,0.20)">
                  <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#0B7C56">Total</div>
                </td>
                <td style="padding:14px 12px;background:linear-gradient(135deg,rgba(16,185,129,0.08),rgba(16,185,129,0.02));border-radius:10px;border:1px solid rgba(16,185,129,0.20);text-align:right">
                  <div style="font-size:22px;font-weight:800;color:#0B7C56;font-variant-numeric:tabular-nums">{$totalFmt}</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- CTA principal -->
        <tr>
          <td style="padding:24px 24px 8px;text-align:center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto">
              <tr>
                <td style="border-radius:10px;background:linear-gradient(135deg,#10B981,#0EA572);box-shadow:0 8px 22px rgba(14,165,114,0.35)">
                  <a href="{$orderUrl}" style="display:inline-block;padding:14px 32px;color:#FFFFFF;font-size:15px;font-weight:700;text-decoration:none;border-radius:10px">Ver orden completa →</a>
                </td>
              </tr>
            </table>
            <p style="margin:14px 0 0;font-size:12px;color:#6B7588">Click para abrir la orden en tu panel y gestionarla.</p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:24px;text-align:center;border-top:1px solid #EEF1F4;background:#F6F8FA">
            <p style="margin:0 0 6px;font-size:12px;color:#6B7588">Este aviso te llega porque configuraste un destino de notificacion en {$brandName}.</p>
            <p style="margin:0;font-size:11px;color:#98A2B3">
              <a href="{$baseUrl}/settings/notifications" style="color:#0EA572;text-decoration:none">Gestionar notificaciones</a>
              &nbsp;·&nbsp;
              <a href="{$baseUrl}" style="color:#0EA572;text-decoration:none">Ir al panel</a>
            </p>
            <p style="margin:14px 0 0;font-size:11px;color:#98A2B3">© {$year} {$brandName} · Powered by Evallish Pulse</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        return [
            'subject'   => $title,
            'title'     => $title,
            'text'      => $text,
            'html'      => $html,
            'event'     => $event,
            'order_id'  => $orderId,
            'order_url' => $orderUrl,
            'fields'    => [
                ['label' => 'Cliente',  'value' => $customer ?: '—'],
                ['label' => 'Telefono', 'value' => $phone ?: '—'],
                ['label' => 'Total',    'value' => $currency . ' ' . $total],
                ['label' => 'Estado',   'value' => $statusLb],
                ['label' => 'Codigo',   'value' => $code],
                ['label' => 'Tipo',     'value' => $deliveryTypeLb],
            ],
        ];
    }

    // ============================================================
    //  Drivers
    // ============================================================

    private function sendEmail(array $config, array $p): array
    {
        // Soporta nuevo formato (emails: array) y legacy (email: string)
        $recipients = [];
        if (!empty($config['emails']) && is_array($config['emails'])) {
            $recipients = array_values(array_filter(array_map('trim', $config['emails'])));
        } elseif (!empty($config['email'])) {
            $recipients = [trim((string) $config['email'])];
        } elseif (!empty($config['to'])) {
            $recipients = [trim((string) $config['to'])];
        }

        $recipients = array_values(array_unique(array_filter($recipients, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
        if (empty($recipients)) return ['success' => false, 'error' => 'Sin destinatarios validos'];

        $resend = new ResendService($this->tenantId);
        $okCount = 0;
        $errors = [];
        $ids = [];
        foreach ($recipients as $to) {
            $res = $resend->sendEmail($to, (string) $p['subject'], (string) $p['html'], (string) $p['text']);
            if (!empty($res['success'])) {
                $okCount++;
                if (!empty($res['id'])) $ids[] = $res['id'];
            } else {
                $errors[] = $to . ': ' . ($res['error'] ?? 'fallo');
            }
        }
        $allOk = $okCount === count($recipients);
        return [
            'success'  => $allOk,
            'error'    => empty($errors) ? null : implode(' | ', $errors),
            'response' => sprintf('%d/%d enviados%s', $okCount, count($recipients), $ids ? ' · ids=' . implode(',', $ids) : ''),
        ];
    }

    private function sendSlack(array $config, array $p): array
    {
        $url = trim((string) ($config['webhook_url'] ?? ''));
        if ($url === '' || stripos($url, 'https://hooks.slack.com/') !== 0) {
            return ['success' => false, 'error' => 'Webhook URL de Slack invalida (debe empezar con https://hooks.slack.com/)'];
        }

        $fields = [];
        foreach (($p['fields'] ?? []) as $f) {
            $fields[] = ['type' => 'mrkdwn', 'text' => "*{$f['label']}:*\n{$f['value']}"];
        }

        $blocks = [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => mb_substr((string) $p['title'], 0, 150)]],
            ['type' => 'section', 'fields' => array_slice($fields, 0, 10)],
        ];
        if (!empty($p['order_url'])) {
            $blocks[] = [
                'type' => 'actions',
                'elements' => [[
                    'type'  => 'button',
                    'text'  => ['type' => 'plain_text', 'text' => 'Ver orden completa →', 'emoji' => true],
                    'style' => 'primary',
                    'url'   => (string) $p['order_url'],
                ]],
            ];
        }
        $blocks[] = [
            'type' => 'context',
            'elements' => [['type' => 'mrkdwn', 'text' => '⚡ Evallish Pulse · ' . date('d M Y H:i')]],
        ];

        return $this->postJson($url, [
            'text'   => $p['title'] ?? 'Notificacion',
            'blocks' => $blocks,
        ]);
    }

    private function sendDiscord(array $config, array $p): array
    {
        $url = trim((string) ($config['webhook_url'] ?? ''));
        if ($url === '' || stripos($url, 'discord.com') === false) {
            return ['success' => false, 'error' => 'Webhook URL de Discord invalida'];
        }

        $fields = [];
        foreach (($p['fields'] ?? []) as $f) {
            $fields[] = ['name' => (string) $f['label'], 'value' => (string) $f['value'], 'inline' => true];
        }
        $embed = [
            'title'       => mb_substr((string) $p['title'], 0, 256),
            'description' => mb_substr((string) $p['text'], 0, 2000),
            'color'       => 0x0EA572,
            'fields'      => $fields,
            'footer'      => ['text' => 'Evallish Pulse · ' . date('Y-m-d H:i')],
            'timestamp'   => date('c'),
        ];
        if (!empty($p['order_url'])) {
            // Discord no tiene "buttons" en webhooks; el url va en el title como link
            $embed['url'] = (string) $p['order_url'];
            $embed['description'] .= "\n\n**[Ver orden completa →](" . $p['order_url'] . ')**';
        }
        return $this->postJson($url, [
            'username' => $config['username'] ?? 'Evallish Pulse',
            'embeds'   => [$embed],
        ]);
    }

    private function sendTeams(array $config, array $p): array
    {
        $url = trim((string) ($config['webhook_url'] ?? ''));
        if ($url === '' || (stripos($url, 'webhook.office.com') === false && stripos($url, 'office365.com') === false)) {
            return ['success' => false, 'error' => 'Webhook URL de Teams invalida'];
        }

        $facts = [];
        foreach (($p['fields'] ?? []) as $f) {
            $facts[] = ['name' => (string) $f['label'], 'value' => (string) $f['value']];
        }
        $body = [
            '@type'      => 'MessageCard',
            '@context'   => 'http://schema.org/extensions',
            'themeColor' => '0EA572',
            'summary'    => mb_substr((string) $p['title'], 0, 100),
            'title'      => mb_substr((string) $p['title'], 0, 200),
            'sections'   => [[
                'activityTitle' => 'Evallish Pulse',
                'activitySubtitle' => date('d M Y H:i'),
                'text' => mb_substr((string) $p['text'], 0, 3000),
                'facts' => $facts,
            ]],
        ];
        if (!empty($p['order_url'])) {
            $body['potentialAction'] = [[
                '@type' => 'OpenUri',
                'name'  => 'Ver orden completa',
                'targets' => [['os' => 'default', 'uri' => (string) $p['order_url']]],
            ]];
        }
        return $this->postJson($url, $body);
    }

    private function sendTelegram(array $config, array $p): array
    {
        $token  = trim((string) ($config['bot_token'] ?? ''));
        $chatId = trim((string) ($config['chat_id'] ?? ''));
        if ($token === '' || $chatId === '') {
            return ['success' => false, 'error' => 'Falta bot_token o chat_id en la configuracion de Telegram'];
        }

        $lines = ['*' . $this->escapeMarkdown((string) $p['title']) . '*', ''];
        foreach (($p['fields'] ?? []) as $f) {
            $lines[] = '*' . $this->escapeMarkdown((string) $f['label']) . ':* ' . $this->escapeMarkdown((string) $f['value']);
        }
        if (!empty($p['order_url'])) {
            $lines[] = '';
            $lines[] = '[Ver orden completa →](' . $p['order_url'] . ')';
        }
        $text = implode("\n", $lines);

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        return $this->postJson($url, [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
        ]);
    }

    private function sendWebhook(array $config, array $p, string $event): array
    {
        $url = trim((string) ($config['url'] ?? ''));
        if ($url === '' || !preg_match('~^https?://~i', $url)) {
            return ['success' => false, 'error' => 'URL del webhook invalida'];
        }

        $body = [
            'event'     => $event,
            'tenant_id' => $this->tenantId,
            'timestamp' => date('c'),
            'data'      => $p,
        ];
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'User-Agent: KyrosPulse/1.0'];
        if (!empty($config['secret'])) {
            $sig = hash_hmac('sha256', (string) $bodyJson, (string) $config['secret']);
            $headers[] = 'X-Kyros-Signature: sha256=' . $sig;
        }
        return $this->postJson($url, $body, $headers, $bodyJson);
    }

    private function sendWhatsapp(array $config, array $p): array
    {
        // Reutilizamos el primer canal WhatsApp activo del tenant como remitente.
        $phone = trim((string) ($config['phone'] ?? ''));
        if ($phone === '') return ['success' => false, 'error' => 'Falta numero destino (phone)'];

        try {
            $dispatcher = new ChannelDispatcher($this->tenantId);
            $text = (string) $p['text'];
            $res  = $dispatcher->sendText($phone, $text);
            $ok   = !empty($res['success']);
            return [
                'success'  => $ok,
                'response' => isset($res['message_id']) ? 'wa_msg_id=' . $res['message_id'] : 'whatsapp_sent',
                'error'    => $ok ? null : (string) ($res['error'] ?? 'No se pudo enviar via WhatsApp'),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================
    //  HTTP helper
    // ============================================================

    private function postJson(string $url, array $body, array $headers = [], ?string $rawBody = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $rawBody ?? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) return ['success' => false, 'error' => 'cURL: ' . $err];
        $ok = $code >= 200 && $code < 300;
        return [
            'success'  => $ok,
            'error'    => $ok ? null : ('HTTP ' . $code . ': ' . mb_substr((string) $resp, 0, 500)),
            'response' => 'HTTP ' . $code,
        ];
    }

    private function escapeMarkdown(string $s): string
    {
        return str_replace(['_', '*', '[', ']', '`'], ['\\_', '\\*', '\\[', '\\]', '\\`'], $s);
    }
}
