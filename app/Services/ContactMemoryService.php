<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Memoria persistente del cliente: aprende del historial de ordenes y
 * conversaciones para que la IA pueda personalizar respuestas y propuestas.
 *
 * Computa:
 *   - Items favoritos (top 5 por frecuencia)
 *   - Patrones de pedido (dia/hora preferida, ticket promedio)
 *   - RFM segmentation: vip | regular | nuevo | dormido | perdido
 *   - Score combinado RFM (0-1000)
 *
 * Se invoca:
 *   - Despues de cada orden creada (refresh hot del contacto)
 *   - Periodicamente desde el cron del SalesBot (refresh batch de stale)
 *   - Manual desde el panel "Refrescar perfil"
 *
 * El perfil aprendido se guarda en `contacts.preferences` (JSON) y se inyecta
 * al system prompt de la IA cuando responde a ese contacto.
 */
final class ContactMemoryService
{
    public function __construct(private int $tenantId) {}

    /**
     * Recalcula el perfil de UN contacto. Idempotente: se puede llamar
     * cuantas veces se quiera sin efectos secundarios.
     */
    public function refreshProfile(int $contactId): array
    {
        $contact = Database::fetch(
            "SELECT id, first_name, last_name FROM contacts
             WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL",
            ['id' => $contactId, 't' => $this->tenantId]
        );
        if (!$contact) {
            return ['success' => false, 'error' => 'Contacto no encontrado'];
        }

        // 1) Estadisticas core: cantidad, total, ticket promedio, ultima orden
        $stats = Database::fetch(
            "SELECT COUNT(*) AS orders_count,
                    COALESCE(SUM(total), 0) AS total_value,
                    COALESCE(AVG(total), 0) AS avg_ticket,
                    MAX(created_at) AS last_order_at,
                    MIN(created_at) AS first_order_at
             FROM orders
             WHERE tenant_id = :t AND contact_id = :c
               AND status NOT IN ('cancelled')",
            ['t' => $this->tenantId, 'c' => $contactId]
        ) ?: ['orders_count' => 0, 'total_value' => 0, 'avg_ticket' => 0, 'last_order_at' => null, 'first_order_at' => null];

        $orderCount  = (int) $stats['orders_count'];
        $totalValue  = (float) $stats['total_value'];
        $avgTicket   = (float) $stats['avg_ticket'];
        $lastOrderAt = $stats['last_order_at'];

        // 2) Items favoritos: top 5 por frecuencia
        $favItems = Database::fetchAll(
            "SELECT oi.name, COUNT(*) AS times, SUM(oi.qty) AS total_qty,
                    AVG(oi.unit_price) AS avg_price
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id
             WHERE oi.tenant_id = :t AND o.contact_id = :c
               AND o.status NOT IN ('cancelled')
             GROUP BY oi.name
             ORDER BY times DESC, total_qty DESC
             LIMIT 5",
            ['t' => $this->tenantId, 'c' => $contactId]
        );

        // 3) Patrones temporales: dia de la semana y hora mas frecuentes
        $patterns = Database::fetchAll(
            "SELECT DAYOFWEEK(created_at) AS dow,
                    HOUR(created_at) AS hour,
                    COUNT(*) AS cnt
             FROM orders
             WHERE tenant_id = :t AND contact_id = :c
               AND status NOT IN ('cancelled')
             GROUP BY dow, hour
             ORDER BY cnt DESC
             LIMIT 10",
            ['t' => $this->tenantId, 'c' => $contactId]
        );

        // Aglomerar patrones a "dias preferidos" y "ventana horaria preferida"
        $dowCounts = [];
        $hourCounts = [];
        foreach ($patterns as $p) {
            $d = (int) $p['dow'];   // 1=Dom, 2=Lun, ... 7=Sab (DAYOFWEEK MySQL)
            $h = (int) $p['hour'];
            $dowCounts[$d] = ($dowCounts[$d] ?? 0) + (int) $p['cnt'];
            $hourCounts[$h] = ($hourCounts[$h] ?? 0) + (int) $p['cnt'];
        }
        arsort($dowCounts);
        arsort($hourCounts);
        $dowMap = [1 => 'dom', 2 => 'lun', 3 => 'mar', 4 => 'mie', 5 => 'jue', 6 => 'vie', 7 => 'sab'];
        $preferredDays = array_slice(array_map(fn($d) => $dowMap[$d] ?? '?', array_keys($dowCounts)), 0, 3);
        $preferredHours = array_slice(array_keys($hourCounts), 0, 3);

        // 4) Tipo de delivery preferido y zona
        $deliveryPref = Database::fetch(
            "SELECT delivery_type, COUNT(*) AS cnt
             FROM orders
             WHERE tenant_id = :t AND contact_id = :c
               AND status NOT IN ('cancelled')
             GROUP BY delivery_type
             ORDER BY cnt DESC LIMIT 1",
            ['t' => $this->tenantId, 'c' => $contactId]
        );

        // 5) RFM scoring
        [$rfmScore, $rfmSegment] = $this->computeRFM($orderCount, $totalValue, $lastOrderAt);

        // 6) Construir el JSON de preferences
        $preferences = [
            'favorite_items'   => array_map(fn($f) => [
                'name'     => (string) $f['name'],
                'times'    => (int) $f['times'],
                'avg_price'=> round((float) $f['avg_price'], 2),
            ], $favItems),
            'preferred_days'   => $preferredDays,
            'preferred_hours'  => $preferredHours,
            'avg_ticket'       => round($avgTicket, 2),
            'delivery_type'    => $deliveryPref['delivery_type'] ?? null,
            'first_order_at'   => $stats['first_order_at'],
            'last_order_at'    => $lastOrderAt,
        ];

        // 7) Persistir
        Database::run(
            "UPDATE contacts
                SET preferences        = :p,
                    rfm_segment        = :seg,
                    rfm_score          = :score,
                    lifetime_orders    = :ords,
                    lifetime_value     = :val,
                    last_order_at      = :last,
                    memory_updated_at  = NOW()
              WHERE id = :id AND tenant_id = :t",
            [
                'p'     => json_encode($preferences, JSON_UNESCAPED_UNICODE),
                'seg'   => $rfmSegment,
                'score' => $rfmScore,
                'ords'  => $orderCount,
                'val'   => $totalValue,
                'last'  => $lastOrderAt,
                'id'    => $contactId,
                't'     => $this->tenantId,
            ]
        );

        return [
            'success'         => true,
            'orders_count'    => $orderCount,
            'lifetime_value'  => $totalValue,
            'rfm_segment'     => $rfmSegment,
            'rfm_score'       => $rfmScore,
            'favorite_items'  => $preferences['favorite_items'],
            'preferred_days'  => $preferredDays,
            'preferred_hours' => $preferredHours,
        ];
    }

    /**
     * Refresca en batch los contactos del tenant cuyo perfil esta stale o
     * nunca se calculo. Pensado para correr desde el cron junto al SalesBot.
     *
     * Stale = memory_updated_at IS NULL O memory_updated_at < hace 7 dias O
     * el ultimo order es mas reciente que memory_updated_at.
     */
    public function refreshStaleProfiles(int $maxBatch = 30): int
    {
        $rows = Database::fetchAll(
            "SELECT co.id
             FROM contacts co
             WHERE co.tenant_id = :t
               AND co.deleted_at IS NULL
               AND EXISTS (
                   SELECT 1 FROM orders o
                   WHERE o.contact_id = co.id AND o.tenant_id = co.tenant_id
                     AND o.status NOT IN ('cancelled')
               )
               AND (co.memory_updated_at IS NULL
                    OR co.memory_updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
                    OR co.memory_updated_at < (
                        SELECT MAX(o.created_at) FROM orders o
                        WHERE o.contact_id = co.id AND o.tenant_id = co.tenant_id
                          AND o.status NOT IN ('cancelled')
                    ))
             ORDER BY co.memory_updated_at ASC
             LIMIT $maxBatch",
            ['t' => $this->tenantId]
        );

        $refreshed = 0;
        foreach ($rows as $r) {
            try {
                $this->refreshProfile((int) $r['id']);
                $refreshed++;
            } catch (\Throwable $e) {
                Logger::warning('ContactMemory refresh fallo', [
                    'contact' => $r['id'], 'msg' => $e->getMessage(),
                ]);
            }
        }
        return $refreshed;
    }

    /**
     * RFM scoring simplificado:
     *   - Recency (R): cuan reciente fue el ultimo pedido
     *   - Frequency (F): cuantas ordenes ha hecho
     *   - Monetary (M): total gastado
     *
     * Cada uno se normaliza a 0-100 y se combina ponderado:
     *   score = R*0.4 + F*0.3 + M*0.3 (escalado a 0-1000)
     *
     * Segmentos:
     *   - vip      : score >= 700 (cliente fiel y reciente)
     *   - regular  : score >= 400
     *   - nuevo    : 1 orden y reciente (<30d)
     *   - dormido  : tuvo varias pero silencio 30-90 dias
     *   - perdido  : silencio > 90 dias
     *
     * @return array{0:int,1:string} [score, segment]
     */
    private function computeRFM(int $orderCount, float $totalValue, ?string $lastOrderAt): array
    {
        if ($orderCount === 0 || $lastOrderAt === null) {
            return [0, 'nuevo'];
        }

        $daysSinceLast = (int) ((time() - strtotime($lastOrderAt)) / 86400);
        if ($daysSinceLast < 0) $daysSinceLast = 0;

        // R: 100 si pidio hoy, 0 si hace >180d
        $r = max(0, min(100, (int) round(100 * (1 - min($daysSinceLast, 180) / 180))));
        // F: 100 al alcanzar 20 ordenes
        $f = max(0, min(100, (int) round($orderCount / 20 * 100)));
        // M: 100 al alcanzar $5000 lifetime
        $m = max(0, min(100, (int) round($totalValue / 5000 * 100)));

        $score = (int) round(($r * 0.4 + $f * 0.3 + $m * 0.3) * 10);

        // Segmento por reglas (no solo score, para que "dormido"/"perdido"
        // funcionen aunque el score sea alto historicamente)
        $segment = 'regular';
        if ($daysSinceLast > 90) {
            $segment = 'perdido';
        } elseif ($daysSinceLast > 30 && $orderCount >= 2) {
            $segment = 'dormido';
        } elseif ($orderCount === 1 && $daysSinceLast <= 30) {
            $segment = 'nuevo';
        } elseif ($score >= 700) {
            $segment = 'vip';
        } elseif ($score >= 400) {
            $segment = 'regular';
        } else {
            $segment = 'nuevo';
        }

        return [$score, $segment];
    }

    /**
     * Renderiza el perfil aprendido como bloque legible para inyectar al
     * system prompt de la IA. Si no hay perfil suficiente devuelve ''.
     */
    public function renderForPrompt(int $contactId): string
    {
        $row = Database::fetch(
            "SELECT first_name, last_name, preferences, rfm_segment, rfm_score,
                    lifetime_orders, lifetime_value, last_order_at, notes_ai
             FROM contacts
             WHERE id = :id AND tenant_id = :t",
            ['id' => $contactId, 't' => $this->tenantId]
        );
        if (!$row) return '';

        $orders = (int) ($row['lifetime_orders'] ?? 0);
        if ($orders === 0) return '';  // sin historial no hay nada que aprender

        $prefs = is_string($row['preferences']) ? (json_decode($row['preferences'], true) ?: []) : [];
        $segment = (string) ($row['rfm_segment'] ?? 'regular');
        $value   = (float) ($row['lifetime_value'] ?? 0);

        $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        $name = $name ?: 'Cliente';

        $segLabels = [
            'vip'      => '🌟 VIP (cliente top — trato prioritario, propuestas premium)',
            'regular'  => 'Regular (cliente fiel — sigue lo que ya le funciona)',
            'nuevo'    => 'Nuevo (recien llegado — facilitale el camino, sin agobiar)',
            'dormido'  => 'Dormido (tiene pedidos pero llevaba tiempo sin hablar — tono cercano, recuperar)',
            'perdido'  => 'Perdido (silencio prolongado — propuesta atractiva, sin presionar)',
        ];

        $favs = $prefs['favorite_items'] ?? [];
        $favText = !empty($favs)
            ? implode(', ', array_map(fn($f) => $f['name'] . ' (' . $f['times'] . 'x)', array_slice($favs, 0, 3)))
            : 'aun no se conoce';

        $days = $prefs['preferred_days'] ?? [];
        $hours = $prefs['preferred_hours'] ?? [];
        $temporalText = '';
        if (!empty($days) && !empty($hours)) {
            $temporalText = 'Suele pedir ' . implode('/', array_slice($days, 0, 2))
                          . ' alrededor de las ' . implode('h, ', array_slice($hours, 0, 2)) . 'h';
        }

        $lines = [];
        $lines[] = 'PERFIL APRENDIDO DEL CLIENTE (usalo para personalizar SIN que el cliente sienta que lo espias):';
        $lines[] = "- Nombre: $name";
        $lines[] = "- Segmento: " . ($segLabels[$segment] ?? $segment);
        $lines[] = "- Historia: $orders pedidos, total $" . number_format($value, 2);
        if (!empty($prefs['avg_ticket'])) {
            $lines[] = "- Ticket promedio: $" . number_format((float) $prefs['avg_ticket'], 2);
        }
        $lines[] = "- Items favoritos: $favText";
        if ($temporalText !== '') {
            $lines[] = "- Patron temporal: $temporalText";
        }
        if (!empty($prefs['delivery_type'])) {
            $lines[] = "- Tipo preferido: " . $prefs['delivery_type'];
        }
        if (!empty($row['notes_ai'])) {
            $lines[] = "- Notas: " . trim((string) $row['notes_ai']);
        }

        $lines[] = '';
        if ($segment === 'vip') {
            $lines[] = 'Trato VIP: saluda con calidez, propon su favorito o algo nuevo de la misma linea, prioriza claridad.';
        } elseif ($segment === 'dormido' || $segment === 'perdido') {
            $lines[] = 'Recordatorio sutil: menciona algo que sabes que le gusta. NO arranques con descuento agresivo si no lo pidio.';
        } else {
            $lines[] = 'Aprovecha lo que ya conoces: si pregunta "que recomiendas", sus favoritos son una buena respuesta primero.';
        }

        return implode("\n", $lines);
    }
}
