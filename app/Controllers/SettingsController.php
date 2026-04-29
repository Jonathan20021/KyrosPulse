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
use App\Models\KnowledgeBase;
use App\Models\QuickReply;
use App\Models\Role;
use App\Models\Tenant as TenantModel;
use App\Models\User;
use App\Services\ResendService;

final class SettingsController extends Controller
{
    public function general(Request $request): void
    {
        $tenantId = Tenant::id();
        $tenant   = TenantModel::findById($tenantId);
        $hours    = !empty($tenant['business_hours']) ? json_decode((string) $tenant['business_hours'], true) : [];

        $this->view('settings.general', [
            'page'   => 'configuracion',
            'tab'    => 'general',
            'tenant' => $tenant,
            'hours'  => is_array($hours) ? $hours : [],
        ], 'layouts.app');
    }

    public function updateGeneral(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $request->only([
            'name','legal_name','tax_id','email','phone','country','currency','timezone','language',
            'website','industry','address','welcome_message','out_of_hours_msg'
        ]);

        // Business hours
        $hours = [];
        foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day) {
            $h = $request->input('hours.' . $day, null);
            if (is_array($h) || is_array($request->input('hours'))) {
                $allHours = $request->input('hours', []);
                $h = $allHours[$day] ?? null;
            }
            if (is_array($h)) {
                $hours[$day] = [
                    'enabled' => !empty($h['enabled']),
                    'start'   => $h['start'] ?? '09:00',
                    'end'     => $h['end']   ?? '18:00',
                ];
            }
        }
        $data['business_hours'] = json_encode($hours, JSON_UNESCAPED_UNICODE);

        Database::update('tenants', $data, ['id' => $tenantId]);
        Audit::log('settings.general', 'tenant', $tenantId, [], $data);

        Session::flash('success', 'Configuracion actualizada.');
        $this->redirect('/settings');
    }

    public function integrations(Request $request): void
    {
        $tenantId = Tenant::id();
        $tenant = TenantModel::findById($tenantId);
        $this->view('settings.integrations', [
            'page'   => 'configuracion',
            'tab'    => 'integrations',
            'tenant' => $tenant,
            'webhookUrl' => url('/webhooks/wasapi/' . ($tenant['uuid'] ?? '')),
        ], 'layouts.app');
    }

    public function updateIntegrations(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $request->only([
            'wasapi_api_key','wasapi_phone','wasapi_webhook',
            'resend_api_key','resend_from_email',
            'claude_api_key','claude_model',
        ]);
        Database::update('tenants', $data, ['id' => $tenantId]);
        Audit::log('settings.integrations', 'tenant', $tenantId);
        Session::flash('success', 'Integraciones actualizadas.');
        $this->redirect('/settings/integrations');
    }

    public function ai(Request $request): void
    {
        $tenantId = Tenant::id();
        $tenant = TenantModel::findById($tenantId);
        $this->view('settings.ai', [
            'page'      => 'configuracion',
            'tab'       => 'ai',
            'tenant'    => $tenant,
            'knowledge' => KnowledgeBase::listForTenant($tenantId),
        ], 'layouts.app');
    }

    public function updateAi(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $request->only(['ai_assistant_name','ai_tone','ai_enabled','out_of_hours_msg','welcome_message']);
        $data['ai_enabled'] = !empty($data['ai_enabled']) ? 1 : 0;
        Database::update('tenants', $data, ['id' => $tenantId]);
        Audit::log('settings.ai', 'tenant', $tenantId);
        Session::flash('success', 'Configuracion de IA actualizada.');
        $this->redirect('/settings/ai');
    }

    public function knowledgeStore(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'category' => 'required|max:80',
            'title'    => 'required|min:3|max:255',
            'content'  => 'required|min:5',
        ]);
        KnowledgeBase::create([
            'tenant_id'  => $tenantId,
            'category'   => $data['category'],
            'title'      => $data['title'],
            'content'    => $data['content'],
            'is_active'  => 1,
            'created_by' => Auth::id(),
        ]);
        Session::flash('success', 'Articulo agregado a la base de conocimiento.');
        $this->redirect('/settings/ai');
    }

    public function knowledgeDelete(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        KnowledgeBase::delete($tenantId, $id);
        Session::flash('success', 'Articulo eliminado.');
        $this->redirect('/settings/ai');
    }

    public function users(Request $request): void
    {
        $tenantId = Tenant::id();
        $users = Database::fetchAll(
            "SELECT u.*, GROUP_CONCAT(r.name) AS roles
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.tenant_id = :t AND u.deleted_at IS NULL
             GROUP BY u.id ORDER BY u.created_at DESC",
            ['t' => $tenantId]
        );
        $this->view('settings.users', [
            'page'  => 'configuracion',
            'tab'   => 'users',
            'users' => $users,
            'roles' => Database::fetchAll("SELECT * FROM roles WHERE slug != 'super_admin' ORDER BY id ASC"),
        ], 'layouts.app');
    }

    public function inviteUser(Request $request): void
    {
        $tenantId = Tenant::id();
        $tenant   = TenantModel::findById($tenantId);
        $data = $this->validate($request, [
            'first_name' => 'required|min:2',
            'last_name'  => 'required|min:2',
            'email'      => 'required|email',
            'role_id'    => 'required|integer',
        ]);

        if (User::emailExists($data['email'])) {
            Session::flash('error', 'El email ya esta registrado en el sistema.');
            $this->redirect('/settings/users');
            return;
        }

        $tempPass = bin2hex(random_bytes(6));
        $userId = User::createUser([
            'tenant_id'  => $tenantId,
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => strtolower($data['email']),
            'password'   => $tempPass,
            'is_active'  => 1,
        ]);
        User::assignRole($userId, (int) $data['role_id'], $tenantId);

        // Email invitacion
        $resend = new ResendService($tenantId);
        $user = User::findById($userId);
        if ($user) {
            $resend->sendInvitation($user, url('/login') . '?invited=' . urlencode($data['email']), (string) ($tenant['name'] ?? ''));
        }

        Audit::log('user.invited', 'user', $userId, [], ['email' => $data['email']]);
        Session::flash('success', "Usuario invitado. Password temporal: $tempPass (compartelo seguramente).");
        $this->redirect('/settings/users');
    }

    public function quickReplies(Request $request): void
    {
        $tenantId = Tenant::id();
        $this->view('settings.quick_replies', [
            'page'    => 'configuracion',
            'tab'     => 'quick_replies',
            'replies' => QuickReply::listForTenant($tenantId),
        ], 'layouts.app');
    }

    public function quickReplyStore(Request $request): void
    {
        $tenantId = Tenant::id();
        $data = $this->validate($request, [
            'shortcut' => 'required|max:60',
            'body'     => 'required|min:2',
        ]);
        QuickReply::create([
            'tenant_id'  => $tenantId,
            'shortcut'   => $data['shortcut'],
            'title'      => $data['title'] ?? null,
            'body'       => $data['body'],
            'created_by' => Auth::id(),
        ]);
        Session::flash('success', 'Respuesta rapida creada.');
        $this->redirect('/settings/quick-replies');
    }

    public function quickReplyDelete(Request $request, array $params): void
    {
        $tenantId = Tenant::id();
        $id = (int) ($params['id'] ?? 0);
        QuickReply::delete($tenantId, $id);
        Session::flash('success', 'Respuesta eliminada.');
        $this->redirect('/settings/quick-replies');
    }

    public function profile(Request $request): void
    {
        $this->view('settings.profile', [
            'page' => 'configuracion',
            'tab'  => 'profile',
            'user' => Auth::user(),
        ], 'layouts.app');
    }

    public function updateProfile(Request $request): void
    {
        $userId = (int) Auth::id();
        $data = $request->only(['first_name','last_name','phone','language','timezone','signature']);
        Database::update('users', $data, ['id' => $userId]);

        // Cambio de contrasena opcional
        if (!empty($request->input('new_password'))) {
            $pass = (string) $request->input('new_password');
            $confirm = (string) $request->input('new_password_confirmation');
            if (mb_strlen($pass) >= 8 && $pass === $confirm) {
                User::updatePassword($userId, $pass);
            } else {
                Session::flash('error', 'Las contrasenas no coinciden o son menores a 8 caracteres.');
                $this->redirect('/settings/profile');
                return;
            }
        }

        Session::flash('success', 'Perfil actualizado.');
        $this->redirect('/settings/profile');
    }
}
