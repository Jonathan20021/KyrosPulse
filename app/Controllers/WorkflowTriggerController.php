<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\WorkflowEngine;

/**
 * Endpoint publico para disparar workflows con trigger=webhook via URL unica:
 *   POST /workflows/run/{token}
 *   {... body JSON arbitrario ...}
 *
 * Sin auth pero con token unico por workflow. El body llega al context como
 * `webhook_payload`.
 */
final class WorkflowTriggerController extends Controller
{
    public function trigger(Request $request, array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        if ($token === '' || !preg_match('/^wf_[a-zA-Z0-9]{20,}$/', $token)) {
            $this->json(['error' => 'invalid_token'], 400);
            return;
        }
        $body = (array) $request->input();
        unset($body['_method']);

        $result = WorkflowEngine::triggerByWebhookToken($token, $body);
        if (!$result) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        $this->json([
            'data' => [
                'run_id'      => (int) $result['run_id'],
                'workflow_id' => (int) $result['workflow_id'],
                'status'      => 'started',
            ],
        ], 202);
    }
}
