<?php
/**
 * Front controller - Kyros Pulse
 */
declare(strict_types=1);

require __DIR__ . '/../app/Core/Application.php';
require __DIR__ . '/../app/Core/Env.php';
require __DIR__ . '/../app/Core/Config.php';

$app = new App\Core\Application(dirname(__DIR__));
$app->boot()->loadRoutes()->run();
