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
use App\Controllers\ContactController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\InboxController;
use App\Controllers\LeadController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\TaskController;
use App\Controllers\TicketController;

// ------------------- Publico -------------------
$router->get('/', [HomeController::class, 'index']);

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

    // Tickets
    $r->get   ('/tickets',           [TicketController::class, 'index']);
    $r->get   ('/tickets/create',    [TicketController::class, 'create']);
    $r->post  ('/tickets',           [TicketController::class, 'store'])->middleware('csrf');
    $r->get   ('/tickets/{id}',      [TicketController::class, 'show']);
    $r->put   ('/tickets/{id}',      [TicketController::class, 'update'])->middleware('csrf');
    $r->post  ('/tickets/{id}/comment', [TicketController::class, 'comment'])->middleware('csrf');
    $r->delete('/tickets/{id}',      [TicketController::class, 'destroy'])->middleware('csrf');

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
    $r->get('/settings/integrations',     [SettingsController::class, 'integrations']);
    $r->put('/settings/integrations',     [SettingsController::class, 'updateIntegrations'])->middleware('csrf');
    $r->post('/settings/integrations/wasapi/templates/sync', [SettingsController::class, 'syncWasapiTemplates'])->middleware('csrf');
    $r->get('/settings/ai',               [SettingsController::class, 'ai']);
    $r->put('/settings/ai',               [SettingsController::class, 'updateAi'])->middleware('csrf');
    $r->post('/settings/ai/agents',       [SettingsController::class, 'aiAgentStore'])->middleware('csrf');
    $r->post('/settings/ai/agents/{id}/toggle', [SettingsController::class, 'aiAgentToggle'])->middleware('csrf');
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
    $r->get('/admin/tenants',               [AdminController::class, 'tenants']);
    $r->put('/admin/tenants/{id}',          [AdminController::class, 'tenantUpdate'])->middleware('csrf');
    $r->post('/admin/tenants/{id}/suspend', [AdminController::class, 'tenantSuspend'])->middleware('csrf');
    $r->post('/admin/tenants/{id}/activate',[AdminController::class, 'tenantActivate'])->middleware('csrf');
    $r->get('/admin/plans',                 [AdminController::class, 'plans']);
    $r->put('/admin/plans/{id}',            [AdminController::class, 'planUpdate'])->middleware('csrf');
    $r->get('/admin/logs',                  [AdminController::class, 'logs']);
});
