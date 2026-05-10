<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Services\ExecutiveDashboardService;

/**
 * Snapshot ejecutivo cross-feature en JSON.
 *
 *   GET /api/v1/dashboard/executive
 *   GET /api/v1/dashboard/executive?refresh=1  -> bypass cache (forzar query fresh)
 */
final class ApiDashboardController extends ApiController
{
    public function executive(Request $request): void
    {
        $tenantId = $this->tenantId();
        $bypass = (bool) $request->query('refresh', false);
        $snapshot = (new ExecutiveDashboardService($tenantId))->snapshot($bypass);
        $this->ok($snapshot);
    }
}
