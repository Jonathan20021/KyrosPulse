<?php
/**
 * Cron worker: reintenta webhooks salientes pendientes que ya cumplieron
 * su next_retry_at. Cron sugerido: cada minuto.
 *
 *   * * * * * cd /path/to/KyrosPulse && /usr/bin/php cron/webhooks_retry.php
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$start  = microtime(true);
$result = \App\Services\WebhookDispatcher::processRetries(200);

$durationMs = (int) round((microtime(true) - $start) * 1000);
echo sprintf(
    "Webhooks retry: processed=%d delivered=%d failed=%d in %dms\n",
    $result['processed'],
    $result['delivered'],
    $result['failed'],
    $durationMs
);
