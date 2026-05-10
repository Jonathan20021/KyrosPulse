<?php
/**
 * Cron worker: evalua reglas de alerta pasivas (las que dependen de queries
 * periodicas como webhook.dead, agent.error_rate, workflow.failed).
 *
 * Las reglas inline (cuota API, security critical) NO se evaluan aqui;
 * disparan desde el codigo en tiempo real via AlertService::fire().
 *
 * Cron sugerido: cada 10 minutos.
 *   * /10 * * * * cd /path/to/KyrosPulse && /usr/bin/php cron/evaluate_alerts.php
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$start = microtime(true);
$stats = \App\Services\AlertService::evaluateAll(200);
$dur = (int) round((microtime(true) - $start) * 1000);

echo sprintf(
    "Alerts evaluated: tenants=%d rules=%d fired=%d in %dms\n",
    $stats['tenants'], $stats['evaluated'], $stats['fired'], $dur
);
