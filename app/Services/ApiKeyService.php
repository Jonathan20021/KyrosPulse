<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ApiKey;

/**
 * Generacion, verificacion y rate-limiting de API keys.
 *
 * Formato del token: kp_live_<32 chars random>
 *   - prefix:  "kp_live_" + primeros 8 chars (display, no secreto)
 *   - secret:  todo el string (solo se muestra UNA vez al crearse)
 *   - hash:    sha256(secret) almacenado en BD
 *
 * Verificacion: hash el bearer recibido, lookup por key_hash con scope checks.
 */
final class ApiKeyService
{
    public const SCOPES_AVAILABLE = [
        'agents.read'    => 'Listar y leer agentes IA',
        'agents.run'     => 'Ejecutar un agente IA con input',
        'agents.write'   => 'Crear/editar/borrar agentes y skills',
        'contacts.read'  => 'Leer contactos',
        'contacts.write' => 'Crear/editar contactos',
        'orders.read'    => 'Leer ordenes',
        'orders.write'   => 'Crear/editar ordenes',
        'messages.send'  => 'Enviar mensajes WhatsApp',
        'webhooks.read'  => 'Listar webhooks salientes y sus entregas',
        'webhooks.write' => 'Crear/editar/borrar webhooks salientes',
        'dashboard.read' => 'Leer snapshot ejecutivo del dashboard',
        '*'              => 'Acceso completo (admin)',
    ];

    /** Limite global por defecto: 600 requests/min por key. Override en config('api'). */
    private const DEFAULT_RATE_PER_MIN = 600;

    /**
     * Genera una nueva API key. Devuelve ['id', 'plain_key', 'prefix', 'last4'].
     * El plain_key SOLO se devuelve aqui — no se vuelve a recuperar.
     */
    public static function generate(int $tenantId, string $name, array $scopes = ['*'], ?int $createdBy = null, ?string $expiresAt = null, ?array $allowedIps = null): array
    {
        // 32 chars URL-safe (base62 simulado con alphanumeric)
        $secret = self::randomString(32);
        $plain  = 'kp_live_' . $secret;
        $prefix = 'kp_live_' . substr($secret, 0, 8);
        $last4  = substr($secret, -4);
        $hash   = hash('sha256', $plain);

        $id = ApiKey::create([
            'tenant_id'   => $tenantId,
            'created_by'  => $createdBy,
            'name'        => substr($name, 0, 120) ?: 'Default key',
            'prefix'      => $prefix,
            'key_hash'    => $hash,
            'last4'       => $last4,
            'scopes'      => json_encode(array_values(array_unique($scopes)), JSON_UNESCAPED_SLASHES),
            'allowed_ips' => $allowedIps ? json_encode($allowedIps) : null,
            'expires_at'  => $expiresAt,
        ]);

        return [
            'id'        => $id,
            'plain_key' => $plain,
            'prefix'    => $prefix,
            'last4'     => $last4,
        ];
    }

    /**
     * Verifica un Bearer token y devuelve la fila de api_keys si es valido.
     * Retorna null si no existe / esta revocado / esta caducado.
     */
    public static function verify(string $bearer): ?array
    {
        $bearer = trim($bearer);
        if ($bearer === '' || !str_starts_with($bearer, 'kp_')) {
            return null;
        }
        // Defensa: rechazar tokens absurdamente largos antes de hashear
        if (strlen($bearer) > 128) return null;

        $hash = hash('sha256', $bearer);
        $row  = ApiKey::findActiveByHash($hash);
        if (!$row) return null;

        return $row;
    }

    /**
     * Verifica que el token tiene un scope determinado.
     * "*" otorga acceso a todo. Si el scope pedido es "agents.read", aceptamos
     * tambien "agents.*" (wildcard de seccion).
     */
    public static function hasScope(array $key, string $required): bool
    {
        $scopes = self::decodeScopes($key);
        if (in_array('*', $scopes, true)) return true;
        if (in_array($required, $scopes, true)) return true;

        // wildcard por seccion: "agents.*" cubre "agents.read", "agents.run", etc.
        $section = explode('.', $required, 2)[0] ?? '';
        if ($section !== '' && in_array($section . '.*', $scopes, true)) return true;

        return false;
    }

    public static function decodeScopes(array $key): array
    {
        $raw = $key['scopes'] ?? null;
        if (!$raw) return [];
        if (is_array($raw)) return $raw;
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Verifica si la IP del request es aceptada por la whitelist (si existe).
     */
    public static function ipAllowed(array $key, string $ip): bool
    {
        $raw = $key['allowed_ips'] ?? null;
        if (!$raw) return true; // sin whitelist = todo permitido
        $list = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($list) || empty($list)) return true;

        foreach ($list as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') continue;
            if ($entry === $ip) return true;
            if (str_contains($entry, '/') && self::ipInCidr($ip, $entry)) return true;
        }
        return false;
    }

    /**
     * Sliding-window simple por (key_hash, bucket) en MySQL.
     * Devuelve ['ok' => bool, 'remaining' => int, 'retry_after' => seconds].
     */
    public static function rateLimit(string $keyHash, string $bucket = 'global', int $maxPerMin = self::DEFAULT_RATE_PER_MIN): array
    {
        // Limpieza oportunista (1% de las requests)
        if (random_int(1, 100) === 1) {
            Database::run("DELETE FROM `api_rate_limits` WHERE `expires_at` < NOW()");
        }

        $row = Database::fetch(
            "SELECT * FROM `api_rate_limits` WHERE `key_hash` = :h AND `bucket` = :b LIMIT 1",
            ['h' => $keyHash, 'b' => $bucket]
        );

        if ($row && strtotime($row['expires_at']) > time()) {
            $attempts = (int) $row['attempts'] + 1;
            if ($attempts > $maxPerMin) {
                return [
                    'ok'          => false,
                    'remaining'   => 0,
                    'retry_after' => max(1, strtotime($row['expires_at']) - time()),
                    'limit'       => $maxPerMin,
                ];
            }
            Database::run(
                "UPDATE `api_rate_limits` SET `attempts` = :a WHERE `id` = :i",
                ['a' => $attempts, 'i' => (int) $row['id']]
            );
            return [
                'ok'          => true,
                'remaining'   => $maxPerMin - $attempts,
                'retry_after' => 0,
                'limit'       => $maxPerMin,
            ];
        }

        // Crear o resetear ventana
        $expires = date('Y-m-d H:i:s', time() + 60);
        if ($row) {
            Database::run(
                "UPDATE `api_rate_limits` SET `attempts` = 1, `expires_at` = :e WHERE `id` = :i",
                ['e' => $expires, 'i' => (int) $row['id']]
            );
        } else {
            Database::run(
                "INSERT INTO `api_rate_limits` (`key_hash`, `bucket`, `attempts`, `expires_at`)
                 VALUES (:h, :b, 1, :e)",
                ['h' => $keyHash, 'b' => $bucket, 'e' => $expires]
            );
        }

        return [
            'ok'          => true,
            'remaining'   => $maxPerMin - 1,
            'retry_after' => 0,
            'limit'       => $maxPerMin,
        ];
    }

    public static function logRequest(?array $key, string $method, string $endpoint, int $status, int $latencyMs, string $ip, string $ua, ?string $requestId = null, ?string $error = null, int $bytesIn = 0, int $bytesOut = 0): void
    {
        try {
            Database::insert('api_request_logs', [
                'api_key_id'  => $key['id'] ?? null,
                'tenant_id'   => $key['tenant_id'] ?? null,
                'method'      => substr($method, 0, 8),
                'endpoint'    => substr($endpoint, 0, 255),
                'status_code' => $status,
                'latency_ms'  => $latencyMs,
                'ip'          => substr($ip, 0, 64),
                'user_agent'  => substr($ua, 0, 255),
                'request_id'  => $requestId,
                'bytes_in'    => $bytesIn,
                'bytes_out'   => $bytesOut,
                'error'       => $error ? substr($error, 0, 255) : null,
            ]);
        } catch (\Throwable) {
            // Nunca tirar el request si el log falla
        }
    }

    private static function randomString(int $len): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $aLen = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, $aLen)];
        }
        return $out;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) return $ip === $cidr;
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipL  = ip2long($ip);
            $sL   = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            return ($ipL & $mask) === ($sL & $mask);
        }
        // IPv6: comparacion binaria
        $ipBin = @inet_pton($ip);
        $sBin  = @inet_pton($subnet);
        if (!$ipBin || !$sBin || strlen($ipBin) !== strlen($sBin)) return false;
        $bytes = intdiv($bits, 8);
        $rem   = $bits % 8;
        if (substr($ipBin, 0, $bytes) !== substr($sBin, 0, $bytes)) return false;
        if ($rem === 0) return true;
        $mask = chr(0xFF << (8 - $rem) & 0xFF);
        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($sBin[$bytes]) & ord($mask));
    }
}
