<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modelo de agent_runs: ejecuciones de un agente IA con su input/output,
 * costo, latencia y acciones derivadas. Una run puede venir del API publico,
 * de un webhook entrante de WhatsApp, o de un job interno (cron, automation).
 */
final class AgentRun
{
    public static function start(array $data): int
    {
        $data['uuid'] = $data['uuid'] ?? self::uuid4();
        $data['status'] = $data['status'] ?? 'running';
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('agent_runs', $data);
    }

    public static function complete(int $id, array $patch): int
    {
        $patch['completed_at'] = date('Y-m-d H:i:s');
        return Database::update('agent_runs', $patch, ['id' => $id]);
    }

    public static function fail(int $id, string $error): int
    {
        return Database::update('agent_runs', [
            'status'       => 'failed',
            'error'        => $error,
            'completed_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public static function findByUuid(int $tenantId, string $uuid): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM `agent_runs` WHERE `tenant_id` = :t AND `uuid` = :u LIMIT 1",
            ['t' => $tenantId, 'u' => $uuid]
        );
        return $row ?: null;
    }

    public static function listForTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return Database::fetchAll(
            "SELECT * FROM `agent_runs`
             WHERE `tenant_id` = :t
             ORDER BY `id` DESC
             LIMIT $limit OFFSET $offset",
            ['t' => $tenantId]
        );
    }

    public static function listForAgent(int $tenantId, int $agentId, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT * FROM `agent_runs`
             WHERE `tenant_id` = :t AND `agent_id` = :a
             ORDER BY `id` DESC
             LIMIT $limit",
            ['t' => $tenantId, 'a' => $agentId]
        );
    }

    private static function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
