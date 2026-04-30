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
use App\Models\WhatsappChannel;
use App\Services\WasapiService;
use App\Services\WhatsappCloudService;

final class ChannelController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $channels = WhatsappChannel::listForTenant($tenantId);

        $stats = [];
        foreach ($channels as $ch) {
            $stats[$ch['id']] = [
                'msgs_24h' => (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM messages WHERE tenant_id = :t AND channel_id = :c
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
                    ['t' => $tenantId, 'c' => (int) $ch['id']]
                ),
                'open_convs' => (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM conversations WHERE tenant_id = :t AND channel_id = :c AND status NOT IN ('closed','resolved')",
                    ['t' => $tenantId, 'c' => (int) $ch['id']]
                ),
            ];
        }

        $this->view('settings.channels', [
            'page'     => 'configuracion',
            'tab'      => 'channels',
            'channels' => $channels,
            'stats'    => $stats,
            'providers' => WhatsappChannel::PROVIDERS,
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'label'    => 'required|min:2|max:120',
            'phone'    => 'required|min:6|max:40',
            'provider' => 'required',
        ]);

        $provider = (string) $data['provider'];
        if (!array_key_exists($provider, WhatsappChannel::PROVIDERS)) {
            Session::flash('error', 'Proveedor invalido.');
            $this->redirect('/settings/channels');
            return;
        }

        $payload = [
            'tenant_id'           => $tenantId,
            'label'               => trim($data['label']),
            'phone'               => $this->normalizePhone((string) $data['phone']),
            'provider'            => $provider,
            'display_name'        => trim((string) $request->input('display_name', '')),
            'business_account_id' => trim((string) $request->input('business_account_id', '')) ?: null,
            'phone_number_id'     => trim((string) $request->input('phone_number_id', '')) ?: null,
            'api_key'             => trim((string) $request->input('api_key', '')) ?: null,
            'api_secret'          => trim((string) $request->input('api_secret', '')) ?: null,
            'access_token'        => trim((string) $request->input('access_token', '')) ?: null,
            'webhook_secret'      => trim((string) $request->input('webhook_secret', '')) ?: null,
            'webhook_verify'      => trim((string) $request->input('webhook_verify', '')) ?: null,
            'color'               => trim((string) $request->input('color', '#7C3AED')),
            'status'              => 'active',
        ];

        // Si es el primer canal, marcar como default
        $existing = WhatsappChannel::listForTenant($tenantId);
        if (empty($existing) || !empty($request->input('is_default'))) {
            $payload['is_default'] = 1;
        }

        try {
            $id = WhatsappChannel::create($payload);
            if (!empty($payload['is_default'])) {
                WhatsappChannel::setDefault($tenantId, $id);
            }
            Audit::log('channel.created', 'whatsapp_channel', $id, [], ['provider' => $provider, 'phone' => $payload['phone']]);
            Session::flash('success', 'Canal de WhatsApp creado correctamente.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('channel.create failed', ['msg' => $e->getMessage()]);
            Session::flash('error', 'No se pudo crear el canal: ' . $e->getMessage());
        }

        $this->redirect('/settings/channels');
    }

    public function update(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $existing = WhatsappChannel::findById($tenantId, $id);
        if (!$existing) $this->abort(404);

        $patch = [
            'label'               => trim((string) $request->input('label', $existing['label'])),
            'phone'               => $this->normalizePhone((string) $request->input('phone', $existing['phone'])),
            'display_name'        => trim((string) $request->input('display_name', $existing['display_name'] ?? '')),
            'business_account_id' => trim((string) $request->input('business_account_id', $existing['business_account_id'] ?? '')) ?: null,
            'phone_number_id'     => trim((string) $request->input('phone_number_id', $existing['phone_number_id'] ?? '')) ?: null,
            'api_key'             => $request->input('api_key') !== '' ? (string) $request->input('api_key', $existing['api_key']) : $existing['api_key'],
            'api_secret'          => $request->input('api_secret') !== '' ? (string) $request->input('api_secret', $existing['api_secret']) : $existing['api_secret'],
            'access_token'        => $request->input('access_token') !== '' ? (string) $request->input('access_token', $existing['access_token']) : $existing['access_token'],
            'webhook_secret'      => trim((string) $request->input('webhook_secret', $existing['webhook_secret'] ?? '')) ?: null,
            'webhook_verify'      => trim((string) $request->input('webhook_verify', $existing['webhook_verify'] ?? '')) ?: null,
            'color'               => trim((string) $request->input('color', $existing['color'] ?? '#7C3AED')),
        ];

        WhatsappChannel::update($tenantId, $id, $patch);

        if (!empty($request->input('is_default'))) {
            WhatsappChannel::setDefault($tenantId, $id);
        }

        Audit::log('channel.updated', 'whatsapp_channel', $id);
        Session::flash('success', 'Canal actualizado.');
        $this->redirect('/settings/channels');
    }

    public function setDefault(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $exists = WhatsappChannel::findById($tenantId, $id);
        if (!$exists) $this->abort(404);
        WhatsappChannel::setDefault($tenantId, $id);
        Session::flash('success', 'Canal marcado como predeterminado.');
        $this->redirect('/settings/channels');
    }

    public function toggle(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $exists = WhatsappChannel::findById($tenantId, $id);
        if (!$exists) $this->abort(404);
        $newStatus = $exists['status'] === 'active' ? 'disabled' : 'active';
        WhatsappChannel::update($tenantId, $id, ['status' => $newStatus]);
        Session::flash('success', $newStatus === 'active' ? 'Canal activado.' : 'Canal pausado.');
        $this->redirect('/settings/channels');
    }

    public function destroy(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $exists = WhatsappChannel::findById($tenantId, $id);
        if (!$exists) $this->abort(404);
        WhatsappChannel::softDelete($tenantId, $id);
        Audit::log('channel.deleted', 'whatsapp_channel', $id);
        Session::flash('success', 'Canal eliminado.');
        $this->redirect('/settings/channels');
    }

    public function test(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $channel = WhatsappChannel::findById($tenantId, $id);
        if (!$channel) {
            $this->json(['success' => false, 'error' => 'Canal no encontrado.'], 404);
            return;
        }

        try {
            if ($channel['provider'] === 'cloud') {
                $svc = new WhatsappCloudService($tenantId, $channel);
                $info = $svc->getNumberInfo();
                if (!empty($info['success'])) {
                    $body = $info['body'] ?? [];
                    WhatsappChannel::update($tenantId, $id, [
                        'display_name'         => $body['verified_name']    ?? null,
                        'quality_rating'       => $body['quality_rating']   ?? 'unknown',
                        'messaging_limit_tier' => $body['messaging_limit_tier'] ?? null,
                        'last_health_check'    => date('Y-m-d H:i:s'),
                        'error_message'        => null,
                        'status'               => 'active',
                    ]);
                    $this->json(['success' => true, 'info' => $body]);
                    return;
                }
                WhatsappChannel::update($tenantId, $id, [
                    'last_health_check' => date('Y-m-d H:i:s'),
                    'error_message' => $info['error'] ?? 'No se pudo conectar.',
                    'status' => 'error',
                ]);
                $this->json(['success' => false, 'error' => $info['error'] ?? 'Error desconocido']);
                return;
            }

            // Wasapi
            $svc = new WasapiService($tenantId, $channel);
            $resp = $svc->getWhatsappNumbers();
            if (!empty($resp['success'])) {
                WhatsappChannel::update($tenantId, $id, [
                    'last_health_check' => date('Y-m-d H:i:s'),
                    'error_message' => null,
                    'status' => 'active',
                ]);
                $this->json(['success' => true, 'numbers' => $resp['body']['data'] ?? []]);
                return;
            }
            WhatsappChannel::update($tenantId, $id, [
                'last_health_check' => date('Y-m-d H:i:s'),
                'error_message' => $resp['error'] ?? 'Sin respuesta',
                'status' => 'error',
            ]);
            $this->json(['success' => false, 'error' => $resp['error'] ?? 'Error']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        return $digits !== '' ? '+' . $digits : $phone;
    }
}
