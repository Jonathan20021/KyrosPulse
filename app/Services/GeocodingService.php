<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Geocodificacion via OpenStreetMap Nominatim (gratis, sin API key).
 * Cachea resultados en archivo local para no llamar dos veces el mismo texto.
 *
 * Politica de uso de Nominatim: max 1 req/s, User-Agent obligatorio.
 * Para alto trafico migrar a Mapbox/Google.
 */
final class GeocodingService
{
    private const CACHE_DIR = '/cache/geocode';
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /**
     * Devuelve ['lat' => float, 'lng' => float] o null si no se pudo resolver.
     */
    public static function geocode(string $address, ?string $countryHint = null): ?array
    {
        $address = trim($address);
        if ($address === '') return null;

        $key = md5(strtolower($address) . '|' . ($countryHint ?? ''));
        $cachePath = self::cacheFile($key);

        if (is_file($cachePath) && (time() - filemtime($cachePath)) < 86400 * 30) {
            $cached = @json_decode((string) file_get_contents($cachePath), true);
            if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
                return ['lat' => (float) $cached['lat'], 'lng' => (float) $cached['lng']];
            }
            if (is_array($cached) && ($cached['_miss'] ?? false)) {
                return null; // Cache negativo: ya intentamos y no resolvio
            }
        }

        $params = [
            'q'              => $address,
            'format'         => 'jsonv2',
            'limit'          => 1,
            'addressdetails' => 0,
        ];
        if ($countryHint) {
            $params['countrycodes'] = strtolower($countryHint);
        }
        $url = self::ENDPOINT . '?' . http_build_query($params);

        $result = self::httpGet($url);
        if (!$result) {
            @file_put_contents($cachePath, json_encode(['_miss' => true, 'at' => time()]));
            return null;
        }
        $data = @json_decode($result, true);
        if (!is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
            @file_put_contents($cachePath, json_encode(['_miss' => true, 'at' => time()]));
            return null;
        }
        $coords = ['lat' => (float) $data[0]['lat'], 'lng' => (float) $data[0]['lon']];
        @file_put_contents($cachePath, json_encode($coords));
        return $coords;
    }

    /**
     * Asegura que la delivery tenga pickup_lat/lng y dropoff_lat/lng.
     * Si no las tiene, intenta geocodificar a partir de los textos en orders y tenants.
     * Idempotente: si ya hay coords, no hace nada.
     */
    public static function backfillDelivery(array $delivery): array
    {
        $needsDropoff = empty($delivery['dropoff_lat']) || empty($delivery['dropoff_lng']);
        $needsPickup  = empty($delivery['pickup_lat'])  || empty($delivery['pickup_lng']);
        if (!$needsDropoff && !$needsPickup) return $delivery;

        $update = [];

        if ($needsDropoff) {
            $address = (string) ($delivery['dropoff_address'] ?? $delivery['order_address'] ?? '');
            if ($address !== '') {
                $coords = self::geocode($address, 'do');
                if ($coords) {
                    $update['dropoff_lat'] = $coords['lat'];
                    $update['dropoff_lng'] = $coords['lng'];
                    $delivery['dropoff_lat'] = $coords['lat'];
                    $delivery['dropoff_lng'] = $coords['lng'];
                }
            }
        }

        if ($needsPickup) {
            // Pickup viene del tenant (la direccion del local). Si el tenant tiene
            // address textual, geocodificar. Si no, intentar tomar la primera
            // localizacion conocida del driver.
            $tenantId = (int) ($delivery['tenant_id'] ?? 0);
            if ($tenantId) {
                $row = Database::fetch(
                    "SELECT address, restaurant_settings FROM tenants WHERE id = :t",
                    ['t' => $tenantId]
                );
                if ($row) {
                    // Primero buscar lat/lng explicitos en restaurant_settings JSON
                    $settings = is_string($row['restaurant_settings'] ?? null)
                        ? (json_decode((string) $row['restaurant_settings'], true) ?: [])
                        : [];
                    if (isset($settings['lat'], $settings['lng'])) {
                        $update['pickup_lat'] = (float) $settings['lat'];
                        $update['pickup_lng'] = (float) $settings['lng'];
                        $delivery['pickup_lat'] = $update['pickup_lat'];
                        $delivery['pickup_lng'] = $update['pickup_lng'];
                    } elseif (!empty($row['address'])) {
                        $coords = self::geocode((string) $row['address'], 'do');
                        if ($coords) {
                            $update['pickup_lat'] = $coords['lat'];
                            $update['pickup_lng'] = $coords['lng'];
                            $delivery['pickup_lat'] = $coords['lat'];
                            $delivery['pickup_lng'] = $coords['lng'];
                            // Cachear en restaurant_settings para futuras llamadas
                            $settings['lat'] = $coords['lat'];
                            $settings['lng'] = $coords['lng'];
                            Database::update('tenants', [
                                'restaurant_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                            ], ['id' => $tenantId]);
                        }
                    }
                }
            }
        }

        if ($update && !empty($delivery['id'])) {
            Database::update('deliveries', $update, ['id' => (int) $delivery['id']]);
        }
        return $delivery;
    }

    private static function cacheFile(string $key): string
    {
        $dir = (string) \App\Core\Config::get('app.paths.storage') . self::CACHE_DIR;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir . '/' . $key . '.json';
    }

    private static function httpGet(string $url): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => "User-Agent: KyrosPulse/1.0 (delivery-module)\r\nAccept: application/json\r\n",
                'timeout'       => 6,
                'ignore_errors' => true,
            ],
        ]);
        try {
            $body = @file_get_contents($url, false, $ctx);
            return $body !== false ? $body : null;
        } catch (\Throwable $e) {
            Logger::warning('Geocoding GET fallo', ['msg' => $e->getMessage()]);
            return null;
        }
    }
}
