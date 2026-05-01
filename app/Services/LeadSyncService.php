<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Lead;

/**
 * Mantiene en sincronia las ordenes con el pipeline de leads y el CRM.
 *
 * - Cada vez que se crea una orden, se crea (o actualiza) un Lead asociado.
 * - Cuando cambia el status de la orden, el Lead se mueve a la etapa correcta.
 * - El contacto se actualiza con: lifecycle_stage, last_interaction, estimated_value.
 *
 * Mapeo orden.status → pipeline stage:
 *   new              → "Nuevo lead"
 *   confirmed        → "Interesado"
 *   preparing        → "Cotizacion enviada"
 *   ready            → "Negociacion"
 *   out_for_delivery → "Negociacion"
 *   delivered        → "Ganado" (won)
 *   cancelled        → "Perdido" (lost)
 */
final class LeadSyncService
{
    public function __construct(private int $tenantId) {}

    /** Mapeo de status de orden a slug de pipeline_stage. */
    private const STATUS_TO_STAGE = [
        'new'              => 'nuevo',
        'confirmed'        => 'interesado',
        'preparing'        => 'cotizacion',
        'ready'            => 'negociacion',
        'out_for_delivery' => 'negociacion',
        'delivered'        => 'ganado',
        'cancelled'        => 'perdido',
    ];

    /**
     * Crea o actualiza el lead asociado a una orden.
     */
    public function syncOrderToLead(int $orderId): ?int
    {
        try {
            $order = Database::fetch(
                "SELECT * FROM orders WHERE id = :id AND tenant_id = :t",
                ['id' => $orderId, 't' => $this->tenantId]
            );
            if (!$order) return null;

            $stageId = $this->resolveStageId((string) $order['status']);
            if (!$stageId) return null;

            // Buscar lead existente para esta orden (relacionado por description o contact)
            $existing = Database::fetch(
                "SELECT id FROM leads
                 WHERE tenant_id = :t AND deleted_at IS NULL
                   AND description LIKE :ref
                 LIMIT 1",
                ['t' => $this->tenantId, 'ref' => '%[ORDER:' . $order['code'] . ']%']
            );

            $stage = Database::fetch("SELECT * FROM pipeline_stages WHERE id = :id", ['id' => $stageId]);
            $isWon = !empty($stage['is_won']);
            $isLost = !empty($stage['is_lost']);

            $title = sprintf('Pedido %s — %s',
                $order['code'],
                trim((string) ($order['customer_name'] ?? 'Cliente'))
            );
            $description = sprintf("Orden de restaurante via canal %s.\n[ORDER:%s] orderId=%d total=%s %s",
                ($order['is_ai_generated'] ? 'IA' : 'manual'),
                $order['code'],
                (int) $order['id'],
                $order['currency'],
                number_format((float) $order['total'], 2)
            );

            $data = [
                'tenant_id'    => $this->tenantId,
                'contact_id'   => $order['contact_id'] ? (int) $order['contact_id'] : null,
                'stage_id'     => $stageId,
                'title'        => mb_substr($title, 0, 180),
                'description'  => $description,
                'value'        => (float) $order['total'],
                'currency'     => (string) ($order['currency'] ?? 'USD'),
                'probability'  => (int) ($stage['probability'] ?? 50),
                'source'       => $order['is_ai_generated'] ? 'whatsapp_ia' : 'restaurante',
                'status'       => $isWon ? 'won' : ($isLost ? 'lost' : 'open'),
                'expected_close' => date('Y-m-d', strtotime('+1 day')),
                'actual_close' => ($isWon || $isLost) ? date('Y-m-d') : null,
            ];

            if ($existing) {
                Database::update('leads', $data, ['id' => (int) $existing['id'], 'tenant_id' => $this->tenantId]);
                $leadId = (int) $existing['id'];
            } else {
                $leadId = Lead::create($data);
            }

            // Actualizar el contacto
            if (!empty($order['contact_id'])) {
                $this->updateContactFromOrder((int) $order['contact_id'], $order, $isWon, $isLost);
            }

            Logger::info('Lead sincronizado desde orden', [
                'tenant'   => $this->tenantId,
                'order'    => $orderId,
                'lead'     => $leadId,
                'stage'    => $stage['name'] ?? null,
                'status'   => $data['status'],
            ]);

            return $leadId;
        } catch (\Throwable $e) {
            Logger::error('LeadSync fallo', [
                'tenant' => $this->tenantId,
                'order'  => $orderId,
                'msg'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Actualiza estadisticas del contacto: lifecycle, valor estimado total,
     * ultima interaccion. Tambien marca como 'customer' al ganar la orden.
     */
    private function updateContactFromOrder(int $contactId, array $order, bool $isWon, bool $isLost): void
    {
        try {
            $update = [
                'last_interaction' => date('Y-m-d H:i:s'),
            ];

            if ($isWon) {
                // Sumar al estimated_value del cliente (lifetime value aproximado)
                Database::run(
                    "UPDATE contacts
                     SET estimated_value = COALESCE(estimated_value, 0) + :v,
                         lifecycle_stage = 'customer',
                         status = 'active',
                         last_interaction = NOW()
                     WHERE id = :id AND tenant_id = :t",
                    ['v' => (float) $order['total'], 'id' => $contactId, 't' => $this->tenantId]
                );
                return;
            }

            if (!$isLost) {
                // Cliente activo en pipeline: si no tiene lifecycle aun, marcarlo lead
                Database::run(
                    "UPDATE contacts
                     SET lifecycle_stage = COALESCE(NULLIF(lifecycle_stage,''), 'lead'),
                         last_interaction = NOW()
                     WHERE id = :id AND tenant_id = :t",
                    ['id' => $contactId, 't' => $this->tenantId]
                );
            } else {
                Database::update('contacts', $update, ['id' => $contactId, 'tenant_id' => $this->tenantId]);
            }
        } catch (\Throwable $e) {
            Logger::error('LeadSync::updateContact fallo', ['msg' => $e->getMessage()]);
        }
    }

    /**
     * Encuentra el id de la pipeline_stage que corresponde al status de la orden.
     * Si no existe la stage exacta, usa la primera disponible (defensivo).
     */
    private function resolveStageId(string $orderStatus): ?int
    {
        $slug = self::STATUS_TO_STAGE[$orderStatus] ?? 'nuevo';

        // Primero por slug exacto
        $stageId = Database::fetchColumn(
            "SELECT id FROM pipeline_stages WHERE tenant_id = :t AND slug = :s LIMIT 1",
            ['t' => $this->tenantId, 's' => $slug]
        );
        if ($stageId) return (int) $stageId;

        // Fallback: por flags (won/lost)
        if ($slug === 'ganado') {
            $stageId = Database::fetchColumn(
                "SELECT id FROM pipeline_stages WHERE tenant_id = :t AND is_won = 1 LIMIT 1",
                ['t' => $this->tenantId]
            );
            if ($stageId) return (int) $stageId;
        }
        if ($slug === 'perdido') {
            $stageId = Database::fetchColumn(
                "SELECT id FROM pipeline_stages WHERE tenant_id = :t AND is_lost = 1 LIMIT 1",
                ['t' => $this->tenantId]
            );
            if ($stageId) return (int) $stageId;
        }

        // Fallback final: primera stage del tenant
        $stageId = Database::fetchColumn(
            "SELECT id FROM pipeline_stages WHERE tenant_id = :t ORDER BY sort_order ASC LIMIT 1",
            ['t' => $this->tenantId]
        );
        return $stageId ? (int) $stageId : null;
    }

    /**
     * Asegura que existan stages basicas para el tenant si todavia no las tiene
     * (ej. tenants creados antes de tener restaurant module).
     */
    public function ensureRestaurantStages(): void
    {
        $existing = Database::fetchAll(
            "SELECT slug FROM pipeline_stages WHERE tenant_id = :t",
            ['t' => $this->tenantId]
        );
        $slugs = array_column($existing, 'slug');

        $defaults = [
            ['Nuevo lead',         'nuevo',       '#06B6D4', 10,  0, 0, 1],
            ['Contactado',         'contactado',  '#3B82F6', 25,  0, 0, 2],
            ['Interesado',         'interesado',  '#7C3AED', 50,  0, 0, 3],
            ['Cotizacion enviada', 'cotizacion',  '#A855F7', 70,  0, 0, 4],
            ['Negociacion',        'negociacion', '#F59E0B', 85,  0, 0, 5],
            ['Ganado',             'ganado',      '#22C55E', 100, 1, 0, 6],
            ['Perdido',            'perdido',     '#EF4444', 0,   0, 1, 7],
        ];
        foreach ($defaults as [$name, $slug, $color, $prob, $won, $lost, $order]) {
            if (in_array($slug, $slugs, true)) continue;
            try {
                Database::insert('pipeline_stages', [
                    'tenant_id'   => $this->tenantId,
                    'name'        => $name,
                    'slug'        => $slug,
                    'color'       => $color,
                    'probability' => $prob,
                    'is_won'      => $won,
                    'is_lost'     => $lost,
                    'sort_order'  => $order,
                ]);
            } catch (\Throwable) {}
        }
    }
}
