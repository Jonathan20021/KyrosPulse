<?php
/**
 * Rutas API y webhooks externos.
 *
 * @var \App\Core\Router $router
 */
declare(strict_types=1);

use App\Controllers\WebhookController;

$router->post('/webhooks/wasapi/{tenant_uuid}', [WebhookController::class, 'wasapi'])
    ->middleware('rate:api');

// WhatsApp Cloud API (Meta) — handshake (GET) + eventos (POST)
$router->get ('/webhooks/cloud/{channel_uuid}',  [WebhookController::class, 'cloudApi']);
$router->post('/webhooks/cloud/{channel_uuid}',  [WebhookController::class, 'cloudApi'])
    ->middleware('rate:api');

// Webhooks genericos para integraciones (Stripe, MercadoPago, Telegram, etc.)
$router->post('/webhooks/integration/{slug}/{tenant_uuid}', [WebhookController::class, 'integration'])
    ->middleware('rate:api');
