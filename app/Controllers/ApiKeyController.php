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
use App\Models\ApiKey;
use App\Services\ApiKeyService;

/**
 * Admin UI para gestionar las API keys del tenant + audit log de requests.
 *
 * Rutas:
 *   GET    /settings/api-keys
 *   POST   /settings/api-keys                  -> generar (devuelve plain_key 1 vez)
 *   POST   /settings/api-keys/{id}/revoke
 *   POST   /settings/api-keys/{id}/rename
 */
final class ApiKeyController extends Controller
{
    public function index(Request $request): void
    {
        $tenantId = Tenant::id();
        $keys     = ApiKey::listForTenant($tenantId);
        $stats    = ApiKey::statsForTenant($tenantId, 7);

        $logs = Database::fetchAll(
            "SELECT l.*, k.name AS key_name, k.prefix
             FROM `api_request_logs` l
             LEFT JOIN `api_keys` k ON k.id = l.api_key_id
             WHERE l.tenant_id = :t
             ORDER BY l.id DESC
             LIMIT 50",
            ['t' => $tenantId]
        );

        // Si acabamos de generar una key en este flow, la pasamos one-shot
        $newKey = Session::get('__new_api_key');
        Session::forget('__new_api_key');

        $quota = \App\Services\ApiQuotaService::snapshot($tenantId);

        $this->view('settings.api_keys', [
            'page'    => 'configuracion',
            'tab'     => 'api',
            'keys'    => $keys,
            'logs'    => $logs,
            'stats'   => $stats,
            'scopes'  => ApiKeyService::SCOPES_AVAILABLE,
            'newKey'  => $newKey,
            'quota'   => $quota,
        ], 'layouts.app');
    }

    public function store(Request $request): void
    {
        $tenantId = Tenant::id();
        $userId   = Auth::id();

        $name   = trim((string) $request->input('name', 'API key'));
        $scopes = (array) $request->input('scopes', ['*']);
        $scopes = array_values(array_filter(array_map('strval', $scopes)));
        if (!$scopes) $scopes = ['*'];

        $expiresAt = (string) $request->input('expires_at', '');
        $expiresAt = $expiresAt !== '' ? $expiresAt : null;

        $ipsRaw = (string) $request->input('allowed_ips', '');
        $allowedIps = null;
        if ($ipsRaw !== '') {
            $allowedIps = array_values(array_filter(array_map('trim', explode(',', $ipsRaw))));
            if (empty($allowedIps)) $allowedIps = null;
        }

        if ($name === '') {
            Session::flash('error', 'El nombre de la API key es obligatorio.');
            $this->redirect('/settings/api-keys');
            return;
        }

        // Validar scopes contra catalogo
        $valid = array_keys(ApiKeyService::SCOPES_AVAILABLE);
        foreach ($scopes as $s) {
            if (!in_array($s, $valid, true)) {
                Session::flash('error', 'Scope invalido: ' . $s);
                $this->redirect('/settings/api-keys');
                return;
            }
        }

        $created = ApiKeyService::generate($tenantId, $name, $scopes, $userId, $expiresAt, $allowedIps);
        Audit::log('api_key.create', 'api_key', $created['id'], [], ['name' => $name, 'scopes' => $scopes]);
        \App\Services\SecurityService::logEvent('api_key_create', $userId, $tenantId, 'warning', [
            'api_key_id' => $created['id'],
            'name'       => $name,
            'scopes'     => $scopes,
            'expires_at' => $expiresAt,
        ]);

        // Pasar el plain_key UNA SOLA VEZ via flash (no persistido)
        Session::set('__new_api_key', [
            'id'        => $created['id'],
            'plain_key' => $created['plain_key'],
            'prefix'    => $created['prefix'],
            'name'      => $name,
        ]);

        Session::flash('success', 'API key creada. Coemepiala AHORA — no se mostrara de nuevo.');
        $this->redirect('/settings/api-keys');
    }

    public function revoke(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $count = ApiKey::revoke($tenantId, $id);
        if ($count > 0) {
            Audit::log('api_key.revoke', 'api_key', $id);
            \App\Services\SecurityService::logEvent('api_key_revoke', Auth::id(), $tenantId, 'warning', ['api_key_id' => $id]);
            Session::flash('success', 'API key revocada.');
        } else {
            Session::flash('error', 'No se pudo revocar (ya estaba revocada o no existe).');
        }
        $this->redirect('/settings/api-keys');
    }

    public function rename(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Nombre vacio.');
            $this->redirect('/settings/api-keys');
            return;
        }
        ApiKey::rename($tenantId, $id, mb_substr($name, 0, 120));
        Audit::log('api_key.rename', 'api_key', $id, [], ['name' => $name]);
        Session::flash('success', 'API key renombrada.');
        $this->redirect('/settings/api-keys');
    }
}
