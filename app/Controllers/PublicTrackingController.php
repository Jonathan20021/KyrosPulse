<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Models\Delivery;
use App\Models\DeliveryLocation;

/**
 * Tracking publico para el cliente final. Una sola URL por delivery:
 *   GET  /d/{token}            -> vista HTML con mapa + estado
 *   GET  /d/{token}/feed       -> JSON con posicion del driver + estado
 *   POST /d/{token}/rate       -> rating del cliente al driver
 *
 * Sin auth: el token UUID es la unica credencial. Si el cliente lo comparte,
 * cualquiera puede ver el seguimiento (es el mismo modelo de Uber Eats).
 */
final class PublicTrackingController extends Controller
{
    public function show(Request $request, array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $delivery = Delivery::findByToken($token);
        if (!$delivery) {
            http_response_code(404);
            echo 'Tracking link invalido o expirado.';
            return;
        }
        // Backfill silencioso de coordenadas pickup/dropoff (idempotente).
        $delivery = \App\Services\GeocodingService::backfillDelivery($delivery);
        $this->view('tracking.show', [
            'delivery' => $delivery,
            'statuses' => Delivery::STATUSES,
        ], 'layouts.driver');
    }

    public function feed(Request $request, array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $delivery = Delivery::findByToken($token);
        if (!$delivery) {
            $this->json(['success' => false], 404);
            return;
        }
        // Backfill: si la entrega no tiene pickup_lat/lng o dropoff_lat/lng,
        // intentamos geocodificar y guardamos el resultado en la fila.
        $delivery = \App\Services\GeocodingService::backfillDelivery($delivery);
        $latest = DeliveryLocation::latestForDelivery((int) $delivery['id']);
        $trail = DeliveryLocation::trailForDelivery((int) $delivery['id'], 50);
        $this->json([
            'success'      => true,
            'status'       => (string) $delivery['status'],
            'status_label' => Delivery::STATUSES[$delivery['status']][0] ?? $delivery['status'],
            'driver'       => $delivery['driver_id'] ? [
                'name'    => (string) ($delivery['driver_name'] ?? ''),
                'phone'   => (string) ($delivery['driver_phone'] ?? ''),
                'vehicle' => (string) ($delivery['vehicle_type'] ?? ''),
            ] : null,
            'driver_position' => $latest ? [
                'lat' => (float) $latest['lat'],
                'lng' => (float) $latest['lng'],
                'at'  => (string) $latest['created_at'],
            ] : ($delivery['driver_lat'] ? [
                'lat' => (float) $delivery['driver_lat'],
                'lng' => (float) $delivery['driver_lng'],
                'at'  => (string) ($delivery['driver_ping'] ?? ''),
            ] : null),
            'trail'    => array_map(fn($p) => [
                'lat' => (float) $p['lat'],
                'lng' => (float) $p['lng'],
            ], $trail),
            'pickup'   => $delivery['pickup_lat'] ? ['lat' => (float) $delivery['pickup_lat'], 'lng' => (float) $delivery['pickup_lng']] : null,
            'dropoff'  => $delivery['dropoff_lat'] ? ['lat' => (float) $delivery['dropoff_lat'], 'lng' => (float) $delivery['dropoff_lng']] : null,
            'eta_min'  => $delivery['eta_minutes'] !== null ? (int) $delivery['eta_minutes'] : null,
            'server_time' => date('c'),
        ]);
    }

    public function rate(Request $request, array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $delivery = Delivery::findByToken($token);
        if (!$delivery) {
            $this->json(['success' => false], 404);
            return;
        }
        if ($delivery['status'] !== 'delivered') {
            $this->json(['success' => false, 'message' => 'Aun no entregado.'], 422);
            return;
        }
        $rating = max(1, min(5, (int) $request->input('rating', 0)));
        $feedback = trim((string) $request->input('feedback', ''));

        Database::update('deliveries', [
            'customer_rating'   => $rating,
            'customer_feedback' => $feedback ?: null,
        ], ['id' => (int) $delivery['id']]);

        // Promediar rating del driver
        if (!empty($delivery['driver_id'])) {
            $agg = Database::fetch(
                "SELECT AVG(customer_rating) AS avg, COUNT(customer_rating) AS c
                 FROM deliveries WHERE driver_id = :d AND customer_rating IS NOT NULL",
                ['d' => (int) $delivery['driver_id']]
            );
            Database::update('drivers', [
                'rating_avg'   => round((float) ($agg['avg'] ?? 0), 2),
                'rating_count' => (int) ($agg['c'] ?? 0),
            ], ['id' => (int) $delivery['driver_id']]);
        }

        $this->json(['success' => true]);
    }
}
