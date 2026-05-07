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

        if (empty($data['label']) || empty($data['type']) || empty($data['events'])) {
            Session::flash('error', 'Faltan datos: etiqueta, tipo y al menos un evento.');
            $this->redirect('/settings/notifications');
            return;
        }

        $data['created_by'] = Auth::id();
        $id = NotificationDestination::create($tenantId, $data);
        Audit::log('notifications.destination.created', 'notification_destination', $id, [], ['type' => $data['type']]);
        Session::flash('success', 'Destino de notificacion creado.');
        $this->redirect('/settings/notifications');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $existing = NotificationDestination::find($tenantId, $id);
        if (!$existing) { Session::flash('error', 'Destino no encontrado.'); $this->redirect('/settings/notifications'); return; }

        $data = $this->collectInput($request);
        NotificationDestination::update($tenantId, $id, $data);
        Audit::log('notifications.destination.updated', 'notification_destination', $id, $existing, $data);
        Session::flash('success', 'Destino actualizado.');
        $this->redirect('/settings/notifications');
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
                $config = ['email' => trim((string) $request->input('email', ''))];
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
