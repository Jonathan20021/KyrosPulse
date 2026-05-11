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
use App\Models\NotificationDestination;
use App\Services\NotificationDispatcher;

final class NotificationDestinationController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $destinations = NotificationDestination::listForTenant($tenantId);

        $logs = Database::fetchAll(
            "SELECT l.*, d.label, d.type AS dest_type
             FROM notification_logs l
             LEFT JOIN notification_destinations d ON d.id = l.destination_id
             WHERE l.tenant_id = :t
             ORDER BY l.id DESC LIMIT 30",
            ['t' => $tenantId]
        );

        $this->view('settings.notifications', [
            'page'         => 'configuracion',
            'tab'          => 'notifications',
            'destinations' => $destinations,
            'events'       => NotificationDestination::availableEvents(),
            'types'        => NotificationDestination::TYPES,
            'logs'         => $logs,
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->collectInput($request);

        if ($err = $this->validateDestination($data)) {
            Session::flash('error', $err);
            $this->redirect('/settings/notifications?prefill=' . urlencode($data['type']));
            return;
        }

        $data['created_by'] = Auth::id();
        $id = NotificationDestination::create($tenantId, $data);
        Audit::log('notifications.destination.created', 'notification_destination', $id, [], ['type' => $data['type']]);
        Session::flash('success', 'Destino "' . $data['label'] . '" creado.');
        $this->redirect('/settings/notifications');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $existing = NotificationDestination::find($tenantId, $id);
        if (!$existing) { Session::flash('error', 'Destino no encontrado.'); $this->redirect('/settings/notifications'); return; }

        $data = $this->collectInput($request);
        if ($err = $this->validateDestination($data)) {
            Session::flash('error', $err);
            $this->redirect('/settings/notifications');
            return;
        }

        NotificationDestination::update($tenantId, $id, $data);
        Audit::log('notifications.destination.updated', 'notification_destination', $id, $existing, $data);
        Session::flash('success', 'Destino "' . $data['label'] . '" actualizado.');
        $this->redirect('/settings/notifications');
    }

    /**
     * Validacion server-side de un destino: rechaza si faltan label, eventos o
     * campos del canal. Devuelve string con el mensaje de error o null si OK.
     * Nota: NO se llama validate() porque el parent Controller ya define ese
     * metodo con otra firma; PHP lo rechaza por incompatibilidad de signatura.
     */
    private function validateDestination(array $data): ?string
    {
        if (empty($data['label']))  return 'La etiqueta interna es obligatoria.';
        if (empty($data['type']))   return 'El tipo de canal es obligatorio.';
        if (empty($data['events'])) return 'Selecciona al menos un evento al que suscribirte.';

        $cfg = $data['config'] ?? [];
        switch ($data['type']) {
            case 'email':
                if (empty($cfg['emails']) || !is_array($cfg['emails'])) {
                    return 'Agrega al menos un email destinatario valido.';
                }
                break;
            case 'slack':
                $url = trim((string) ($cfg['webhook_url'] ?? ''));
                if ($url === '') return 'El Webhook URL de Slack es obligatorio.';
                if (stripos($url, 'https://hooks.slack.com/') !== 0) {
                    return 'El Webhook URL debe empezar con https://hooks.slack.com/';
                }
                break;
            case 'discord':
                $url = trim((string) ($cfg['webhook_url'] ?? ''));
                if ($url === '') return 'El Webhook URL de Discord es obligatorio.';
                if (stripos($url, 'discord.com') === false && stripos($url, 'discordapp.com') === false) {
                    return 'El Webhook URL debe ser de discord.com';
                }
                break;
            case 'teams':
                $url = trim((string) ($cfg['webhook_url'] ?? ''));
                if ($url === '') return 'El Webhook URL de Teams es obligatorio.';
                if (stripos($url, 'webhook.office.com') === false && stripos($url, 'office365.com') === false) {
                    return 'El Webhook URL debe ser de webhook.office.com';
                }
                break;
            case 'telegram':
                if (empty($cfg['bot_token']))  return 'El Bot Token de Telegram es obligatorio.';
                if (empty($cfg['chat_id']))    return 'El Chat ID de Telegram es obligatorio.';
                break;
            case 'webhook':
                $url = trim((string) ($cfg['url'] ?? ''));
                if ($url === '') return 'La URL del webhook es obligatoria.';
                if (!preg_match('~^https?://~i', $url)) return 'La URL debe empezar con http:// o https://';
                break;
            case 'whatsapp':
                if (empty($cfg['phone'])) return 'El numero destino de WhatsApp es obligatorio.';
                break;
        }
        return null;
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        NotificationDestination::softDelete($tenantId, $id);
        Audit::log('notifications.destination.deleted', 'notification_destination', $id);
        Session::flash('success', 'Destino eliminado.');
        $this->redirect('/settings/notifications');
    }

    public function toggle(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $row = NotificationDestination::find($tenantId, $id);
        if (!$row) { $this->redirect('/settings/notifications'); return; }
        NotificationDestination::update($tenantId, $id, ['is_active' => empty($row['is_active']) ? 1 : 0]);
        $this->redirect('/settings/notifications');
    }

    public function test(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $row = NotificationDestination::find($tenantId, $id);
        if (!$row) { Session::flash('error', 'Destino no encontrado.'); $this->redirect('/settings/notifications'); return; }

        // Validacion previa: si la config esta incompleta, mensaje claro y no
        // contar como fallo de envio (es de configuracion).
        $preErr = $this->validateDestination([
            'label'  => $row['label'] ?? 'X',
            'type'   => $row['type']  ?? '',
            'events' => $row['events'] ?? ['x'],
            'config' => $row['config'] ?? [],
        ]);
        if ($preErr !== null) {
            Session::flash('error', '⚠ Configuracion incompleta: ' . $preErr . ' Edita el destino, completa el campo y guarda antes de probar.');
            $this->redirect('/settings/notifications');
            return;
        }

        $res = (new NotificationDispatcher($tenantId))->testDestination($row);
        if (!empty($res['success'])) {
            Session::flash('success', '✓ Prueba enviada con exito al destino "' . $row['label'] . '".');
        } else {
            Session::flash('error', '✗ Fallo la prueba: ' . ($res['error'] ?? 'desconocido'));
        }
        $this->redirect('/settings/notifications');
    }

    /**
     * Normaliza el input del formulario y arma el `config` segun el tipo.
     */
    private function collectInput(Request $request): array
    {
        $type   = (string) $request->input('type', 'email');
        $label  = trim((string) $request->input('label', ''));
        $entity = (string) $request->input('entity', 'order');
        $events = (array) $request->input('events', []);
        $events = array_values(array_filter($events, fn ($e) => is_string($e) && $e !== ''));
        $isActive = $request->input('is_active') ? 1 : 0;

        $config = [];
        switch ($type) {
            case 'email':
                // Acepta: comma/semicolon/whitespace separados. Maximo 20. Valida cada uno.
                $rawEmails = (string) $request->input('emails', '') ?: (string) $request->input('email', '');
                $parts = preg_split('/[,;\s]+/', $rawEmails) ?: [];
                $emails = [];
                foreach ($parts as $p) {
                    $p = trim((string) $p);
                    if ($p === '') continue;
                    if (filter_var($p, FILTER_VALIDATE_EMAIL) && !in_array($p, $emails, true)) {
                        $emails[] = $p;
                    }
                    if (count($emails) >= 20) break;
                }
                $config = ['emails' => $emails];
                break;
            case 'slack':
            case 'discord':
            case 'teams':
                $config = ['webhook_url' => trim((string) $request->input('webhook_url', ''))];
                if ($type === 'discord') {
                    $config['username'] = trim((string) $request->input('username', 'Kyros Pulse'));
                }
                break;
            case 'telegram':
                $config = [
                    'bot_token' => trim((string) $request->input('bot_token', '')),
                    'chat_id'   => trim((string) $request->input('chat_id', '')),
                ];
                break;
            case 'webhook':
                $config = [
                    'url'    => trim((string) $request->input('url', '')),
                    'secret' => trim((string) $request->input('secret', '')),
                ];
                break;
            case 'whatsapp':
                $config = ['phone' => trim((string) $request->input('phone', ''))];
                break;
        }

        return [
            'type'      => $type,
            'label'     => $label,
            'entity'    => $entity,
            'events'    => $events,
            'config'    => $config,
            'is_active' => $isActive,
        ];
    }
}
