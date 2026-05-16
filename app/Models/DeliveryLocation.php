<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class DeliveryLocation extends Model
{
    protected static string $table = 'delivery_locations';

    public static function create(array $data): int
    {
        return Database::insert('delivery_locations', $data);
    }

    public static function latestForDelivery(int $deliveryId): ?array
    {
        return Database::fetch(
            "SELECT * FROM delivery_locations WHERE delivery_id = :d ORDER BY id DESC LIMIT 1",
            ['d' => $deliveryId]
        );
    }

    /**
     * Trail (puntos historicos) para dibujar la ruta en el tracking publico.
     * Limita a los ultimos N puntos para no agobiar al frontend.
     */
    public static function trailForDelivery(int $deliveryId, int $limit = 100): array
    {
        $limit = (int) max(1, min(500, $limit));
        return Database::fetchAll(
            "SELECT lat, lng, created_at FROM delivery_locations
             WHERE delivery_id = :d
             ORDER BY id DESC LIMIT $limit",
            ['d' => $deliveryId]
        );
    }
}
