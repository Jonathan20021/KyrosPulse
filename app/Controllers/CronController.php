<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Services\ContactMemoryService;
use App\Services\SalesBotService;

/**
 * Endpoints de tareas programadas (cron). Se llaman externamente desde el cron
 * del servidor (cPanel, GitHub Actions, etc.) y autenticados via X-Cron-Token.
 *
 * Ejemplo cPanel cron (cada 10 min):
 *   curl -s -X POST -H "X-Cron-Token: TU_TOKEN" https://pulse.tudominio.com/api/cron/sales-bot
 *
 * El token vive en env CRON_TOKEN.
 */
final class CronController extends Controller
{
    public function salesBot(Request $request): void
    {
        if (!$this->authorized($request)) {
            $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $startedAt = microtime(true);

        // Solo tenants activos con modulo restaurante (es donde el sales bot tiene sentido)
        $tenants = Database::fetchAll(
            "SELECT id, name FROM tenants
             WHERE deleted_at IS NULL
               AND COALESCE(is_restaurant, 0) = 1
             ORDER BY id ASC"
        );

        $perTenant = [];
        $totals = ['cart_recovery' => 0, 're_engagement' => 0, 'memory_refresh' => 0, 'errors' => 0];
        foreach ($tenants as $t) {
            $tid = (int) $t['id'];
            try {
                $stats = (new SalesBotService($tid))->runFullCycle();
                // Refrescar perfiles aprendidos stale (Fase 3) en el mismo ciclo
                try {
                    $stats['memory_refresh'] = (new ContactMemoryService($tid))->refreshStaleProfiles(30);
                } catch (\Throwable $e) {
                    Logger::warning('ContactMemory cron tenant fallo', ['tenant' => $tid, 'msg' => $e->getMessage()]);
                    $stats['memory_refresh'] = 0;
                }

                $perTenant[] = [
                    'tenant_id' => $tid,
                    'name'      => (string) $t['name'],
                    'stats'     => $stats,
                ];
                $totals['cart_recovery']  += $stats['cart_recovery'];
                $totals['re_engagement']  += $stats['re_engagement'];
                $totals['memory_refresh'] += (int) ($stats['memory_refresh'] ?? 0);
                $totals['errors']         += $stats['errors'];
            } catch (\Throwable $e) {
                Logger::error('SalesBot cron tenant fallo', ['tenant' => $tid, 'msg' => $e->getMessage()]);
                $perTenant[] = [
                    'tenant_id' => $tid,
                    'name'      => (string) $t['name'],
                    'error'     => $e->getMessage(),
                ];
                $totals['errors']++;
            }
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        Logger::info('SalesBot cron completado', [
            'duration_ms' => $durationMs,
            'totals'      => $totals,
            'tenants'     => count($tenants),
        ]);

        $this->json([
            'success'     => true,
            'duration_ms' => $durationMs,
            'totals'      => $totals,
            'tenants'     => $perTenant,
            'ran_at'      => date('c'),
        ]);
    }

    /**
     * GET|POST /cron/webhooks-retry
     * Reintenta entregas de webhooks salientes pendientes.
     * Cron sugerido: cada minuto.
     */
    public function webhooksRetry(Request $request): void
    {
        if (!$this->authorized($request)) {
            $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $start  = microtime(true);
        $result = \App\Services\WebhookDispatcher::processRetries(200);
        $this->json([
            'success'    => true,
            'processed'  => $result['processed'],
            'delivered'  => $result['delivered'],
            'failed'     => $result['failed'],
            'duration_ms'=> (int) round((microtime(true) - $start) * 1000),
            'ran_at'     => date('c'),
        ]);
    }

    /**
     * GET|POST /cron/evaluate-alerts
     * Evalua reglas pasivas (webhook.dead, agent.error_rate, workflow.failed).
     * Cron sugerido: cada 10 min.
     */
    public function evaluateAlerts(Request $request): void
    {
        if (!$this->authorized($request)) {
            $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }
        $start = microtime(true);
        $stats = \App\Services\AlertService::evaluateAll(200);
        $this->json([
            'success'    => true,
            'tenants'    => $stats['tenants'],
            'rules_eval' => $stats['evaluated'],
            'fired'      => $stats['fired'],
            'duration_ms'=> (int) round((microtime(true) - $start) * 1000),
            'ran_at'     => date('c'),
        ]);
    }

    /**
     * GET|POST /cron/workflow-tick
     * Tick del workflow engine: reanuda waiters + dispara schedule triggers.
     * Cron sugerido: cada minuto.
     */
    public function workflowTick(Request $request): void
    {
        if (!$this->authorized($request)) {
            $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $start  = microtime(true);
        $resume = \App\Services\WorkflowEngine::processWaitingRuns(200);
        $sched  = \App\Services\WorkflowEngine::processScheduledTriggers(50);
        $this->json([
            'success'           => true,
            'resumed'           => $resume['resumed'],
            'resume_failed'     => $resume['failed'],
            'scheduled_started' => $sched['started'],
            'duration_ms'       => (int) round((microtime(true) - $start) * 1000),
            'ran_at'            => date('c'),
        ]);
    }

    private function authorized(Request $request): bool
    {
        $expected = (string) (\App\Core\Config::get('app.cron_token', '') ?: env('CRON_TOKEN', ''));
        if ($expected === '') {
            // Sin token configurado, denegamos por defecto (modo seguro).
            return false;
        }
        $provided = $request->header('X-Cron-Token')
            ?: $request->header('Authorization')
            ?: (string) $request->query('token', '');

        // Authorization: Bearer XYZ
        if (str_starts_with((string) $provided, 'Bearer ')) {
            $provided = substr((string) $provided, 7);
        }

        return hash_equals($expected, (string) $provided);
    }
}
