<?php
/**
 * Cron worker: tick del workflow engine.
 *   1. Reanuda runs en estado 'waiting' (delays cumplidos)
 *   2. Dispara workflows con trigger=schedule cuyo cron matchea
 *
 * Cron sugerido: cada minuto.
 *   * * * * * cd /path/to/KyrosPulse && /usr/bin/php cron/workflow_tick.php
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$start = microtime(true);
$resume = \App\Services\WorkflowEngine::processWaitingRuns(200);
$sched  = \App\Services\WorkflowEngine::processScheduledTriggers(50);
$dur    = (int) round((microtime(true) - $start) * 1000);

echo sprintf(
    "Workflow tick: resumed=%d failed=%d scheduled_started=%d in %dms\n",
    $resume['resumed'],
    $resume['failed'],
    $sched['started'],
    $dur
);
