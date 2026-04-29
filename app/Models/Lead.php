<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Lead extends Model
{
    protected static string $table = 'leads';
    protected static bool $softDelete = true;

    public static function listKanban(int $tenantId): array
    {
        $stages = Database::fetchAll(
            "SELECT * FROM pipeline_stages WHERE tenant_id = :t ORDER BY sort_order ASC",
            ['t' => $tenantId]
        );
        $leads = Database::fetchAll(
            "SELECT l.*, c.first_name, c.last_name, c.company
             FROM leads l
             LEFT JOIN contacts c ON c.id = l.contact_id
             WHERE l.tenant_id = :t AND l.deleted_at IS NULL
             ORDER BY l.updated_at DESC",
            ['t' => $tenantId]
        );

        $grouped = [];
        foreach ($stages as $s) {
            $grouped[$s['id']] = ['stage' => $s, 'leads' => []];
        }
        foreach ($leads as $l) {
            $sid = (int) $l['stage_id'];
            if (isset($grouped[$sid])) {
                $grouped[$sid]['leads'][] = $l;
            }
        }
        return array_values($grouped);
    }

    public static function totalValue(int $tenantId, string $status = 'open'): float
    {
        return (float) Database::fetchColumn(
            "SELECT COALESCE(SUM(value), 0) FROM leads WHERE tenant_id = :t AND status = :s AND deleted_at IS NULL",
            ['t' => $tenantId, 's' => $status]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT l.*, c.first_name, c.last_name, c.email, c.phone, c.company,
                    s.name AS stage_name, s.color AS stage_color, s.is_won, s.is_lost,
                    u.first_name AS agent_first, u.last_name AS agent_last
             FROM leads l
             LEFT JOIN contacts c ON c.id = l.contact_id
             LEFT JOIN pipeline_stages s ON s.id = l.stage_id
             LEFT JOIN users u ON u.id = l.assigned_to
             WHERE l.id = :id AND l.tenant_id = :t AND l.deleted_at IS NULL",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('leads', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('leads', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function softDelete(int $tenantId, int $id): int
    {
        return self::update($tenantId, $id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public static function changeStage(int $tenantId, int $id, int $stageId): array
    {
        $stage = \App\Models\PipelineStage::findById($tenantId, $stageId);
        if (!$stage) return ['success' => false, 'reason' => 'stage no existe'];

        $update = ['stage_id' => $stageId, 'probability' => (int) $stage['probability']];
        if ($stage['is_won']) {
            $update['status'] = 'won';
            $update['actual_close'] = date('Y-m-d');
        } elseif ($stage['is_lost']) {
            $update['status'] = 'lost';
            $update['actual_close'] = date('Y-m-d');
        } else {
            $update['status'] = 'open';
        }
        self::update($tenantId, $id, $update);
        return ['success' => true, 'stage' => $stage];
    }
}
