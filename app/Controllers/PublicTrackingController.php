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

    /**
     * Server-Sent Events: empuja al cliente cambios en tiempo real (posicion
     * del driver, estado, ETA) sin que tenga que hacer polling. Misma idea
     * que InboxStreamController. El cliente usa EventSource; si el server
     * cierra la conexion al MAX_DURATION, el browser reconecta solo.
     *
     *   GET /d/{token}/stream
     *
     * Eventos:
     *   open            -> snapshot inicial
     *   driver_position -> {lat, lng, at}
     *   status_change   -> {status, label}
     *   heartbeat       -> {ts}
     *   close           -> {reason}
     */
    public function stream(Request $request, array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $delivery = Delivery::findByToken($token);
        if (!$delivery) {
            http_response_code(404);
            header('Content-Type: text/event-stream');
            echo "event: error\ndata: " . json_encode(['error' => 'not_found']) . "\n\n";
            return;
        }
        $delivery = \App\Services\GeocodingService::backfillDelivery($delivery);
        $deliveryId = (int) $delivery['id'];

        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) ob_end_clean();

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');

        ignore_user_abort(false);
        $maxDuration = 55;
        set_time_limit($maxDuration + 10);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Snapshot inicial
        $this->sendEvent('open', $this->buildSnapshot($delivery));

        $lastStatus    = (string) $delivery['status'];
        $lastLocId     = (int) (Database::fetchColumn(
            "SELECT COALESCE(MAX(id),0) FROM delivery_locations WHERE delivery_id = :d",
            ['d' => $deliveryId]
        ) ?? 0);
        $lastDriverId  = $delivery['driver_id'] ? (int) $delivery['driver_id'] : 0;
        $lastEventAt   = time();
        $started       = time();

        while (true) {
            if (connection_aborted()) break;
            if (time() - $started >= $maxDuration) break;

            // Posicion nueva del driver?
            $newLoc = Database::fetch(
                "SELECT id, lat, lng, created_at FROM delivery_locations
                 WHERE delivery_id = :d AND id > :s
                 ORDER BY id DESC LIMIT 1",
                ['d' => $deliveryId, 's' => $lastLocId]
            );
            if ($newLoc) {
                $this->sendEvent('driver_position', [
                    'lat' => (float) $newLoc['lat'],
                    'lng' => (float) $newLoc['lng'],
                    'at'  => (string) $newLoc['created_at'],
                ]);
                $lastLocId = (int) $newLoc['id'];
                $lastEventAt = time();
            }

            // Cambio de estado o de driver asignado?
            $fresh = Database::fetch(
                "SELECT status, driver_id FROM deliveries WHERE id = :id",
                ['id' => $deliveryId]
            );
            if ($fresh) {
                if ((string) $fresh['status'] !== $lastStatus) {
                    $newStatus = (string) $fresh['status'];
                    $lbl = Delivery::STATUSES[$newStatus][0] ?? $newStatus;
                    $this->sendEvent('status_change', [
                        'status' => $newStatus,
                        'label'  => $lbl,
                    ]);
                    $lastStatus = $newStatus;
                    $lastEventAt = time();
                }
                $currentDriverId = $fresh['driver_id'] ? (int) $fresh['driver_id'] : 0;
                if ($currentDriverId !== $lastDriverId) {
                    // Reenviar snapshot completo cuando cambia el driver asignado
                    $reloaded = Delivery::findByToken($token);
                    if ($reloaded) {
                        $this->sendEvent('driver_assigned', $this->buildSnapshot($reloaded));
                    }
                    $lastDriverId = $currentDriverId;
                    $lastEventAt = time();
                }
            }

            // Heartbeat
            if (time() - $lastEventAt >= 15) {
                $this->sendEvent('heartbeat', ['ts' => time()]);
                $lastEventAt = time();
            }

            // Poll cada 1.5s — sentido "live"
            for ($i = 0; $i < 15; $i++) {
                if (connection_aborted()) break 2;
                usleep(100_000);
            }
        }

        $this->sendEvent('close', ['reason' => 'max_duration']);
    }

    private function buildSnapshot(array $delivery): array
    {
        $latest = DeliveryLocation::latestForDelivery((int) $delivery['id']);
        $trail = DeliveryLocation::trailForDelivery((int) $delivery['id'], 50);
        return [
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
            'trail'   => array_map(fn($p) => ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']], $trail),
            'pickup'  => $delivery['pickup_lat'] ? ['lat' => (float) $delivery['pickup_lat'], 'lng' => (float) $delivery['pickup_lng']] : null,
            'dropoff' => $delivery['dropoff_lat'] ? ['lat' => (float) $delivery['dropoff_lat'], 'lng' => (float) $delivery['dropoff_lng']] : null,
            'eta_min' => $delivery['eta_minutes'] !== null ? (int) $delivery['eta_minutes'] : null,
            'ts'      => time(),
        ];
    }

    private function sendEvent(string $event, array $data): void
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) return;
        echo "event: " . $event . "\n";
        echo "data: " . $payload . "\n\n";
        if (function_exists('flush')) @flush();
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
