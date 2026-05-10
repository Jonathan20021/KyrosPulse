<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Workflow + WorkflowStep + WorkflowRun.
 * Acceso scoped por tenant. Helpers para listar, resolver siguiente step,
 * y persistir contexto entre steps (delays).
 */
final class Workflow
{
    // ---- Workflows ----

    public static function listForTenant(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT w.*, (SELECT COUNT(*) FROM `workflow_steps` s WHERE s.workflow_id = w.id) AS steps_count
             FROM `workflows` w
             WHERE w.`tenant_id` = :t
             ORDER BY w.`is_active` DESC, w.`created_at` DESC",
            ['t' => $tenantId]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `workflows` WHERE `id` = :i AND `tenant_id` = :t LIMIT 1",
            ['i' => $id, 't' => $tenantId]
        );
        return $row ?: null;
    }

    public static function findByWebhookToken(string $token): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `workflows` WHERE `webhook_token` = :t AND `is_active` = 1 LIMIT 1",
            ['t' => $token]
        );
        return $row ?: null;
    }

    public static function listActiveByEvent(int $tenantId, string $event): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM `workflows`
             WHERE `tenant_id` = :t AND `is_active` = 1 AND `trigger_type` = 'event'",
            ['t' => $tenantId]
        );
        $matched = [];
        foreach ($rows as $r) {
            $cfg = $r['trigger_config'] ? json_decode((string) $r['trigger_config'], true) : [];
            $want = (string) ($cfg['event'] ?? '');
            if ($want === '' || $want === '*' || $want === $event) {
                $matched[] = $r;
            } elseif (str_ends_with($want, '.*') && str_starts_with($event, substr($want, 0, -1))) {
                $matched[] = $r;
            }
        }
        return $matched;
    }

    public static function listScheduledDue(): array
    {
        return Database::fetchAll(
            "SELECT * FROM `workflows`
             WHERE `is_active` = 1
               AND `trigger_type` = 'schedule'
               AND (`next_run_at` IS NULL OR `next_run_at` <= NOW())
             LIMIT 100"
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('workflows', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('workflows', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function delete(int $tenantId, int $id): int
    {
        $count = Database::delete('workflows', ['id' => $id, 'tenant_id' => $tenantId]);
        if ($count > 0) {
            Database::run("DELETE FROM `workflow_steps` WHERE `workflow_id` = :i", ['i' => $id]);
            Database::run("DELETE FROM `workflow_run_steps` WHERE `run_id` IN (SELECT id FROM `workflow_runs` WHERE `workflow_id` = :i)", ['i' => $id]);
            Database::run("DELETE FROM `workflow_runs` WHERE `workflow_id` = :i", ['i' => $id]);
        }
        return $count;
    }

    public static function touchRun(int $id, ?string $nextRunAt = null): void
    {
        $sql = "UPDATE `workflows`
                SET `runs_count` = `runs_count` + 1, `last_run_at` = NOW()";
        $params = ['i' => $id];
        if ($nextRunAt !== null) {
            $sql .= ", `next_run_at` = :n";
            $params['n'] = $nextRunAt;
        }
        $sql .= " WHERE `id` = :i";
        Database::run($sql, $params);
    }

    // ---- Steps ----

    public static function listSteps(int $workflowId): array
    {
        return Database::fetchAll(
            "SELECT * FROM `workflow_steps` WHERE `workflow_id` = :w ORDER BY `order_index` ASC, `id` ASC",
            ['w' => $workflowId]
        );
    }

    public static function findStep(int $workflowId, string $key): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `workflow_steps` WHERE `workflow_id` = :w AND `step_key` = :k LIMIT 1",
            ['w' => $workflowId, 'k' => $key]
        );
        return $row ?: null;
    }

    public static function firstStep(int $workflowId): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `workflow_steps` WHERE `workflow_id` = :w ORDER BY `order_index` ASC, `id` ASC LIMIT 1",
            ['w' => $workflowId]
        );
        return $row ?: null;
    }

    public static function createStep(array $data): int
    {
        return Database::insert('workflow_steps', $data);
    }

    public static function updateStep(int $tenantId, int $stepId, array $data): int
    {
        return Database::update('workflow_steps', $data, ['id' => $stepId, 'tenant_id' => $tenantId]);
    }

    public static function deleteStep(int $tenantId, int $stepId): int
    {
        return Database::delete('workflow_steps', ['id' => $stepId, 'tenant_id' => $tenantId]);
    }

    // ---- Runs ----

    public static function startRun(int $tenantId, int $workflowId, string $triggerType, array $context, ?string $startStepKey = null): int
    {
        $uuid = self::uuid4();
        return Database::insert('workflow_runs', [
            'uuid'             => $uuid,
            'tenant_id'        => $tenantId,
            'workflow_id'      => $workflowId,
            'trigger_type'     => substr($triggerType, 0, 20),
            'status'           => 'running',
            'current_step_key' => $startStepKey,
            'context'          => json_encode($context, JSON_UNESCAPED_UNICODE),
            'started_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    public static function findRun(int $tenantId, int $runId): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `workflow_runs` WHERE `id` = :i AND `tenant_id` = :t LIMIT 1",
            ['i' => $runId, 't' => $tenantId]
        );
        return $row ?: null;
    }

    public static function findRunByUuid(int $tenantId, string $uuid): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `workflow_runs` WHERE `uuid` = :u AND `tenant_id` = :t LIMIT 1",
            ['u' => $uuid, 't' => $tenantId]
        );
        return $row ?: null;
    }

    public static function listRunsForWorkflow(int $tenantId, int $workflowId, int $limit = 25): array
    {
        return Database::fetchAll(
            "SELECT * FROM `workflow_runs`
             WHERE `tenant_id` = :t AND `workflow_id` = :w
             ORDER BY `id` DESC
             LIMIT $limit",
            ['t' => $tenantId, 'w' => $workflowId]
        );
    }

    public static function listResumableRuns(int $limit = 100): array
    {
        return Database::fetchAll(
            "SELECT * FROM `workflow_runs`
             WHERE `status` = 'waiting'
               AND (`wait_until` IS NULL OR `wait_until` <= NOW())
             ORDER BY `id` ASC
             LIMIT $limit"
        );
    }

    public static function updateRun(int $runId, array $patch): void
    {
        if (isset($patch['context']) && is_array($patch['context'])) {
            $patch['context'] = json_encode($patch['context'], JSON_UNESCAPED_UNICODE);
        }
        Database::update('workflow_runs', $patch, ['id' => $runId]);
    }

    public static function logRunStep(int $runId, int $tenantId, string $stepKey, string $stepType, string $status, ?array $input = null, ?array $output = null, ?string $error = null, ?int $latencyMs = null): int
    {
        return Database::insert('workflow_run_steps', [
            'run_id'      => $runId,
            'tenant_id'   => $tenantId,
            'step_key'    => mb_substr($stepKey, 0, 40),
            'step_type'   => mb_substr($stepType, 0, 20),
            'status'      => $status,
            'input'       => $input  ? json_encode($input,  JSON_UNESCAPED_UNICODE) : null,
            'output'      => $output ? json_encode($output, JSON_UNESCAPED_UNICODE) : null,
            'error'       => $error,
            'latency_ms'  => $latencyMs,
            'finished_at' => $status !== 'running' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public static function listRunSteps(int $runId, int $limit = 100): array
    {
        return Database::fetchAll(
            "SELECT * FROM `workflow_run_steps` WHERE `run_id` = :r ORDER BY `id` ASC LIMIT $limit",
            ['r' => $runId]
        );
    }

    public static function generateWebhookToken(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $aLen = strlen($alphabet) - 1;
        $out = 'wf_';
        for ($i = 0; $i < 36; $i++) {
            $out .= $alphabet[random_int(0, $aLen)];
        }
        return $out;
    }

    private static function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
