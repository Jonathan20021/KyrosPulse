<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Models\Changelog;
use App\Models\GlobalAiProvider;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SaasSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiProviderService;
use App\Services\HttpClient;

final class AdminController extends Controller
{
    public function dashboard(Request $request): void
    {
        $stats = [
            'tenants_total'    => (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL"),
            'tenants_active'   => (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'active' AND deleted_at IS NULL"),
            'tenants_trial'    => (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'trial' AND deleted_at IS NULL"),
            'tenants_suspended'=> (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'suspended' AND deleted_at IS NULL"),
            'users_total'      => (int) Database::fetchColumn("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL"),
            'contacts_total'   => (int) Database::fetchColumn("SELECT COUNT(*) FROM contacts WHERE deleted_at IS NULL"),
            'messages_30d'     => (int) Database::fetchColumn("SELECT COUNT(*) FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'messages_24h'     => (int) Database::fetchColumn("SELECT COUNT(*) FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
            'campaigns_total'  => (int) Database::fetchColumn("SELECT COUNT(*) FROM campaigns"),
            'mrr'              => (float) Database::fetchColumn(
                "SELECT COALESCE(SUM(p.price_monthly), 0)
                 FROM tenants t
                 INNER JOIN plans p ON p.id = t.plan_id
                 WHERE t.status = 'active' AND t.deleted_at IS NULL"
            ),
            'new_signups_7d'   => (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND deleted_at IS NULL"),
            'churn_30d'        => (int) Database::fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'cancelled' AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
        ];

        $byPlan = Database::fetchAll(
            "SELECT p.name, p.price_monthly, COUNT(t.id) AS total
             FROM plans p
             LEFT JOIN tenants t ON t.plan_id = p.id AND t.deleted_at IS NULL
             GROUP BY p.id ORDER BY p.sort_order ASC"
        );

        $recentTenants = Database::fetchAll(
            "SELECT t.*, p.name AS plan_name FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.deleted_at IS NULL ORDER BY t.created_at DESC LIMIT 10"
        );

        $errors = Database::fetchAll(
            "SELECT * FROM whatsapp_logs WHERE success = 0 ORDER BY created_at DESC LIMIT 10"
        );

        // Volumen de mensajes por dia (ultimos 14 dias) global
        $messagesChart = $this->buildAdminChart();

        // Top tenants por uso de mensajes
        $topTenants = Database::fetchAll(
            "SELECT t.id, t.name, t.email, COUNT(m.id) AS msg_count
             FROM tenants t
             LEFT JOIN messages m ON m.tenant_id = t.id AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             WHERE t.deleted_at IS NULL
             GROUP BY t.id
             ORDER BY msg_count DESC
             LIMIT 8"
        );

        $this->view('admin.dashboard', [
            'page'           => 'admin',
            'stats'          => $stats,
            'byPlan'         => $byPlan,
            'recentTenants'  => $recentTenants,
            'errors'         => $errors,
            'messagesChart'  => $messagesChart,
            'topTenants'     => $topTenants,
        ], 'layouts.admin');
    }

    private function buildAdminChart(): array
    {
        $rows = Database::fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS total
             FROM messages
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(created_at) ORDER BY d ASC"
        );
        $labels = []; $data = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            $found = array_filter($rows, fn($r) => $r['d'] === $date);
            $found = $found ? array_values($found)[0] : null;
            $data[] = $found ? (int) $found['total'] : 0;
        }
        return ['labels' => $labels, 'data' => $data];
    }

    public function tenants(Request $request): void
    {
        $tenants = Database::fetchAll(
            "SELECT t.*, p.name AS plan_name, gp.name AS global_ai_name, gp.provider AS global_ai_provider
             FROM tenants t
             LEFT JOIN plans p ON p.id = t.plan_id
             LEFT JOIN global_ai_providers gp ON gp.id = t.global_ai_provider_id
             WHERE t.deleted_at IS NULL
             ORDER BY t.created_at DESC"
        );
        $this->view('admin.tenants', [
            'page'      => 'admin',
            'tenants'   => $tenants,
            'plans'     => Plan::listActive(),
            'providers' => GlobalAiProvider::listActive(),
        ], 'layouts.admin');
    }

    public function tenantUpdate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = $request->only(['plan_id','status','expires_at']);
        if (empty($data['expires_at'])) $data['expires_at'] = null;
        Database::update('tenants', $data, ['id' => $id]);
        Audit::log('admin.tenant.updated', 'tenant', $id, [], $data);
        Session::flash('success', 'Empresa actualizada.');
        $this->redirect('/admin/tenants');
    }

    public function tenantSuspend(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        Database::update('tenants', ['status' => 'suspended'], ['id' => $id]);
        Session::flash('success', 'Empresa suspendida.');
        $this->redirect('/admin/tenants');
    }

    public function tenantActivate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        Database::update('tenants', ['status' => 'active'], ['id' => $id]);
        Session::flash('success', 'Empresa activada.');
        $this->redirect('/admin/tenants');
    }

    public function plans(Request $request): void
    {
        $plans = Database::fetchAll("SELECT * FROM plans ORDER BY sort_order ASC");
        $this->view('admin.plans', ['page' => 'admin', 'plans' => $plans], 'layouts.admin');
    }

    public function planUpdate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $data = $request->only([
            'name','description','price_monthly','price_yearly',
            'max_users','max_contacts','max_messages','max_campaigns','max_automations'
        ]);
        $data['ai_enabled']       = !empty($request->input('ai_enabled')) ? 1 : 0;
        $data['advanced_reports'] = !empty($request->input('advanced_reports')) ? 1 : 0;
        $data['api_access']       = !empty($request->input('api_access')) ? 1 : 0;
        $data['is_active']        = !empty($request->input('is_active')) ? 1 : 0;

        Database::update('plans', $data, ['id' => $id]);
        Session::flash('success', 'Plan actualizado.');
        $this->redirect('/admin/plans');
    }

    // -------------------------------------------------------------- Tenants extras
    public function tenantExtendTrial(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $days = max(1, min(180, (int) $request->input('days', 14)));
        $tenant = Tenant::findById($id);
        if (!$tenant) $this->abort(404);

        $current = !empty($tenant['trial_ends_at']) ? strtotime((string) $tenant['trial_ends_at']) : time();
        if ($current < time()) $current = time();
        $newDate = date('Y-m-d H:i:s', $current + $days * 86400);

        Database::update('tenants', ['trial_ends_at' => $newDate, 'status' => 'trial'], ['id' => $id]);
        Audit::log('admin.tenant.trial_extended', 'tenant', $id, [], ['days' => $days, 'trial_ends_at' => $newDate]);
        Session::flash('success', "Trial extendido $days dias hasta $newDate.");
        $this->redirect('/admin/tenants');
    }

    public function tenantSetExpiry(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $expires = (string) $request->input('expires_at', '');
        if (!Tenant::findById($id)) $this->abort(404);

        Database::update('tenants', ['expires_at' => $expires ?: null], ['id' => $id]);
        Audit::log('admin.tenant.expires_set', 'tenant', $id, [], ['expires_at' => $expires]);
        Session::flash('success', 'Vigencia de licencia actualizada.');
        $this->redirect('/admin/tenants');
    }

    public function tenantImpersonate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $tenant = Tenant::findById($id);
        if (!$tenant) $this->abort(404);

        // Buscamos un owner del tenant
        $owner = Database::fetch(
            "SELECT u.* FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.tenant_id = :t AND r.slug IN ('owner','admin') AND u.is_active = 1 AND u.deleted_at IS NULL
             ORDER BY r.id ASC LIMIT 1",
            ['t' => $id]
        );
        if (!$owner) {
            Session::flash('error', 'Este tenant no tiene un owner activo para impersonar.');
            $this->redirect('/admin/tenants');
            return;
        }

        Audit::log('admin.tenant.impersonate', 'tenant', $id, [], ['as_user' => (int) $owner['id']]);

        // Guardamos el id del super-admin original para poder volver
        Session::set('impersonator_id', \App\Core\Auth::id());
        Session::set('user_id', (int) $owner['id']);
        Session::flash('success', 'Sesion como ' . $owner['email']);
        $this->redirect('/dashboard');
    }

    // -------------------------------------------------------------- Changelog
    public function changelogIndex(Request $request): void
    {
        $entries = Changelog::listAll(200);
        $this->view('admin.changelog', [
            'page'       => 'admin',
            'entries'    => $entries,
            'categories' => Changelog::CATEGORIES,
        ], 'layouts.admin');
    }

    public function changelogStore(Request $request): void
    {
        $data = $this->validate($request, [
            'title' => 'required|min:3|max:180',
        ]);
        $publish = !empty($request->input('is_published'));
        Changelog::create([
            'version'      => trim((string) $request->input('version', '')) ?: null,
            'title'        => $data['title'],
            'slug'         => Changelog::uniqueSlug($data['title']),
            'category'     => $this->normalizeCategory((string) $request->input('category', 'feature')),
            'summary'      => trim((string) $request->input('summary', '')) ?: null,
            'body'         => trim((string) $request->input('body', '')),
            'tags'         => json_encode($this->parseTags((string) $request->input('tags', '')), JSON_UNESCAPED_UNICODE),
            'author'       => trim((string) $request->input('author', '')) ?: null,
            'is_published' => $publish ? 1 : 0,
            'is_featured'  => !empty($request->input('is_featured')) ? 1 : 0,
            'published_at' => $publish ? ($request->input('published_at') ?: date('Y-m-d H:i:s')) : null,
            'created_by'   => Auth::id(),
        ]);
        Session::flash('success', 'Entrada de changelog creada.');
        $this->redirect('/admin/changelog');
    }

    public function changelogUpdate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = Changelog::findById($id);
        if (!$existing) $this->abort(404);

        $title = trim((string) $request->input('title', $existing['title']));
        $publish = !empty($request->input('is_published'));
        $publishedAt = $request->input('published_at');
        if ($publish && empty($publishedAt)) {
            $publishedAt = $existing['published_at'] ?: date('Y-m-d H:i:s');
        }

        Changelog::update($id, [
            'version'      => trim((string) $request->input('version', '')) ?: null,
            'title'        => $title,
            'slug'         => $existing['slug'] ?: Changelog::uniqueSlug($title, $id),
            'category'     => $this->normalizeCategory((string) $request->input('category', $existing['category'] ?: 'feature')),
            'summary'      => trim((string) $request->input('summary', '')) ?: null,
            'body'         => trim((string) $request->input('body', '')),
            'tags'         => json_encode($this->parseTags((string) $request->input('tags', '')), JSON_UNESCAPED_UNICODE),
            'author'       => trim((string) $request->input('author', '')) ?: null,
            'is_published' => $publish ? 1 : 0,
            'is_featured'  => !empty($request->input('is_featured')) ? 1 : 0,
            'published_at' => $publish ? ($publishedAt ?: date('Y-m-d H:i:s')) : null,
        ]);
        Session::flash('success', 'Entrada actualizada.');
        $this->redirect('/admin/changelog');
    }

    public function changelogDelete(Request $request, array $params): void
    {
        Changelog::delete((int) ($params['id'] ?? 0));
        Session::flash('success', 'Entrada eliminada.');
        $this->redirect('/admin/changelog');
    }

    private function normalizeCategory(string $cat): string
    {
        $valid = array_keys(Changelog::CATEGORIES);
        return in_array($cat, $valid, true) ? $cat : 'feature';
    }

    private function parseTags(string $raw): array
    {
        if ($raw === '') return [];
        $parts = preg_split('/[,;\n]+/u', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && mb_strlen($p) <= 40) $out[] = $p;
        }
        return array_values(array_unique($out));
    }

    // -------------------------------------------------------------- Branding
    public function branding(Request $request): void
    {
        $this->view('admin.branding', [
            'page'     => 'admin',
            'settings' => SaasSetting::all(),
        ], 'layouts.admin');
    }

    public function brandingUpdate(Request $request): void
    {
        $stringKeys = [
            'brand_name','brand_tagline','hero_eyebrow','hero_headline','hero_sub',
            'cta_primary_label','cta_primary_url','cta_secondary_label','cta_secondary_url',
            'contact_email','contact_phone','contact_whatsapp',
            'social_x','social_linkedin','social_instagram','legal_company',
        ];
        foreach ($stringKeys as $k) {
            if ($request->input($k) !== null) {
                SaasSetting::set($k, (string) $request->input($k), 'string');
            }
        }
        foreach (['show_pricing','show_changelog'] as $k) {
            SaasSetting::set($k, $request->input($k) ? '1' : '0', 'bool');
        }
        Audit::log('admin.branding.updated', 'saas_settings', 0);
        Session::flash('success', 'Branding y landing actualizados.');
        $this->redirect('/admin/branding');
    }

    public function logs(Request $request): void
    {
        $audit = Database::fetchAll(
            "SELECT a.*, u.first_name, u.last_name, u.email
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT 200"
        );

        $whatsapp = Database::fetchAll(
            "SELECT wl.*, t.name AS tenant_name FROM whatsapp_logs wl
             LEFT JOIN tenants t ON t.id = wl.tenant_id
             ORDER BY wl.created_at DESC LIMIT 100"
        );

        $email = Database::fetchAll(
            "SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 100"
        );

        $ai = Database::fetchAll(
            "SELECT al.*, t.name AS tenant_name FROM ai_logs al
             LEFT JOIN tenants t ON t.id = al.tenant_id
             ORDER BY al.created_at DESC LIMIT 100"
        );

        $this->view('admin.logs', [
            'page'     => 'admin',
            'audit'    => $audit,
            'whatsapp' => $whatsapp,
            'email'    => $email,
            'ai'       => $ai,
        ], 'layouts.admin');
    }

    // -------------------------------------------------------------- AI providers globales
    public function aiProvidersIndex(Request $request): void
    {
        $providers = GlobalAiProvider::listAll();
        // Calcular consumo por provider
        foreach ($providers as &$p) {
            $p['_used_period'] = (int) (GlobalAiProvider::tokenUsageThisMonth((int) $p['id'])['used']);
            $p['_tenants_count'] = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM tenants WHERE global_ai_provider_id = :id AND deleted_at IS NULL",
                ['id' => (int) $p['id']]
            );
        }
        unset($p);

        $this->view('admin.ai_providers', [
            'page'      => 'admin',
            'providers' => $providers,
        ], 'layouts.admin');
    }

    public function aiProvidersStore(Request $request): void
    {
        $data = $this->validate($request, [
            'name'    => 'required|min:2|max:120',
            'api_key' => 'required',
            'model'   => 'required',
        ]);
        $providerType = strtolower((string) $request->input('provider', 'claude'));
        if (!in_array($providerType, ['claude','openai'], true)) $providerType = 'claude';

        GlobalAiProvider::create([
            'name'                => $data['name'],
            'provider'            => $providerType,
            'api_key'             => trim((string) $data['api_key']),
            'model'               => trim((string) $data['model']),
            'description'         => trim((string) $request->input('description', '')) ?: null,
            'is_active'           => !empty($request->input('is_active')) ? 1 : 0,
            'is_default'          => !empty($request->input('is_default')) ? 1 : 0,
            'priority'            => (int) ($request->input('priority') ?: 100),
            'monthly_token_limit' => $request->input('monthly_token_limit') !== '' ? (int) $request->input('monthly_token_limit') : null,
        ]);
        Audit::log('admin.ai_provider.created', 'global_ai_providers', 0);
        Session::flash('success', 'Proveedor IA global creado.');
        $this->redirect('/admin/ai-providers');
    }

    public function aiProvidersUpdate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = GlobalAiProvider::findById($id);
        if (!$existing) $this->abort(404);

        $providerType = strtolower((string) $request->input('provider', $existing['provider']));
        if (!in_array($providerType, ['claude','openai'], true)) $providerType = $existing['provider'];

        // Solo actualizamos api_key si llega un nuevo valor (no vaciar accidentalmente)
        $newKey = trim((string) $request->input('api_key', ''));
        $update = [
            'name'                => trim((string) $request->input('name', $existing['name'])),
            'provider'            => $providerType,
            'model'               => trim((string) $request->input('model', $existing['model'])),
            'description'         => trim((string) $request->input('description', '')) ?: null,
            'is_active'           => !empty($request->input('is_active')) ? 1 : 0,
            'is_default'          => !empty($request->input('is_default')) ? 1 : 0,
            'priority'            => (int) ($request->input('priority') ?: $existing['priority']),
            'monthly_token_limit' => $request->input('monthly_token_limit') !== '' ? (int) $request->input('monthly_token_limit') : null,
        ];
        if ($newKey !== '' && $newKey !== '••••••••') $update['api_key'] = $newKey;

        GlobalAiProvider::update($id, $update);
        Audit::log('admin.ai_provider.updated', 'global_ai_providers', $id);
        Session::flash('success', 'Proveedor IA actualizado.');
        $this->redirect('/admin/ai-providers');
    }

    public function aiProvidersDelete(Request $request, array $params): void
    {
        GlobalAiProvider::delete((int) ($params['id'] ?? 0));
        Session::flash('success', 'Proveedor IA eliminado y desasignado de los tenants.');
        $this->redirect('/admin/ai-providers');
    }

    public function aiProvidersTest(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $p = GlobalAiProvider::findById($id);
        if (!$p) { $this->json(['success' => false, 'error' => 'No encontrado.']); return; }

        try {
            if ($p['provider'] === 'openai') {
                $resp = HttpClient::post(
                    (string) config('services.openai.api_url', 'https://api.openai.com/v1/chat/completions'),
                    [
                        'model'      => $p['model'],
                        'messages'   => [['role' => 'user', 'content' => 'OK']],
                        'max_tokens' => 5,
                    ],
                    ['Authorization' => 'Bearer ' . $p['api_key'], 'Content-Type' => 'application/json'],
                    20
                );
            } else {
                $resp = HttpClient::post(
                    (string) config('services.claude.api_url'),
                    [
                        'model'      => $p['model'],
                        'max_tokens' => 5,
                        'messages'   => [['role' => 'user', 'content' => 'Responde OK']],
                    ],
                    [
                        'x-api-key'         => $p['api_key'],
                        'anthropic-version' => (string) config('services.claude.version', '2023-06-01'),
                        'Content-Type'      => 'application/json',
                    ],
                    20
                );
            }

            if (!empty($resp['success'])) {
                $this->json(['success' => true, 'message' => 'Conexion OK con ' . strtoupper($p['provider'])]);
                return;
            }
            $this->json(['success' => false, 'error' => $resp['body']['error']['message'] ?? $resp['error'] ?? 'Fallo']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------- Crear tenant + owner desde admin
    public function tenantCreateForm(Request $request): void
    {
        $this->view('admin.tenant_create', [
            'page'      => 'admin',
            'plans'     => Plan::listActive(),
            'providers' => GlobalAiProvider::listActive(),
        ], 'layouts.admin');
    }

    public function tenantCreate(Request $request): void
    {
        $data = $this->validate($request, [
            'company_name' => 'required|min:2|max:150',
            'company_email'=> 'required|email',
            'owner_first'  => 'required|min:2',
            'owner_last'   => 'required|min:2',
            'owner_email'  => 'required|email',
        ]);

        if (User::emailExists($data['owner_email'])) {
            Session::flash('error', 'El email del owner ya esta registrado.');
            $this->redirect('/admin/tenants/create');
            return;
        }

        $planId = $request->input('plan_id') ? (int) $request->input('plan_id') : null;
        $status = (string) $request->input('status', 'trial');
        if (!in_array($status, ['trial','active','suspended','cancelled','expired'], true)) $status = 'trial';

        $trialDays = (int) ($request->input('trial_days') ?: 14);
        $globalProviderId = $request->input('global_ai_provider_id') ? (int) $request->input('global_ai_provider_id') : null;
        $tokenQuota = $request->input('ai_token_quota') !== '' ? (int) $request->input('ai_token_quota') : null;

        $slug = Tenant::generateUniqueSlug($data['company_name']);
        $tenantId = Tenant::create([
            'slug'                  => $slug,
            'name'                  => $data['company_name'],
            'email'                 => strtolower(trim($data['company_email'])),
            'phone'                 => trim((string) $request->input('company_phone', '')) ?: null,
            'country'               => (string) ($request->input('country') ?: 'DO'),
            'currency'              => (string) ($request->input('currency') ?: 'USD'),
            'timezone'              => (string) ($request->input('timezone') ?: 'America/Santo_Domingo'),
            'language'              => (string) ($request->input('language') ?: 'es'),
            'plan_id'               => $planId,
            'status'                => $status,
            'trial_ends_at'         => $status === 'trial' ? date('Y-m-d H:i:s', time() + $trialDays * 86400) : null,
            'ai_provider'           => 'claude',
            'global_ai_provider_id' => $globalProviderId,
            'ai_token_quota'        => $tokenQuota,
            'ai_enabled'            => $globalProviderId ? 1 : 0,
        ]);

        // Crear owner
        $tempPass = trim((string) $request->input('owner_password', ''));
        if ($tempPass === '' || strlen($tempPass) < 8) {
            $tempPass = bin2hex(random_bytes(6));
        }
        $userId = User::createUser([
            'tenant_id'  => $tenantId,
            'first_name' => trim((string) $data['owner_first']),
            'last_name'  => trim((string) $data['owner_last']),
            'email'      => strtolower(trim($data['owner_email'])),
            'password'   => $tempPass,
            'is_active'  => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
        $ownerRole = Database::fetch("SELECT id FROM roles WHERE slug = 'owner' LIMIT 1");
        if ($ownerRole) {
            User::assignRole($userId, (int) $ownerRole['id'], $tenantId);
        }

        Audit::log('admin.tenant.created', 'tenant', $tenantId, [], [
            'owner_email' => $data['owner_email'],
            'plan_id'     => $planId,
        ]);

        Session::flash('success', "Empresa creada. Owner: {$data['owner_email']} · Password temporal: $tempPass");
        $this->redirect('/admin/tenants');
    }

    public function tenantAiAssign(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Tenant::findById($id)) $this->abort(404);

        $providerId = $request->input('global_ai_provider_id');
        $providerId = ($providerId === '' || $providerId === null) ? null : (int) $providerId;
        $quota = $request->input('ai_token_quota');
        $quota = ($quota === '' || $quota === null) ? null : max(0, (int) $quota);

        $update = [
            'global_ai_provider_id' => $providerId,
            'ai_token_quota'        => $quota,
        ];
        if (!empty($request->input('reset_period'))) {
            $update['ai_tokens_used_period']     = 0;
            $update['ai_token_period_starts_at'] = date('Y-m-01');
        }
        if (!empty($request->input('enable_ai'))) $update['ai_enabled'] = 1;

        Database::update('tenants', $update, ['id' => $id]);
        Audit::log('admin.tenant.ai_assigned', 'tenant', $id, [], $update);
        Session::flash('success', 'Configuracion IA del tenant actualizada.');
        $this->redirect('/admin/tenants');
    }

    // -------------------------------------------------------------- Usuarios (todos los tenants)
    public function usersIndex(Request $request): void
    {
        $q = trim((string) $request->query('q', ''));
        $tenantFilter = $request->query('tenant') ? (int) $request->query('tenant') : null;

        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.email LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($tenantFilter) {
            $where[] = 'u.tenant_id = :t';
            $params['t'] = $tenantFilter;
        }

        $users = Database::fetchAll(
            "SELECT u.*, t.name AS tenant_name, GROUP_CONCAT(r.slug) AS role_slugs
             FROM users u
             LEFT JOIN tenants t ON t.id = u.tenant_id
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT 200",
            $params
        );

        $this->view('admin.users', [
            'page'    => 'admin',
            'users'   => $users,
            'q'       => $q,
            'tenants' => Tenant::listAll(),
            'tenantFilter' => $tenantFilter,
        ], 'layouts.admin');
    }

    public function userToggleActive(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $u = User::findById($id);
        if (!$u) $this->abort(404);
        Database::update('users', ['is_active' => empty($u['is_active']) ? 1 : 0], ['id' => $id]);
        Audit::log('admin.user.toggle_active', 'user', $id);
        Session::flash('success', 'Estado del usuario actualizado.');
        $this->redirect('/admin/users');
    }

    public function userResetPassword(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $u = User::findById($id);
        if (!$u) $this->abort(404);
        $tempPass = bin2hex(random_bytes(6));
        User::updatePassword($id, $tempPass);
        Audit::log('admin.user.password_reset', 'user', $id);
        Session::flash('success', "Nueva password temporal para {$u['email']}: $tempPass");
        $this->redirect('/admin/users');
    }

    public function userDelete(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        Database::update('users', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        Audit::log('admin.user.deleted', 'user', $id);
        Session::flash('success', 'Usuario eliminado (soft delete).');
        $this->redirect('/admin/users');
    }

    /**
     * Ejecuta el seeder de BBQ MeatHouse contra el tenant demo. Solo super admin.
     * Reaplica menu, zonas y conocimiento (idempotente: borra menu previo del tenant demo).
     */
    public function seedDemoRestaurant(Request $request): void
    {
        $script = dirname(__DIR__, 2) . '/database/seed_bbq_meathouse.php';
        if (!is_file($script)) {
            Session::flash('error', 'Seeder no encontrado en: ' . $script);
            $this->redirect('/admin');
            return;
        }

        ob_start();
        try {
            require $script;
        } catch (\Throwable $e) {
            ob_end_clean();
            Audit::log('admin.seed_demo.failed', 'tenant', null, [], ['msg' => $e->getMessage()]);
            Session::flash('error', 'El seeder fallo: ' . $e->getMessage());
            $this->redirect('/admin');
            return;
        }
        $output = (string) ob_get_clean();

        Audit::log('admin.seed_demo.ok', 'tenant', null);
        Session::flash('success', 'Demo BBQ MeatHouse re-cargado: ' . mb_substr(strip_tags($output), 0, 1200));
        $this->redirect('/admin');
    }
}
