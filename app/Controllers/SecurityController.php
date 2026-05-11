<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\User;
use App\Services\SecurityService;
use App\Services\TotpService;

/**
 * Admin UI de seguridad:
 *   GET    /settings/security                    pagina principal
 *   POST   /settings/security/2fa/setup          genera secret + QR (no activa)
 *   POST   /settings/security/2fa/confirm        verifica codigo inicial y activa
 *   POST   /settings/security/2fa/disable        requiere password
 *   POST   /settings/security/2fa/recovery/regen regenera recovery codes
 *   POST   /settings/security/sessions/{id}/revoke
 *   POST   /settings/security/sessions/revoke-others
 *   POST   /settings/security/password           cambiar password
 */
final class SecurityController extends Controller
{
    public function index(Request $request): void
    {
        $userId = Auth::id();
        $tenantId = Tenant::id();
        $user = User::findById($userId);

        // Cada query envuelta: si migration 011 no aplico aun, la pagina sigue funcionando
        $row2fa = null;
        try { $row2fa = SecurityService::get2faRow($userId); }
        catch (\Throwable $e) { \App\Core\Logger::warning('get2faRow fallo', ['msg' => $e->getMessage()]); }

        $enabled = $row2fa && !empty($row2fa['enabled']);

        $recoveryCount = 0;
        try { $recoveryCount = SecurityService::recoveryCodesUnusedCount($userId); }
        catch (\Throwable $e) { \App\Core\Logger::warning('recovery count fallo', ['msg' => $e->getMessage()]); }

        $sessions = [];
        try { $sessions = SecurityService::activeSessionsForUser($userId); }
        catch (\Throwable $e) { \App\Core\Logger::warning('sessions fallo', ['msg' => $e->getMessage()]); }

        $events = [];
        try { $events = SecurityService::recentEventsForUser($userId, 30); }
        catch (\Throwable $e) { \App\Core\Logger::warning('events user fallo', ['msg' => $e->getMessage()]); }

        $tenantEvents = [];
        if ($tenantId) {
            try { $tenantEvents = SecurityService::recentEventsForTenant($tenantId, 30); }
            catch (\Throwable $e) { \App\Core\Logger::warning('events tenant fallo', ['msg' => $e->getMessage()]); }
        }

        $currentSessionHash = hash('sha256', session_id() ?: '');

        // Si acabamos de generar codes, mostrarlos one-shot
        $newRecoveryCodes = Session::get('__new_recovery_codes');
        Session::forget('__new_recovery_codes');

        $qrUrl = null;
        if ($row2fa && empty($row2fa['enabled']) && !empty($row2fa['secret']) && !empty($user['email'])) {
            try {
                $qrUrl = TotpService::qrImageUrl(TotpService::provisioningUri((string) $row2fa['secret'], (string) $user['email']));
            } catch (\Throwable) { $qrUrl = null; }
        }

        $this->view('settings.security', [
            'page'                => 'configuracion',
            'tab'                 => 'security',
            'user'                => $user,
            'twofa_enabled'       => $enabled,
            'twofa_secret'        => $enabled ? null : ($row2fa['secret'] ?? null), // exponemos solo si NO esta enabled todavia
            'qr_url'              => $qrUrl,
            'recovery_count'      => $recoveryCount,
            'new_recovery_codes'  => $newRecoveryCodes,
            'sessions'            => $sessions,
            'current_session_hash'=> $currentSessionHash,
            'events'              => $events,
            'tenant_events'       => $tenantEvents,
        ], 'layouts.app');
    }

    public function setup2fa(Request $request): void
    {
        $userId = Auth::id();
        // Si ya esta enabled, no permitir generar nuevo secret sin disable primero
        if (SecurityService::user2faEnabled($userId)) {
            Session::flash('error', '2FA ya esta activa. Desactivala antes de generar un nuevo secreto.');
            $this->redirect('/settings/security');
            return;
        }
        $secret = TotpService::generateSecret();
        SecurityService::set2faSecret($userId, $secret);
        SecurityService::logEvent('2fa_setup_started', $userId, Tenant::id(), 'info');
        $this->redirect('/settings/security');
    }

    public function confirm2fa(Request $request): void
    {
        $userId = Auth::id();
        $code = trim((string) $request->input('code', ''));
        $row = SecurityService::get2faRow($userId);
        if (!$row || !empty($row['enabled'])) {
            Session::flash('error', '2FA no iniciado o ya activo.');
            $this->redirect('/settings/security');
            return;
        }
        if (!preg_match('/^\d{6}$/', preg_replace('/\s+/', '', $code) ?? '')) {
            Session::flash('error', 'Codigo debe ser de 6 digitos.');
            $this->redirect('/settings/security');
            return;
        }
        $clean = preg_replace('/\s+/', '', $code);
        if (!TotpService::verify((string) $row['secret'], $clean)) {
            Session::flash('error', 'Codigo invalido. Verifica que la hora de tu telefono sea correcta.');
            $this->redirect('/settings/security');
            return;
        }
        SecurityService::enable2fa($userId);
        SecurityService::record2faCodeUsed($userId, $clean);
        $codes = SecurityService::generateRecoveryCodes($userId, 10);
        SecurityService::logEvent('2fa_enabled', $userId, Tenant::id(), 'info');
        Session::set('__new_recovery_codes', $codes);
        Session::flash('success', '2FA activado. Guarda los recovery codes ahora — solo se muestran una vez.');
        $this->redirect('/settings/security');
    }

    public function disable2fa(Request $request): void
    {
        $userId = Auth::id();
        $password = (string) $request->input('password', '');
        if ($password === '') {
            Session::flash('error', 'Confirma tu password para desactivar 2FA.');
            $this->redirect('/settings/security');
            return;
        }
        $user = User::findById($userId);
        if (!$user || !password_verify($password, (string) $user['password'])) {
            SecurityService::logEvent('2fa_disable_bad_password', $userId, Tenant::id(), 'warning');
            Session::flash('error', 'Password incorrecto.');
            $this->redirect('/settings/security');
            return;
        }
        SecurityService::disable2fa($userId);
        SecurityService::logEvent('2fa_disabled', $userId, Tenant::id(), 'warning');
        Session::flash('success', '2FA desactivado.');
        $this->redirect('/settings/security');
    }

    public function regenRecoveryCodes(Request $request): void
    {
        $userId = Auth::id();
        if (!SecurityService::user2faEnabled($userId)) {
            Session::flash('error', '2FA no esta activo.');
            $this->redirect('/settings/security');
            return;
        }
        $codes = SecurityService::generateRecoveryCodes($userId, 10);
        SecurityService::logEvent('2fa_recovery_regen', $userId, Tenant::id(), 'info');
        Session::set('__new_recovery_codes', $codes);
        Session::flash('success', 'Nuevos recovery codes generados. Los anteriores fueron invalidados.');
        $this->redirect('/settings/security');
    }

    public function revokeSession(Request $request, array $params): void
    {
        $userId = Auth::id();
        $id = (int) ($params['id'] ?? 0);
        $count = SecurityService::revokeSession($userId, $id);
        if ($count > 0) {
            SecurityService::logEvent('session_revoked', $userId, Tenant::id(), 'info', ['session_row_id' => $id]);
            Session::flash('success', 'Sesion revocada.');
        }
        $this->redirect('/settings/security');
    }

    public function revokeOtherSessions(Request $request): void
    {
        $userId = Auth::id();
        $count = SecurityService::revokeAllOtherSessions($userId, session_id());
        SecurityService::logEvent('sessions_revoke_all_others', $userId, Tenant::id(), 'warning', ['count' => $count]);
        Session::flash('success', "Se revocaron $count otras sesiones.");
        $this->redirect('/settings/security');
    }

    public function changePassword(Request $request): void
    {
        $userId = Auth::id();
        $current = (string) $request->input('current_password', '');
        $new     = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('confirm_password', '');

        if ($current === '' || $new === '') {
            Session::flash('error', 'Completa todos los campos.');
            $this->redirect('/settings/security');
            return;
        }
        if (mb_strlen($new) < 8) {
            Session::flash('error', 'La nueva password debe tener al menos 8 caracteres.');
            $this->redirect('/settings/security');
            return;
        }
        if ($new !== $confirm) {
            Session::flash('error', 'Las passwords no coinciden.');
            $this->redirect('/settings/security');
            return;
        }
        $user = User::findById($userId);
        if (!$user || !password_verify($current, (string) $user['password'])) {
            SecurityService::logEvent('password_change_bad_current', $userId, Tenant::id(), 'warning');
            Session::flash('error', 'Password actual incorrecto.');
            $this->redirect('/settings/security');
            return;
        }
        User::updatePassword($userId, $new);
        SecurityService::logEvent('password_changed', $userId, Tenant::id(), 'warning');

        // Revocar todas las demas sesiones por seguridad
        SecurityService::revokeAllOtherSessions($userId, session_id());

        Session::flash('success', 'Password actualizada. Otras sesiones activas fueron cerradas por seguridad.');
        $this->redirect('/settings/security');
    }
}
