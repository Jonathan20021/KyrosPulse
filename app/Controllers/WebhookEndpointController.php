<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatcher;

/**
 * Admin UI para gestionar webhooks salientes con HMAC.
 *
 *   GET    /settings/webhooks
 *   POST   /settings/webhooks                   (crear: muestra secret 1 vez)
 *   POST   /settings/webhooks/{id}/update
 *   POST   /settings/webhooks/{id}/toggle
 *   POST   /settings/webhooks/{id}/rotate       (rotar secret)
 *   POST   /settings/webhooks/{id}/delete
 *   POST   /settings/webhooks/deliveries/{uuid}/replay
 */
final class WebhookEndpointController extends Controller
{
    /** Catalogo de eventos publicos que el tenant puede subscribir desde la UI. */
    private const EVENTS_CATALOG = [
        'order.created'         => 'Orden creada',
        'order.status_changed'  => 'Status de orden cambiado',
        'order.cancelled'       => 'Orden cancelada',
        'order.delivered'       => 'Orden entregada',
        'agent.run.completed'   => 'Agente IA ejecutado (OK)',
        'agent.run.failed'      => 'Agente IA fallo',
        'contact.created'       => 'Contacto creado',
        'contact.updated'       => 'Contacto actualizado',
        'message.received'      => 'Mensaje recibido (cliente)',
        'message.sent'          => 'Mensaje enviado',
        'conversation.opened'   => 'Conversacion abierta',
        'conversation.closed'   => 'Conversacion cerrada',
        'lead.stage_changed'    => 'Etapa de lead cambiada',
        'ticket.created'        => 'Ticket creado',
        'ticket.updated'        => 'Ticket actualizado',
    ];

    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $endpoints = WebhookEndpoint::listForTenant($tenantId);
        $deliveries = WebhookEndpoint::deliveriesForTenant($tenantId, 50);

        // Pasamos el secret one-shot solo si el flow recien creo uno
        $newSecret = Session::get('__new_webhook_secret');
        Session::forget('__new_webhook_secret');

        $this->view('settings.webhooks', [
            'page'       => 'configuracion',
            'tab'        => 'webhooks',
            'endpoints'  => $endpoints,
            'deliveries' => $deliveries,
            'events'     => self::EVENTS_CATALOG,
            'newSecret'  => $newSecret,
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $userId   = Auth::id();

        $name = trim((string) $request->input('name', ''));
        $url  = trim((string) $request->input('url', ''));
        $description = trim((string) $request->input('description', ''));
        $events = (array) $request->input('events', []);
        $events = array_values(array_filter(array_map('strval', $events)));

        if ($name === '' || $url === '') {
            Session::flash('error', 'Nombre y URL son obligatorios.');
            $this->redirect('/settings/webhooks');
            return;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Session::flash('error', 'URL invalida.');
            $this->redirect('/settings/webhooks');
            return;
        }
        // Bloquear localhost / IPs internas en produccion para evitar SSRF
        if (env('APP_ENV', 'production') === 'production' && self::isInternalUrl($url)) {
            Session::flash('error', 'No se permiten URLs internas/localhost.');
            $this->redirect('/settings/webhooks');
            return;
        }
        if (empty($events)) $events = ['*'];

        $secret = WebhookDispatcher::generateSecret();
        $id = WebhookEndpoint::create([
            'tenant_id'   => $tenantId,
            'created_by'  => $userId,
            'name'        => mb_substr($name, 0, 120),
            'url'         => $url,
            'secret'      => $secret,
            'events'      => json_encode($events, JSON_UNESCAPED_SLASHES),
            'is_active'   => 1,
            'description' => $description !== '' ? mb_substr($description, 0, 500) : null,
        ]);
        Audit::log('webhook.create', 'webhook_endpoint', $id, [], ['name' => $name, 'url' => $url, 'events' => $events]);

        Session::set('__new_webhook_secret', [
            'id'     => $id,
            'secret' => $secret,
            'name'   => $name,
        ]);
        Session::flash('success', 'Webhook creado. Copia el secret AHORA — no se mostrara de nuevo.');
        $this->redirect('/settings/webhooks');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $row = WebhookEndpoint::findByIdForTenant($tenantId, $id);
        if (!$row) {
            Session::flash('error', 'Webhook no encontrado.');
            $this->redirect('/settings/webhooks');
            return;
        }

        $patch = [];
        $name = trim((string) $request->input('name', ''));
        $url  = trim((string) $request->input('url', ''));
        if ($name !== '') $patch['name'] = mb_substr($name, 0, 120);
        if ($url !== '') {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Session::flash('error', 'URL invalida.');
                $this->redirect('/settings/webhooks');
                return;
            }
            $patch['url'] = $url;
        }
        if (($d = trim((string) $request->input('description', ''))) !== '') {
            $patch['description'] = mb_substr($d, 0, 500);
        }
        $events = $request->input('events');
        if (is_array($events) && !empty($events)) {
            $patch['events'] = json_encode(array_values(array_map('strval', $events)), JSON_UNESCAPED_SLASHES);
        }

        if (!empty($patch)) {
            WebhookEndpoint::update($tenantId, $id, $patch);
            Audit::log('webhook.update', 'webhook_endpoint', $id, [], $patch);
        }
        Session::flash('success', 'Webhook actualizado.');
        $this->redirect('/settings/webhooks');
    }

    public function toggle(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $row = WebhookEndpoint::findByIdForTenant($tenantId, $id);
        if (!$row) {
            Session::flash('error', 'Webhook no encontrado.');
            $this->redirect('/settings/webhooks');
            return;
        }
        WebhookEndpoint::update($tenantId, $id, ['is_active' => empty($row['is_active']) ? 1 : 0]);
        Session::flash('success', empty($row['is_active']) ? 'Webhook activado.' : 'Webhook pausado.');
        $this->redirect('/settings/webhooks');
    }

    public function rotate(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $row = WebhookEndpoint::findByIdForTenant($tenantId, $id);
        if (!$row) {
            Session::flash('error', 'Webhook no encontrado.');
            $this->redirect('/settings/webhooks');
            return;
        }
        $secret = WebhookDispatcher::generateSecret();
        WebhookEndpoint::update($tenantId, $id, ['secret' => $secret]);
        Audit::log('webhook.rotate_secret', 'webhook_endpoint', $id);
        Session::set('__new_webhook_secret', [
            'id'     => $id,
            'secret' => $secret,
            'name'   => (string) $row['name'],
        ]);
        Session::flash('success', 'Secret rotado. Copialo AHORA.');
        $this->redirect('/settings/webhooks');
    }

    public function delete(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        Database::run("DELETE FROM `webhook_deliveries` WHERE `endpoint_id` = :i AND `tenant_id` = :t", ['i' => $id, 't' => $tenantId]);
        $count = WebhookEndpoint::delete($tenantId, $id);
        if ($count > 0) {
            Audit::log('webhook.delete', 'webhook_endpoint', $id);
            Session::flash('success', 'Webhook eliminado.');
        } else {
            Session::flash('error', 'No se pudo eliminar.');
        }
        $this->redirect('/settings/webhooks');
    }

    public function replayDelivery(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $uuid = (string) ($params['uuid'] ?? '');
        $result = WebhookDispatcher::replay($tenantId, $uuid);
        if (!$result) {
            Session::flash('error', 'Delivery no encontrado.');
        } else {
            Audit::log('webhook.replay', 'webhook_delivery', 0, [], ['uuid' => $uuid]);
            Session::flash('success', $result['delivered'] ? 'Reintento exitoso.' : 'Reintento agendado (sigue pendiente).');
        }
        $this->redirect('/settings/webhooks');
    }

    /** SSRF guard: rechaza localhost, 127.0.0.1, 10/8, 172.16/12, 192.168/16 */
    private static function isInternalUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host  = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || $host === '0.0.0.0') return true;
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : @gethostbyname($host);
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) return false;
        $ranges = ['127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.0.0/16', '::1/128', 'fc00::/7'];
        foreach ($ranges as $r) {
            if (self::ipInCidr($ip, $r)) return true;
        }
        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) return $ip === $cidr;
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipL = ip2long($ip); $sL = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            return ($ipL & $mask) === ($sL & $mask);
        }
        $ipBin = @inet_pton($ip); $sBin = @inet_pton($subnet);
        if (!$ipBin || !$sBin || strlen($ipBin) !== strlen($sBin)) return false;
        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if (substr($ipBin, 0, $bytes) !== substr($sBin, 0, $bytes)) return false;
        if ($rem === 0) return true;
        $mask = chr(0xFF << (8 - $rem) & 0xFF);
        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($sBin[$bytes]) & ord($mask));
    }
}
