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
     * Envia un mensaje de prueba a un destino concreto. Lo usa el boton "Probar"
     * en la UI para validar que la config funciona antes de guardar.
     */
    public function testDestination(array $destination): array
    {
        $payload = [
            'subject' => '[Prueba] Notificacion de Kyros Pulse',
            'title'   => '✅ Conexion correcta',
            'text'    => 'Este es un mensaje de prueba enviado desde Kyros Pulse. Si lo ves, la integracion funciona.',
            'html'    => '<p>Este es un mensaje de prueba enviado desde <strong>Kyros Pulse</strong>. Si lo ves, la integracion funciona.</p>',
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
        $statusLabels = [
            'order.new'              => 'Nueva orden',
            'order.confirmed'        => 'Orden confirmada',
            'order.preparing'        => 'En preparacion',
            'order.ready'            => 'Lista',
            'order.out_for_delivery' => 'En camino',
            'order.delivered'        => 'Entregada',
            'order.cancelled'        => 'Cancelada',
        ];
        $code     = (string) ($order['code'] ?? ('#' . ($order['id'] ?? '')));
        $customer = trim((string) ($order['customer_name'] ?? ($order['contact_name'] ?? 'Cliente')));
        $phone    = (string) ($order['customer_phone'] ?? ($order['contact_phone'] ?? ''));
        $total    = number_format((float) ($order['total'] ?? 0), 2);
        $currency = (string) ($order['currency'] ?? 'USD');
        $statusLb = $statusLabels[$event] ?? ucfirst((string) ($order['status'] ?? ''));
        $items    = $order['items'] ?? [];

        $itemsTextLines = [];
        $itemsHtml = '';
        foreach ($items as $it) {
            $line = sprintf('%dx %s', (int) ($it['qty'] ?? 1), (string) ($it['name'] ?? 'Producto'));
            if (!empty($it['notes'])) $line .= ' (' . $it['notes'] . ')';
            $line .= ' — ' . $currency . ' ' . number_format((float) ($it['subtotal'] ?? 0), 2);
            $itemsTextLines[] = $line;
            $itemsHtml .= '<li>' . htmlspecialchars($line, ENT_QUOTES) . '</li>';
        }
        $itemsText = empty($itemsTextLines) ? '(sin items)' : implode("\n", $itemsTextLines);

        $title = sprintf('%s · %s', $statusLb, $code);
        $text  = "Cliente: {$customer}\nTelefono: {$phone}\nTotal: {$currency} {$total}\nEstado: {$statusLb}\n\nItems:\n{$itemsText}";

        $html = "<h2 style='margin:0 0 12px;color:#0EA572'>" . htmlspecialchars($title, ENT_QUOTES) . "</h2>"
              . "<table style='border-collapse:collapse;font-family:Inter,system-ui,sans-serif'>"
              . "<tr><td style='padding:4px 12px 4px 0;color:#6B7588'>Cliente</td><td style='padding:4px 0;font-weight:600'>" . htmlspecialchars($customer, ENT_QUOTES) . "</td></tr>"
              . "<tr><td style='padding:4px 12px 4px 0;color:#6B7588'>Telefono</td><td style='padding:4px 0'>" . htmlspecialchars($phone, ENT_QUOTES) . "</td></tr>"
              . "<tr><td style='padding:4px 12px 4px 0;color:#6B7588'>Total</td><td style='padding:4px 0;font-weight:700;color:#0EA572'>" . $currency . ' ' . $total . "</td></tr>"
              . "<tr><td style='padding:4px 12px 4px 0;color:#6B7588;vertical-align:top'>Items</td><td style='padding:4px 0'><ul style='margin:0;padding-left:18px'>" . $itemsHtml . "</ul></td></tr>"
              . "</table>";

        return [
            'subject' => $title,
            'title'   => $title,
            'text'    => $text,
            'html'    => $html,
            'event'   => $event,
            'order_id' => (int) ($order['id'] ?? 0),
            'fields'  => [
                ['label' => 'Cliente',  'value' => $customer ?: '—'],
                ['label' => 'Telefono', 'value' => $phone ?: '—'],
                ['label' => 'Total',    'value' => $currency . ' ' . $total],
                ['label' => 'Estado',   'value' => $statusLb],
                ['label' => 'Codigo',   'value' => $code],
            ],
        ];
    }

    // ============================================================
    //  Drivers
    // ============================================================

    private function sendEmail(array $config, array $p): array
    {
        $to = trim((string) ($config['email'] ?? ($config['to'] ?? '')));
        if ($to === '') return ['success' => false, 'error' => 'Email destinatario vacio'];

        $resend = new ResendService($this->tenantId);
        $res = $resend->sendEmail($to, (string) $p['subject'], (string) $p['html'], (string) $p['text']);
        return [
            'success'  => !empty($res['success']),
            'error'    => $res['error'] ?? null,
            'response' => isset($res['id']) ? 'resend_id=' . $res['id'] : null,
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
        $body = [
            'text' => $p['title'] ?? 'Notificacion',
            'blocks' => [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => mb_substr((string) $p['title'], 0, 150)]],
                ['type' => 'section', 'fields' => array_slice($fields, 0, 10)],
                ['type' => 'context', 'elements' => [
                    ['type' => 'mrkdwn', 'text' => '🚀 Kyros Pulse · ' . date('d M Y H:i')],
                ]],
            ],
        ];
        return $this->postJson($url, $body);
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
        $body = [
            'username' => $config['username'] ?? 'Kyros Pulse',
            'embeds'   => [[
                'title'       => mb_substr((string) $p['title'], 0, 256),
                'description' => mb_substr((string) $p['text'], 0, 2000),
                'color'       => 0x0EA572,
                'fields'      => $fields,
                'footer'      => ['text' => 'Kyros Pulse · ' . date('Y-m-d H:i')],
                'timestamp'   => date('c'),
            ]],
        ];
        return $this->postJson($url, $body);
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
                'activityTitle' => 'Kyros Pulse',
                'activitySubtitle' => date('d M Y H:i'),
                'text' => mb_substr((string) $p['text'], 0, 3000),
                'facts' => $facts,
            ]],
        ];
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
