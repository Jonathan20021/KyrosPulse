<?php
/**
 * Bootstrap comun para scripts cron.
 * Carga env, config, autoload y servicios.
 */
declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/app/Core/Application.php';
require $root . '/app/Core/Env.php';
require $root . '/app/Core/Config.php';

$app = new App\Core\Application($root);
$app->boot();

// CLI mode marker
define('KYROS_CLI', true);

echo "[Kyros Pulse Cron] " . date('Y-m-d H:i:s') . "\n";
