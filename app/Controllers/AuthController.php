<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ResendService;
use App\Services\SecurityService;
use App\Services\TotpService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->view('auth.login', ['errors' => errors()], 'layouts.auth');
    }

    public function login(Request $request): void
    {
        $data = $this->validate($request, [
            'email'    => 'required|email|max:180',
            'password' => 'required|min:6',
        ]);

        $email = (string) $data['email'];
        $ip    = $request->ip();
        $ua    = $request->userAgent();

        // Lockout check: si el email + IP acumularon N fallos recientes,
        // bloquear independientemente de si la password es correcta.
        $lockout = SecurityService::lockoutSeconds($email, $ip);
        if ($lockout > 0) {
            $mins = (int) ceil($lockout / 60);
            SecurityService::recordLoginAttempt($email, $ip, null, false, 'locked', $ua);
            SecurityService::logEvent('login_locked', null, null, 'warning', ['email' => $email, 'remaining_s' => $lockout]);
            Session::flash('error', "Demasiados intentos fallidos. Intenta de nuevo en ~$mins min.");
            $this->withErrors(['email' => ['Cuenta bloqueada temporalmente.']], $request->only(['email']));
            $this->redirect('/login');
            return;
        }

        $user = Auth::attempt($email, (string) $data['password']);
        if (!$user) {
            SecurityService::recordLoginAttempt($email, $ip, null, false, 'bad_password', $ua);
            SecurityService::logEvent('login_fail', null, null, 'warning', ['email' => $email]);
            Session::flash('error', 'Correo o contrasena incorrectos.');
            $this->withErrors(['email' => ['Correo o contrasena incorrectos.']], $request->only(['email']));
            $this->redirect('/login');
            return;
        }

        // 2FA gate: si el user tiene 2FA habilitado, NO login aun.
        // Auth::attempt ya hizo login(); deshacemos y guardamos pending.
        if (SecurityService::user2faEnabled((int) $user['id'])) {
            // Auth::attempt llamo a Auth::login() ya: lo revertimos a un estado pending.
            $userId = (int) $user['id'];
            Auth::logout();
            Session::start(); // re-iniciar sesion limpia
            Session::set('_2fa_pending_user_id', $userId);
            Session::set('_2fa_pending_at',      time());
            SecurityService::logEvent('login_2fa_required', $userId, $user['tenant_id'] ? (int) $user['tenant_id'] : null, 'info', ['email' => $email]);
            $this->redirect('/login/2fa');
            return;
        }

        // Login exitoso sin 2FA
        SecurityService::clearLockout($email);
        SecurityService::recordLoginAttempt($email, $ip, (int) $user['id'], true, 'ok', $ua);
        SecurityService::logEvent('login_ok', (int) $user['id'], $user['tenant_id'] ? (int) $user['tenant_id'] : null, 'info', ['email' => $email]);
        SecurityService::recordSession((int) $user['id'], $user['tenant_id'] ? (int) $user['tenant_id'] : null, session_id(), $ip, $ua);

        Csrf::rotate();
        Session::clearOldInput();

        if ($user['is_super_admin']) {
            $this->redirect('/admin');
            return;
        }
        $this->redirect('/dashboard');
    }

    /**
     * GET /login/2fa — challenge tras password ok cuando el user tiene 2FA.
     * El user_id pendiente vive en sesion durante 5 min.
     */
    public function show2faChallenge(Request $request): void
    {
        $pending = (int) Session::get('_2fa_pending_user_id', 0);
        $at = (int) Session::get('_2fa_pending_at', 0);
        if ($pending === 0 || (time() - $at) > 300) {
            Session::forget('_2fa_pending_user_id');
            Session::forget('_2fa_pending_at');
            $this->redirect('/login');
            return;
        }
        $this->view('auth.two_factor', ['errors' => errors()], 'layouts.auth');
    }

    /**
     * POST /login/2fa — verifica codigo TOTP o recovery code.
     */
    public function verify2faChallenge(Request $request): void
    {
        $pending = (int) Session::get('_2fa_pending_user_id', 0);
        $at = (int) Session::get('_2fa_pending_at', 0);
        if ($pending === 0 || (time() - $at) > 300) {
            Session::flash('error', 'Sesion expirada. Vuelve a iniciar sesion.');
            $this->redirect('/login');
            return;
        }

        $user = User::findById($pending);
        if (!$user) {
            Session::forget('_2fa_pending_user_id');
            $this->redirect('/login');
            return;
        }

        $code = trim((string) $request->input('code', ''));
        $ip   = $request->ip();
        $ua   = $request->userAgent();
        $row  = SecurityService::get2faRow($pending);

        $verified = false;
        $method = 'totp';

        if ($code !== '' && $row && !empty($row['secret'])) {
            // TOTP code (6 digitos)
            if (preg_match('/^\d{6}$/', preg_replace('/\s+/', '', $code) ?? '')) {
                $clean = preg_replace('/\s+/', '', $code);
                $lastCode = (string) ($row['last_used_code'] ?? '');
                $lastTs   = $row['last_used_at'] ? strtotime((string) $row['last_used_at']) : 0;
                // Anti-replay: si el mismo codigo se uso hace < 30s, rechazar.
                if ($lastCode === $clean && (time() - $lastTs) < 30) {
                    $verified = false;
                } else {
                    $verified = TotpService::verify((string) $row['secret'], $clean);
                    if ($verified) SecurityService::record2faCodeUsed($pending, $clean);
                }
            }
            // Recovery code (formato XXXX-XXXX)
            if (!$verified && preg_match('/^[A-Z0-9]{4}-?[A-Z0-9]{4}$/i', $code)) {
                $verified = SecurityService::consumeRecoveryCode($pending, $code);
                if ($verified) $method = 'recovery';
            }
        }

        if (!$verified) {
            SecurityService::recordLoginAttempt((string) $user['email'], $ip, $pending, false, '2fa_bad_code', $ua);
            SecurityService::logEvent('login_2fa_fail', $pending, $user['tenant_id'] ? (int) $user['tenant_id'] : null, 'warning');
            Session::flash('error', 'Codigo invalido.');
            $this->withErrors(['code' => ['Codigo invalido.']]);
            $this->redirect('/login/2fa');
            return;
        }

        // Login final
        Session::forget('_2fa_pending_user_id');
        Session::forget('_2fa_pending_at');
        Auth::login($user);
        Csrf::rotate();

        SecurityService::clearLockout((string) $user['email']);
        SecurityService::recordLoginAttempt((string) $user['email'], $ip, $pending, true, '2fa_ok', $ua);
        SecurityService::logEvent('login_ok', $pending, $user['tenant_id'] ? (int) $user['tenant_id'] : null, 'info', ['method' => $method]);
        SecurityService::recordSession($pending, $user['tenant_id'] ? (int) $user['tenant_id'] : null, session_id(), $ip, $ua);

        if ($user['is_super_admin']) { $this->redirect('/admin'); return; }
        $this->redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        $userId = Auth::id();
        $tenantId = Auth::tenantId();
        try { SecurityService::markSessionRevokedByHash(session_id()); } catch (\Throwable) {}
        try { SecurityService::logEvent('logout', $userId, $tenantId, 'info'); } catch (\Throwable) {}
        Auth::logout();
        Session::flash('success', 'Sesion cerrada correctamente.');
        $this->redirect('/login');
    }

    /**
     * GET /logout — convenience cuando el usuario pega la URL en el navegador.
     * Cierra sesion (si la habia) sin pedir CSRF (GET no acepta tokens en form)
     * y redirige a /login. Evita 500/404 cuando alguien intenta salir tipeando
     * la URL.
     */
    public function logoutGet(Request $request): void
    {
        if (Auth::check()) {
            $userId   = Auth::id();
            $tenantId = Auth::tenantId();
            try { SecurityService::markSessionRevokedByHash(session_id()); } catch (\Throwable) {}
            try { SecurityService::logEvent('logout', $userId, $tenantId, 'info', ['via' => 'GET']); } catch (\Throwable) {}
            Auth::logout();
            Session::flash('success', 'Sesion cerrada correctamente.');
        }
        $this->redirect('/login');
    }

    public function showRegister(Request $request): void
    {
        $this->view('auth.register', ['errors' => errors()], 'layouts.auth');
    }

    public function register(Request $request): void
    {
        $data = $this->validate($request, [
            'company_name' => 'required|min:2|max:150',
            'first_name'   => 'required|min:2|max:80',
            'last_name'    => 'required|min:2|max:80',
            'email'        => 'required|email|max:180',
            'phone'        => 'phone',
            'password'     => 'required|min:8|confirmed',
            'terms'        => 'required',
        ]);

        if (User::emailExists((string) $data['email'])) {
            Session::flash('error', 'Este correo ya esta registrado.');
            $this->withErrors(['email' => ['Este correo ya esta registrado.']], $request->only(['company_name', 'first_name', 'last_name', 'email', 'phone']));
            $this->redirect('/register');
            return;
        }

        try {
            [$tenantId, $userId] = Database::transaction(function () use ($data) {
                $slug = Tenant::generateUniqueSlug((string) $data['company_name']);

                $tenantId = Tenant::create([
                    'slug'          => $slug,
                    'name'          => $data['company_name'],
                    'email'         => strtolower(trim((string) $data['email'])),
                    'phone'         => $data['phone'] ?? null,
                    'country'       => 'DO',
                    'currency'      => 'USD',
                    'timezone'      => (string) config('app.timezone', 'America/Santo_Domingo'),
                    'language'      => 'es',
                    'status'        => 'trial',
                    'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+14 days')),
                    'plan_id'       => 1, // Plan starter por defecto
                ]);

                $userId = User::createUser([
                    'tenant_id'   => $tenantId,
                    'first_name'  => $data['first_name'],
                    'last_name'   => $data['last_name'],
                    'email'       => $data['email'],
                    'phone'       => $data['phone'] ?? null,
                    'password'    => $data['password'],
                    'language'    => 'es',
                    'is_active'   => 1,
                ]);

                // Asignar rol owner
                $owner = Role::findBySlug('owner');
                if ($owner) {
                    User::assignRole($userId, (int) $owner['id'], $tenantId);
                }

                return [$tenantId, $userId];
            });

            // Crear etapas iniciales del pipeline
            $this->seedPipelineStages($tenantId);

            // Enviar email de verificacion
            $this->sendVerificationEmail($userId);

            // Auto-login
            $user = User::findById($userId);
            if ($user) {
                Auth::login($user);
            }

            Session::flash('success', 'Cuenta creada correctamente. Te enviamos un correo para verificar tu cuenta.');
            $this->redirect('/dashboard');
        } catch (\Throwable $e) {
            Logger::error('Error en registro', ['msg' => $e->getMessage()]);
            Session::flash('error', 'No se pudo crear la cuenta. Intenta de nuevo en unos momentos.');
            $this->redirect('/register');
        }
    }

    public function showForgot(Request $request): void
    {
        $this->view('auth.forgot', ['errors' => errors()], 'layouts.auth');
    }

    public function forgot(Request $request): void
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
        ]);

        $email = strtolower(trim((string) $data['email']));
        $user  = User::findByEmail($email);

        // Por seguridad no revelar si existe o no
        if ($user) {
            $token = bin2hex(random_bytes(32));
            Database::insert('password_resets', [
                'email'      => $email,
                'token'      => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'ip_address' => $request->ip(),
            ]);

            $resetUrl = url('/reset-password?token=' . $token . '&email=' . urlencode($email));
            $resend = new ResendService($user['tenant_id'] ? (int) $user['tenant_id'] : null);
            $resend->sendPasswordReset($user, $resetUrl);
        }

        Session::flash('success', 'Si el correo existe, te hemos enviado las instrucciones para restablecer tu contrasena.');
        $this->redirect('/login');
    }

    public function showReset(Request $request): void
    {
        $token = (string) $request->query('token', '');
        $email = (string) $request->query('email', '');
        $this->view('auth.reset', ['token' => $token, 'email' => $email, 'errors' => errors()], 'layouts.auth');
    }

    public function reset(Request $request): void
    {
        $data = $this->validate($request, [
            'email'    => 'required|email',
            'token'    => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $row = Database::fetch(
            "SELECT * FROM password_resets WHERE email = :e AND token = :t AND used_at IS NULL AND expires_at > NOW()",
            ['e' => strtolower(trim((string) $data['email'])), 't' => hash('sha256', (string) $data['token'])]
        );

        if (!$row) {
            Session::flash('error', 'Token invalido o expirado.');
            $this->redirect('/forgot-password');
            return;
        }

        $user = User::findByEmail((string) $data['email']);
        if (!$user) {
            Session::flash('error', 'Usuario no encontrado.');
            $this->redirect('/forgot-password');
            return;
        }

        User::updatePassword((int) $user['id'], (string) $data['password']);
        Database::run("UPDATE password_resets SET used_at = NOW() WHERE id = :id", ['id' => $row['id']]);

        Session::flash('success', 'Contrasena actualizada. Ya puedes iniciar sesion.');
        $this->redirect('/login');
    }

    public function verifyEmail(Request $request): void
    {
        $token = (string) $request->query('token', '');
        if ($token === '') {
            $this->abort(400, 'Token requerido.');
        }

        $row = Database::fetch(
            "SELECT * FROM email_verifications WHERE token = :t AND verified_at IS NULL AND expires_at > NOW()",
            ['t' => hash('sha256', $token)]
        );

        if (!$row) {
            Session::flash('error', 'Token de verificacion invalido o expirado.');
            $this->redirect('/login');
            return;
        }

        User::markEmailVerified((int) $row['user_id']);
        Database::run("UPDATE email_verifications SET verified_at = NOW() WHERE id = :id", ['id' => $row['id']]);

        Session::flash('success', 'Correo verificado. Bienvenido a Evallish Pulse.');
        $this->redirect(Auth::check() ? '/dashboard' : '/login');
    }

    public function showVerifyNotice(Request $request): void
    {
        $this->view('auth.verify_notice', [], 'layouts.auth');
    }

    public function resendVerification(Request $request): void
    {
        $userId = Auth::id();
        if (!$userId) {
            $this->redirect('/login');
            return;
        }
        $this->sendVerificationEmail($userId);
        Session::flash('success', 'Te hemos reenviado el correo de verificacion.');
        $this->redirect('/email/verify-notice');
    }

    // -----------------------------------------------------------------

    private function sendVerificationEmail(int $userId): void
    {
        $user = User::findById($userId);
        if (!$user) return;

        $token = bin2hex(random_bytes(32));
        Database::insert('email_verifications', [
            'user_id'    => $userId,
            'token'      => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 86400),
        ]);

        $url = url('/email/verify?token=' . $token);
        $resend = new ResendService($user['tenant_id'] ? (int) $user['tenant_id'] : null);
        $resend->sendVerificationEmail($user, $url);
    }

    private function seedPipelineStages(int $tenantId): void
    {
        $stages = [
            ['Nuevo lead',        'nuevo',       '#06B6D4', 10, 0, 0, 1],
            ['Contactado',        'contactado',  '#3B82F6', 25, 0, 0, 2],
            ['Interesado',        'interesado',  '#7C3AED', 50, 0, 0, 3],
            ['Cotizacion enviada','cotizacion',  '#A855F7', 70, 0, 0, 4],
            ['Negociacion',       'negociacion', '#F59E0B', 85, 0, 0, 5],
            ['Ganado',            'ganado',      '#22C55E', 100, 1, 0, 6],
            ['Perdido',           'perdido',     '#EF4444', 0, 0, 1, 7],
        ];
        foreach ($stages as [$name, $slug, $color, $prob, $isWon, $isLost, $order]) {
            Database::insert('pipeline_stages', [
                'tenant_id'   => $tenantId,
                'name'        => $name,
                'slug'        => $slug,
                'color'       => $color,
                'probability' => $prob,
                'is_won'      => $isWon,
                'is_lost'     => $isLost,
                'sort_order'  => $order,
            ]);
        }
    }
}
