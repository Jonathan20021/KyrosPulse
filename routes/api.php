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
