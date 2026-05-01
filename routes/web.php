<?php
/**
 * Rutas web (con sesion + CSRF en formularios).
 *
 * @var \App\Core\Router $router
 */
declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\AutomationController;
use App\Controllers\CampaignController;
use App\Controllers\ChangelogController;
use App\Controllers\ChannelController;
use App\Controllers\ContactController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\InboxController;
use App\Controllers\IntegrationController;
use App\Controllers\LeadController;
use App\Controllers\MenuController;
use App\Controllers\OrderController;
use App\Controllers\ProductController;
use App\Controllers\ReportController;
use App\Controllers\RestaurantController;
use App\Controllers\RoutingController;
use App\Controllers\SettingsController;
use App\Controllers\TaskController;
use App\Controllers\TicketController;

// ------------------- Publico -------------------
$router->get('/', [HomeController::class, 'index']);
$router->get('/changelog', [ChangelogController::class, 'publicIndex']);

// ------------------- Auth (guest) -------------------
$router->group(['middleware' => ['guest']], function ($r) {
    $r->get('/login', [AuthController::class, 'showLogin']);
    $r->post('/login', [AuthController::class, 'login'])->middleware(['csrf', 'rate:login']);

    $r->get('/register', [AuthController::class, 'showRegister']);
    $r->post('/register', [AuthController::class, 'register'])->middleware(['csrf', 'rate:login']);

    $r->get('/forgot-password', [AuthController::class, 'showForgot']);
    $r->post('/forgot-password', [AuthController::class, 'forgot'])->middleware(['csrf', 'rate:login']);

    $r->get('/reset-password', [AuthController::class, 'showReset']);
    $r->post('/reset-password', [AuthController::class, 'reset'])->middleware('csrf');
});

// ------------------- Verificacion email -------------------
$router->get('/email/verify', [AuthController::class, 'verifyEmail']);
$router->get('/email/verify-notice', [AuthController::class, 'showVerifyNotice'])->middleware('auth');
$router->post('/email/verify-resend', [AuthController::class, 'resendVerification'])->middleware(['auth', 'csrf']);

// ------------------- Logout -------------------
$router->post('/logout', [AuthController::class, 'logout'])->middleware(['auth', 'csrf']);

// ------------------- App (autenticado + tenant) -------------------
$router->group(['middleware' => ['auth', 'tenant']], function ($r) {
    // Dashboard
    $r->get('/dashboard', [DashboardController::class, 'index']);

    // Contactos
    $r->get   ('/contacts',         [ContactController::class, 'index']);
    $r->get   ('/contacts/create',  [ContactController::class, 'create']);
    $r->post  ('/contacts',         [ContactController::class, 'store'])->middleware('csrf');
    $r->get   ('/contacts/import',  [ContactController::class, 'importForm']);
    $r->post  ('/contacts/import',  [ContactController::class, 'importCsv'])->middleware('csrf');
    $r->get   ('/contacts/export',  [ContactController::class, 'exportCsv']);
    $r->get   ('/contacts/{id}',    [ContactController::class, 'show']);
    $r->get   ('/contacts/{id}/edit', [ContactController::class, 'edit']);
    $r->put   ('/contacts/{id}',    [ContactController::class, 'update'])->middleware('csrf');
    $r->delete('/contacts/{id}',    [ContactController::class, 'destroy'])->middleware('csrf');
    $r->post  ('/contacts/{id}/assign', [ContactController::class, 'assign'])->middleware('csrf');

    // Leads
    $r->get   ('/leads',         [LeadController::class, 'index']);
    $r->get   ('/leads/create',  [LeadController::class, 'create']);
    $r->post  ('/leads',         [LeadController::class, 'store'])->middleware('csrf');
    $r->post  ('/leads/move',    [LeadController::class, 'move']);
    $r->get   ('/leads/{id}',    [LeadController::class, 'show']);
    $r->put   ('/leads/{id}',    [LeadController::class, 'update'])->middleware('csrf');
    $r->delete('/leads/{id}',    [LeadController::class, 'destroy'])->middleware('csrf');
    $r->post  ('/leads/{id}/ai', [LeadController::class, 'aiRecommend']);

    // Inbox
    $r->get   ('/inbox',                    [InboxController::class, 'index']);
    $r->get   ('/inbox/live',               [InboxController::class, 'live']);
    $r->get   ('/inbox/{id}/messages',      [InboxController::class, 'messages']);
    $r->get   ('/inbox/{id}',               [InboxController::class, 'index']);
    $r->post  ('/inbox/{id}/send',          [InboxController::class, 'send']);
    $r->post  ('/inbox/{id}/assign',        [InboxController::class, 'assign'])->middleware('csrf');
    $r->post  ('/inbox/{id}/close',         [InboxController::class, 'close'])->middleware('csrf');
    $r->post  ('/inbox/{id}/reopen',        [InboxController::class, 'reopen'])->middleware('csrf');
    $r->post  ('/inbox/{id}/star',          [InboxController::class, 'star']);
    $r->post  ('/inbox/{id}/ai',            [InboxController::class, 'aiSuggest']);
    $r->post  ('/inbox/{id}/ai/assign',     [InboxController::class, 'aiAssign']);
    $r->post  ('/inbox/{id}/ai/takeover',   [InboxController::class, 'aiTakeover']);
    $r->post  ('/inbox/{id}/ai/run',        [InboxController::class, 'aiRunNow']);

    // Tickets
    $r->get   ('/tickets',           [TicketController::class, 'index']);
    $r->get   ('/tickets/create',    [TicketController::class, 'create']);
    $r->post  ('/tickets',           [TicketController::class, 'store'])->middleware('csrf');
    $r->get   ('/tickets/{id}',      [TicketController::class, 'show']);
    $r->put   ('/tickets/{id}',      [TicketController::class, 'update'])->middleware('csrf');
    $r->post  ('/tickets/{id}/comment', [TicketController::class, 'comment'])->middleware('csrf');
    $r->delete('/tickets/{id}',      [TicketController::class, 'destroy'])->middleware('csrf');

    // ===== Restaurante: menu =====
    $r->get   ('/menu',                       [MenuController::class, 'index']);
    $r->post  ('/menu/categories',            [MenuController::class, 'categoryStore'])->middleware('csrf');
    $r->put   ('/menu/categories/{id}',       [MenuController::class, 'categoryUpdate'])->middleware('csrf');
    $r->delete('/menu/categories/{id}',       [MenuController::class, 'categoryDelete'])->middleware('csrf');
    $r->post  ('/menu/items',                 [MenuController::class, 'itemStore'])->middleware('csrf');
    $r->put   ('/menu/items/{id}',            [MenuController::class, 'itemUpdate'])->middleware('csrf');
    $r->post  ('/menu/items/{id}/toggle',     [MenuController::class, 'itemToggle'])->middleware('csrf');
    $r->delete('/menu/items/{id}',            [MenuController::class, 'itemDelete'])->middleware('csrf');

    // ===== Restaurante: ordenes =====
    $r->get   ('/orders',                     [OrderController::class, 'index']);
    $r->get   ('/orders/live',                [OrderController::class, 'liveSnapshot']);
    $r->get   ('/orders/recent',              [OrderController::class, 'recent']);
    $r->get   ('/orders/create',              [OrderController::class, 'create']);
    $r->post  ('/orders',                     [OrderController::class, 'store'])->middleware('csrf');
    $r->get   ('/orders/{id}',                [OrderController::class, 'show']);
    $r->post  ('/orders/{id}/status',         [OrderController::class, 'status'])->middleware('csrf');
    $r->post  ('/orders/{id}/cancel',         [OrderController::class, 'cancel'])->middleware('csrf');
    $r->post  ('/orders/{id}/notes',          [OrderController::class, 'notes'])->middleware('csrf');

    // ===== Restaurante: settings + zonas =====
    $r->get   ('/settings/restaurant',                  [RestaurantController::class, 'index']);
    $r->put   ('/settings/restaurant',                  [RestaurantController::class, 'update'])->middleware('csrf');
    $r->post  ('/settings/restaurant/zones',            [RestaurantController::class, 'zoneStore'])->middleware('csrf');
    $r->put   ('/settings/restaurant/zones/{id}',       [RestaurantController::class, 'zoneUpdate'])->middleware('csrf');
    $r->delete('/settings/restaurant/zones/{id}',       [RestaurantController::class, 'zoneDelete'])->middleware('csrf');

    // Productos / Catalogo
    $r->get   ('/products',         [ProductController::class, 'index']);
    $r->post  ('/products',         [ProductController::class, 'store'])->middleware('csrf');
    $r->put   ('/products/{id}',    [ProductController::class, 'update'])->middleware('csrf');
    $r->delete('/products/{id}',    [ProductController::class, 'destroy'])->middleware('csrf');

    // Tareas
    $r->get   ('/tasks',           [TaskController::class, 'index']);
    $r->post  ('/tasks',           [TaskController::class, 'store'])->middleware('csrf');
    $r->post  ('/tasks/{id}/complete', [TaskController::class, 'complete'])->middleware('csrf');
    $r->delete('/tasks/{id}',      [TaskController::class, 'destroy'])->middleware('csrf');

    // Campanas
    $r->get   ('/campaigns',          [CampaignController::class, 'index']);
    $r->get   ('/campaigns/create',   [CampaignController::class, 'create']);
    $r->post  ('/campaigns',          [CampaignController::class, 'store'])->middleware('csrf');
    $r->get   ('/campaigns/{id}',     [CampaignController::class, 'show']);
    $r->post  ('/campaigns/{id}/send',[CampaignController::class, 'send'])->middleware('csrf');
    $r->delete('/campaigns/{id}',     [CampaignController::class, 'destroy'])->middleware('csrf');

    // Automatizaciones
    $r->get   ('/automations',         [AutomationController::class, 'index']);
    $r->get   ('/automations/create',  [AutomationController::class, 'create']);
    $r->post  ('/automations',         [AutomationController::class, 'store'])->middleware('csrf');
    $r->get   ('/automations/{id}',    [AutomationController::class, 'edit']);
    $r->put   ('/automations/{id}',    [AutomationController::class, 'update'])->middleware('csrf');
    $r->post  ('/automations/{id}/toggle', [AutomationController::class, 'toggle'])->middleware('csrf');
    $r->delete('/automations/{id}',    [AutomationController::class, 'destroy'])->middleware('csrf');

    // Reportes
    $r->get   ('/reports',         [ReportController::class, 'index']);
    $r->get   ('/reports/export',  [ReportController::class, 'exportCsv']);

    // Settings
    $r->get('/settings',                  [SettingsController::class, 'general']);
    $r->put('/settings',                  [SettingsController::class, 'updateGeneral'])->middleware('csrf');

    // Settings · Integraciones core (Wasapi default + IA)
    $r->get('/settings/integrations/core',     [SettingsController::class, 'integrations']);
    $r->put('/settings/integrations/core',     [SettingsController::class, 'updateIntegrations'])->middleware('csrf');
    $r->post('/settings/integrations/test-ai', [SettingsController::class, 'testAi'])->middleware('csrf');
    $r->post('/settings/integrations/wasapi/templates/sync', [SettingsController::class, 'syncWasapiTemplates'])->middleware('csrf');
    $r->post('/settings/integrations/wasapi/contacts/sync',  [SettingsController::class, 'syncWasapiContactNames'])->middleware('csrf');

    // Settings · Catalogo de integraciones (Slack, HubSpot, Stripe, etc.)
    $r->get   ('/settings/integrations',                [IntegrationController::class, 'index']);
    $r->get   ('/settings/integrations/{slug}',         [IntegrationController::class, 'show']);
    $r->post  ('/settings/integrations/{slug}/connect', [IntegrationController::class, 'connect'])->middleware('csrf');
    $r->post  ('/settings/integrations/{slug}/disconnect', [IntegrationController::class, 'disconnect'])->middleware('csrf');
    $r->post  ('/settings/integrations/{slug}/test',    [IntegrationController::class, 'test'])->middleware('csrf');

    // Settings · Routing inteligente
    $r->get   ('/settings/routing',                  [RoutingController::class, 'index']);
    $r->post  ('/settings/routing',                  [RoutingController::class, 'store'])->middleware('csrf');
    $r->put   ('/settings/routing/{id}',             [RoutingController::class, 'update'])->middleware('csrf');
    $r->post  ('/settings/routing/{id}/toggle',      [RoutingController::class, 'toggle'])->middleware('csrf');
    $r->delete('/settings/routing/{id}',             [RoutingController::class, 'destroy'])->middleware('csrf');

    // Settings · Canales WhatsApp (multi-numero)
    $r->get   ('/settings/channels',                  [ChannelController::class, 'index']);
    $r->post  ('/settings/channels',                  [ChannelController::class, 'store'])->middleware('csrf');
    $r->put   ('/settings/channels/{id}',             [ChannelController::class, 'update'])->middleware('csrf');
    $r->post  ('/settings/channels/{id}/default',     [ChannelController::class, 'setDefault'])->middleware('csrf');
    $r->post  ('/settings/channels/{id}/toggle',      [ChannelController::class, 'toggle'])->middleware('csrf');
    $r->post  ('/settings/channels/{id}/test',        [ChannelController::class, 'test'])->middleware('csrf');
    $r->delete('/settings/channels/{id}',             [ChannelController::class, 'destroy'])->middleware('csrf');
    $r->get('/settings/ai',               [SettingsController::class, 'ai']);
    $r->put('/settings/ai',               [SettingsController::class, 'updateAi'])->middleware('csrf');
    $r->post('/settings/ai/agents',       [SettingsController::class, 'aiAgentStore'])->middleware('csrf');
    $r->put ('/settings/ai/agents/{id}',  [SettingsController::class, 'aiAgentUpdate'])->middleware('csrf');
    $r->post('/settings/ai/agents/{id}/toggle', [SettingsController::class, 'aiAgentToggle'])->middleware('csrf');
    $r->post('/settings/ai/agents/{id}/duplicate', [SettingsController::class, 'aiAgentDuplicate'])->middleware('csrf');
    $r->delete('/settings/ai/agents/{id}', [SettingsController::class, 'aiAgentDelete'])->middleware('csrf');
    $r->post('/settings/ai/knowledge',    [SettingsController::class, 'knowledgeStore'])->middleware('csrf');
    $r->delete('/settings/ai/knowledge/{id}', [SettingsController::class, 'knowledgeDelete'])->middleware('csrf');
    $r->get('/settings/users',            [SettingsController::class, 'users']);
    $r->post('/settings/users/invite',    [SettingsController::class, 'inviteUser'])->middleware('csrf');
    $r->get('/settings/quick-replies',    [SettingsController::class, 'quickReplies']);
    $r->post('/settings/quick-replies',   [SettingsController::class, 'quickReplyStore'])->middleware('csrf');
    $r->delete('/settings/quick-replies/{id}', [SettingsController::class, 'quickReplyDelete'])->middleware('csrf');
    $r->get('/settings/profile',          [SettingsController::class, 'profile']);
    $r->put('/settings/profile',          [SettingsController::class, 'updateProfile'])->middleware('csrf');
});

// ------------------- Super Admin -------------------
$router->group(['middleware' => ['auth', 'super']], function ($r) {
    $r->get('/admin',                       [AdminController::class, 'dashboard']);

    // Empresas / licencias
    $r->get ('/admin/tenants',                       [AdminController::class, 'tenants']);
    $r->get ('/admin/tenants/create',                [AdminController::class, 'tenantCreateForm']);
    $r->post('/admin/tenants',                       [AdminController::class, 'tenantCreate'])->middleware('csrf');
    $r->put ('/admin/tenants/{id}',                  [AdminController::class, 'tenantUpdate'])->middleware('csrf');
    $r->post('/admin/tenants/{id}/suspend',          [AdminController::class, 'tenantSuspend'])->middleware('csrf');
    $r->post('/admin/tenants/{id}/activate',         [AdminController::class, 'tenantActivate'])->middleware('csrf');
    $r->post('/admin/tenants/{id}/extend-trial',     [AdminController::class, 'tenantExtendTrial'])->middleware('csrf');
    $r->post('/admin/tenants/{id}/expiry',           [AdminController::class, 'tenantSetExpiry'])->middleware('csrf');
    $r->post('/admin/tenants/{id}/impersonate',      [AdminController::class, 'tenantImpersonate'])->middleware('csrf');
    $r->post('/admin/tenants/{id}/ai-assign',        [AdminController::class, 'tenantAiAssign'])->middleware('csrf');

    // Usuarios (todos los tenants)
    $r->get   ('/admin/users',                  [AdminController::class, 'usersIndex']);
    $r->post  ('/admin/users/{id}/toggle',      [AdminController::class, 'userToggleActive'])->middleware('csrf');
    $r->post  ('/admin/users/{id}/reset-pass',  [AdminController::class, 'userResetPassword'])->middleware('csrf');
    $r->delete('/admin/users/{id}',             [AdminController::class, 'userDelete'])->middleware('csrf');

    // AI Providers globales
    $r->get   ('/admin/ai-providers',                [AdminController::class, 'aiProvidersIndex']);
    $r->post  ('/admin/ai-providers',                [AdminController::class, 'aiProvidersStore'])->middleware('csrf');
    $r->put   ('/admin/ai-providers/{id}',           [AdminController::class, 'aiProvidersUpdate'])->middleware('csrf');
    $r->delete('/admin/ai-providers/{id}',           [AdminController::class, 'aiProvidersDelete'])->middleware('csrf');
    $r->post  ('/admin/ai-providers/{id}/test',      [AdminController::class, 'aiProvidersTest'])->middleware('csrf');

    // Planes
    $r->get('/admin/plans',                 [AdminController::class, 'plans']);
    $r->put('/admin/plans/{id}',            [AdminController::class, 'planUpdate'])->middleware('csrf');

    // Changelog (administrable)
    $r->get   ('/admin/changelog',          [AdminController::class, 'changelogIndex']);
    $r->post  ('/admin/changelog',          [AdminController::class, 'changelogStore'])->middleware('csrf');
    $r->put   ('/admin/changelog/{id}',     [AdminController::class, 'changelogUpdate'])->middleware('csrf');
    $r->delete('/admin/changelog/{id}',     [AdminController::class, 'changelogDelete'])->middleware('csrf');

    // Branding / landing
    $r->get('/admin/branding',              [AdminController::class, 'branding']);
    $r->put('/admin/branding',              [AdminController::class, 'brandingUpdate'])->middleware('csrf');

    // Logs
    $r->get('/admin/logs',                  [AdminController::class, 'logs']);

    // Seeder demo restaurante (BBQ MeatHouse)
    $r->post('/admin/seed/bbq-meathouse',   [AdminController::class, 'seedDemoRestaurant'])->middleware('csrf');
});
