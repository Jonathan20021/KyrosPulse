<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class MenuItem extends Model
{
    protected static string $table = 'menu_items';

    public static function listForTenant(int $tenantId, array $filters = []): array
    {
        $where = ['mi.tenant_id = :t', 'mi.deleted_at IS NULL'];
        $params = ['t' => $tenantId];
        if (!empty($filters['category_id'])) {
            $where[] = 'mi.category_id = :c';
            $params['c'] = (int) $filters['category_id'];
        }
        if (!empty($filters['available_only'])) {
            $where[] = 'mi.is_available = 1';
        }
        if (!empty($filters['q'])) {
            $where[] = '(mi.name LIKE :q OR mi.description LIKE :q OR mi.sku LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        return Database::fetchAll(
            "SELECT mi.*, mc.name AS category_name, mc.slug AS category_slug
             FROM menu_items mi
             LEFT JOIN menu_categories mc ON mc.id = mi.category_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY mc.sort_order ASC, mi.sort_order ASC, mi.name ASC",
            $params
        );
    }

    public static function findById(int $tenantId, int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM menu_items WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL",
            ['id' => $id, 't' => $tenantId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert('menu_items', $data);
    }

    public static function update(int $tenantId, int $id, array $data): int
    {
        return Database::update('menu_items', $data, ['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function softDelete(int $tenantId, int $id): int
    {
        return self::update($tenantId, $id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public static function toggle(int $tenantId, int $id): void
    {
        Database::run(
            "UPDATE menu_items SET is_available = 1 - is_available
             WHERE id = :id AND tenant_id = :t",
            ['id' => $id, 't' => $tenantId]
        );
    }

    /**
     * Heuristica: encuentra el menu_item que mejor matchea un nombre escrito por
     * un cliente. Devuelve null si nada matchea con > 0.4 de similitud.
     */
    public static function findByNameFuzzy(int $tenantId, string $name): ?array
    {
        $name = trim($name);
        if ($name === '') return null;

        // Match exacto primero
        $row = Database::fetch(
            "SELECT * FROM menu_items
             WHERE tenant_id = :t AND deleted_at IS NULL AND is_available = 1
               AND (LOWER(name) = LOWER(:n) OR sku = :n)
             LIMIT 1",
            ['t' => $tenantId, 'n' => $name]
        );
        if ($row) return $row;

        // LIKE
        $row = Database::fetch(
            "SELECT * FROM menu_items
             WHERE tenant_id = :t AND deleted_at IS NULL AND is_available = 1
               AND name LIKE :q
             ORDER BY CHAR_LENGTH(name) ASC
             LIMIT 1",
            ['t' => $tenantId, 'q' => '%' . $name . '%']
        );
        if ($row) return $row;

        // Fuzzy similar_text
        $items = Database::fetchAll(
            "SELECT * FROM menu_items WHERE tenant_id = :t AND deleted_at IS NULL AND is_available = 1",
            ['t' => $tenantId]
        );
        $best = null; $bestScore = 0.0;
        foreach ($items as $i) {
            similar_text(mb_strtolower($name), mb_strtolower((string) $i['name']), $pct);
            if ($pct / 100 > $bestScore) {
                $bestScore = $pct / 100;
                $best = $i;
            }
        }
        return $bestScore >= 0.45 ? $best : null;
    }

    /**
     * Bloque para inyectar en el system prompt de la IA.
     * Limita por categoria y por total para mantener el prompt bajo ~6000 chars
     * y evitar agotar tokens / disparar timeouts.
     */
    public static function buildPromptBlock(int $tenantId, int $maxItems = 80, int $maxItemsPerCategory = 10, int $maxChars = 6000): string
    {
        $cats = MenuCategory::listForTenant($tenantId, true);
        if (empty($cats)) {
            $items = self::listForTenant($tenantId, ['available_only' => true]);
            if (empty($items)) return '';
            $out = "\nMENU DEL RESTAURANTE (precios en moneda local):\n";
            foreach (array_slice($items, 0, $maxItems) as $i) {
                $out .= self::formatItemLine($i);
                if (mb_strlen($out) > $maxChars) {
                    $out .= "[...mas opciones disponibles, pregunta al cliente que prefiere para detallar...]\n";
                    break;
                }
            }
            return $out;
        }

        $out = "\nMENU DEL RESTAURANTE (precios en moneda local):\n";
        $count = 0;
        foreach ($cats as $cat) {
            $items = self::listForTenant($tenantId, ['category_id' => (int) $cat['id'], 'available_only' => true]);
            if (empty($items)) continue;

            // Priorizar destacados primero
            usort($items, fn ($a, $b) => ((int) $b['is_featured']) <=> ((int) $a['is_featured']));

            $out .= "\n=== " . $cat['name'] . " ===\n";
            $perCategory = 0;
            foreach ($items as $i) {
                if ($count >= $maxItems) {
                    $out .= "[...y mas en otras categorias. Pregunta al cliente que tipo de plato busca.]\n";
                    return mb_substr($out, 0, $maxChars);
                }
                if ($perCategory >= $maxItemsPerCategory) {
                    $remaining = count($items) - $perCategory;
                    if ($remaining > 0) {
                        $out .= "(+ {$remaining} opciones mas en " . $cat['name'] . ")\n";
                    }
                    break;
                }
                $out .= self::formatItemLine($i);
                $count++;
                $perCategory++;
                if (mb_strlen($out) > $maxChars) return mb_substr($out, 0, $maxChars) . "\n[...truncado por tamano]\n";
            }
        }
        return $out;
    }

    private static function formatItemLine(array $i): string
    {
        $price = (float) ($i['price'] ?? 0);
        $cur   = (string) ($i['currency'] ?? 'USD');
        $desc  = trim((string) ($i['description'] ?? ''));
        $sku   = !empty($i['sku']) ? '#' . $i['sku'] : '';
        return sprintf("- %s%s — %s %.2f%s\n",
            $i['name'],
            $sku !== '' ? " ($sku)" : '',
            $cur,
            $price,
            $desc !== '' ? ' — ' . mb_substr($desc, 0, 100) : ''
        );
    }
}
