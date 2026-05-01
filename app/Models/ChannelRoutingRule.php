<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class ChannelRoutingRule extends Model
{
    protected static string $table = 'channel_routing_rules';

    public const MATCH_TYPES = [
        'any'           => 'Siempre (cualquier mensaje)',
        'channel'       => 'Por numero/canal especifico',
        'keyword'       => 'Si el mensaje contiene palabras clave',
        'time'          => 'Por horario (dentro/fuera de horas)',
        'language'      => 'Por idioma del mensaje',
        'contact_tag'   => 'Si el contacto tiene una etiqueta',
        'contact_score' => 'Por score del lead (>= valor)',
    ];

    public const STRATEGIES = [
        'round_robin'   => 'Round-robin entre agentes',
        'least_busy'    => 'Al agente menos ocupado',
        'specific_user' => 'Asignar a un usuario especifico',
        'team'          => 'Asignar a un equipo (rol)',
        'ai_agent'      => 'Manejar con un agente IA',
        'keep'          => 'Mantener sin asignar (cola)',
    ];

    public static function listForTenant(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT r.*,
                    wc.label AS channel_label, wc.color AS channel_color,
                    u.first_name, u.last_name,
                    aa.name AS ai_agent_name
             FROM channel_routing_rules r
             LEFT JOIN whatsapp_channels wc ON wc.id = r.channel_id
             LEFT JOIN users u ON u.id = r.assign_user_id
             LEFT JOIN ai_agents aa ON aa.id = r.assign_ai_agent_id
             WHERE r.tenant_id = :t
             ORDER BY r.priority ASC, r.id ASC",
            ['t' => $tenantId]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM channel_routing_rules WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function activeForChannel(int $tenantId, ?int $channelId): array
    {
        return Database::fetchAll(
            "SELECT * FROM channel_routing_rules
             WHERE tenant_id = :t AND is_active = 1
               AND (channel_id IS NULL OR channel_id = :c)
             ORDER BY priority ASC, id ASC",
            ['t' => $tenantId, 'c' => $channelId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('channel_routing_rules', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('channel_routing_rules', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function delete(int $tenantId, int $id): int
    {
        return (int) Database::run(
            "DELETE FROM channel_routing_rules WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        )->rowCount();
    }

    public static function bumpExecution(int $id, ?int $assignedUserId): void
    {
        Database::run(
            "UPDATE channel_routing_rules
             SET executions_count = executions_count + 1,
                 last_executed_at = NOW(),
                 last_assigned_user_id = COALESCE(:u, last_assigned_user_id)
             WHERE id = :id",
            ['id' => $id, 'u' => $assignedUserId]
        );
    }
}
