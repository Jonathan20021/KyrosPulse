<?php
/**
 * Rutas API y webhooks externos.
 *
 * @var \App\Core\Router $router
 */
declare(strict_types=1);

use App\Controllers\WebhookController;
use App\Controllers\Api\ApiAgentController;
use App\Controllers\Api\ApiContactController;
use App\Controllers\Api\ApiDashboardController;
use App\Controllers\Api\ApiMessageController;
use App\Controllers\Api\ApiMetaController;
use App\Controllers\Api\ApiOrderController;
use App\Controllers\Api\ApiWebhookController;
use App\Controllers\Api\ApiWorkflowTemplateController;

// =====================================================================
// Webhooks entrantes (no requieren API key — usan firma del proveedor)
// =====================================================================
$router->post('/webhooks/wasapi/{tenant_uuid}', [WebhookController::class, 'wasapi'])
    ->middleware('rate:api');

$router->get ('/webhooks/cloud/{channel_uuid}', [WebhookController::class, 'cloudApi']);
$router->post('/webhooks/cloud/{channel_uuid}', [WebhookController::class, 'cloudApi'])
    ->middleware('rate:api');

$router->post('/webhooks/integration/{slug}/{tenant_uuid}', [WebhookController::class, 'integration'])
    ->middleware('rate:api');

// Cron interno protegido por X-Cron-Token
$router->post('/cron/sales-bot', [\App\Controllers\CronController::class, 'salesBot']);
$router->get ('/cron/sales-bot', [\App\Controllers\CronController::class, 'salesBot']);

// Cron: reintentos de webhooks salientes pendientes (cada 1 min)
$router->post('/cron/webhooks-retry', [\App\Controllers\CronController::class, 'webhooksRetry']);
$router->get ('/cron/webhooks-retry', [\App\Controllers\CronController::class, 'webhooksRetry']);

// Cron: tick del workflow engine (delays + schedule triggers; cada 1 min)
$router->post('/cron/workflow-tick', [\App\Controllers\CronController::class, 'workflowTick']);
$router->get ('/cron/workflow-tick', [\App\Controllers\CronController::class, 'workflowTick']);

// Cron: evalua reglas de alerta pasivas (webhook.dead, agent.error_rate, workflow.failed)
$router->post('/cron/evaluate-alerts', [\App\Controllers\CronController::class, 'evaluateAlerts']);
$router->get ('/cron/evaluate-alerts', [\App\Controllers\CronController::class, 'evaluateAlerts']);

// Trigger publico de workflows con type=webhook. URL unica por workflow.
$router->post('/workflows/run/{token}', [\App\Controllers\WorkflowTriggerController::class, 'trigger'])
    ->middleware('rate:api');

// =====================================================================
// API publica v1 (Agent-as-a-Service)
// =====================================================================
// Auth: Authorization: Bearer kp_live_xxxxx
// Todas las rutas devuelven envelope: { data: ..., meta: {...} } o { error: {...} }
// X-Request-Id en headers de respuesta para correlacion.
// =====================================================================

// Endpoints publicos (sin auth)
$router->get('/api/v1/status',  [ApiMetaController::class, 'status']);
$router->get('/api/v1/health',  [ApiMetaController::class, 'health']);
$router->get('/api/v1/openapi', [ApiMetaController::class, 'openapi']);

$router->group(['prefix' => 'api/v1', 'middleware' => ['rate:api']], function ($router) {

    // Meta — requiere auth pero no scope especifico
    $router->get('/me',     [ApiMetaController::class, 'me'])->middleware('apikey');
    $router->get('/scopes', [ApiMetaController::class, 'scopes'])->middleware('apikey');

    // Dashboard ejecutivo cross-feature (JSON)
    $router->get('/dashboard/executive', [ApiDashboardController::class, 'executive'])->middleware('apikey:dashboard.read');

    // Agents (Agent-as-a-Service core)
    $router->get ('/agents',              [ApiAgentController::class, 'index'])  ->middleware('apikey:agents.read');
    $router->get ('/agents/runs',         [ApiAgentController::class, 'listRuns'])->middleware('apikey:agents.read');
    $router->get ('/agents/runs/{uuid}',  [ApiAgentController::class, 'showRun']) ->middleware('apikey:agents.read');
    $router->get ('/agents/{id}',         [ApiAgentController::class, 'show'])    ->middleware('apikey:agents.read');
    $router->post('/agents/{id}/run',     [ApiAgentController::class, 'run'])     ->middleware('apikey:agents.run');

    // Hub de Skills componibles (sales+support+cobranza+...)
    $router->get   ('/skills',                       [ApiAgentController::class, 'listSkills'])      ->middleware('apikey:agents.read');
    $router->get   ('/agents/{id}/skills',           [ApiAgentController::class, 'listAgentSkills']) ->middleware('apikey:agents.read');
    $router->post  ('/agents/{id}/skills',           [ApiAgentController::class, 'attachSkill'])     ->middleware('apikey:agents.write');
    $router->delete('/agents/{id}/skills/{slug}',    [ApiAgentController::class, 'detachSkill'])     ->middleware('apikey:agents.write');

    // Contacts
    $router->get   ('/contacts',         [ApiContactController::class, 'index'])  ->middleware('apikey:contacts.read');
    $router->post  ('/contacts',         [ApiContactController::class, 'store'])  ->middleware('apikey:contacts.write');
    $router->get   ('/contacts/{id}',    [ApiContactController::class, 'show'])   ->middleware('apikey:contacts.read');
    $router->patch ('/contacts/{id}',    [ApiContactController::class, 'update']) ->middleware('apikey:contacts.write');
    $router->delete('/contacts/{id}',    [ApiContactController::class, 'destroy'])->middleware('apikey:contacts.write');

    // Orders
    $router->get  ('/orders',              [ApiOrderController::class, 'index']) ->middleware('apikey:orders.read');
    $router->get  ('/orders/{id}',         [ApiOrderController::class, 'show'])  ->middleware('apikey:orders.read');
    $router->patch('/orders/{id}/status',  [ApiOrderController::class, 'updateStatus'])->middleware('apikey:orders.write');

    // Messages (WhatsApp)
    $router->get ('/messages', [ApiMessageController::class, 'index'])->middleware('apikey:messages.send');
    $router->post('/messages', [ApiMessageController::class, 'send']) ->middleware('apikey:messages.send');

    // Workflow templates marketplace
    $router->get  ('/workflow-templates',            [ApiWorkflowTemplateController::class, 'index'])->middleware('apikey:agents.read');
    $router->get  ('/workflow-templates/{id}',       [ApiWorkflowTemplateController::class, 'show']) ->middleware('apikey:agents.read');
    $router->post ('/workflow-templates/{id}/use',   [ApiWorkflowTemplateController::class, 'use'])  ->middleware('apikey:agents.write');

    // Webhooks salientes (subscripciones a eventos del sistema con HMAC)
    $router->get   ('/webhooks',                              [ApiWebhookController::class, 'index'])     ->middleware('apikey:webhooks.read');
    $router->post  ('/webhooks',                              [ApiWebhookController::class, 'store'])     ->middleware('apikey:webhooks.write');
    $router->get   ('/webhooks/{id}',                         [ApiWebhookController::class, 'show'])      ->middleware('apikey:webhooks.read');
    $router->patch ('/webhooks/{id}',                         [ApiWebhookController::class, 'update'])    ->middleware('apikey:webhooks.write');
    $router->delete('/webhooks/{id}',                         [ApiWebhookController::class, 'destroy'])   ->middleware('apikey:webhooks.write');
    $router->get   ('/webhooks/{id}/deliveries',              [ApiWebhookController::class, 'deliveries'])->middleware('apikey:webhooks.read');
    $router->post  ('/webhooks/deliveries/{uuid}/replay',     [ApiWebhookController::class, 'replay'])    ->middleware('apikey:webhooks.write');
});
