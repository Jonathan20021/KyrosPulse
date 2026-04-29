<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Automation
{
    public const TRIGGERS = [
        'message.received'     => 'Mensaje recibido',
        'contact.created'      => 'Contacto creado',
        'lead.created'         => 'Lead creado',
        'lead.stage_changed'   => 'Lead cambia de etapa',
        'conversation.no_response' => 'Conversacion sin respuesta',
        'ticket.created'       => 'Ticket creado',
        'contact.tagged'       => 'Cliente etiquetado',
    ];

    public const CONDITION_TYPES = [
        'message_contains'        => 'Mensaje contiene palabra',
        'message_not_contains'    => 'Mensaje NO contiene palabra',
        'business_hours'          => 'Es horario laboral',
        'outside_business_hours'  => 'Fuera de horario laboral',
        'contact_has_tag'         => 'Contacto tiene etiqueta',
        'contact_status'          => 'Contacto tiene estado',
        'lead_in_stage'           => 'Lead en etapa',
        'sentiment'               => 'Sentimiento es',
        'ai_score_gte'            => 'Score IA >= valor',
        'ai_score_lte'            => 'Score IA <= valor',
        'channel_is'              => 'Canal es',
    ];

    public const ACTION_TYPES = [
        'send_whatsapp'      => 'Enviar WhatsApp',
        'send_email'         => 'Enviar email',
        'add_tag'            => 'Agregar etiqueta',
        'assign_agent'       => 'Asignar agente',
        'change_lead_stage'  => 'Mover lead a etapa',
        'create_ticket'      => 'Crear ticket',
        'notify'             => 'Notificar usuario',
        'run_ai_reply'       => 'Responder con IA',
    ];

    public static function listForTenant(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT * FROM automations WHERE tenant_id = :t ORDER BY created_at DESC",
            ['t' => $tenantId]
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM automations WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('automations', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('automations', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function delete(int $tenantId, int $id): int
    {
        return Database::delete('automations', ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function recentLogs(int $tenantId, int $automationId, int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT * FROM automation_logs WHERE tenant_id = :t AND automation_id = :a
             ORDER BY created_at DESC LIMIT $limit",
            ['t' => $tenantId, 'a' => $automationId]
        );
    }
}
