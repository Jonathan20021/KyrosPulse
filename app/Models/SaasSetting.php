<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Settings globales del SaaS (key/value). Cacheado en memoria por request.
 */
final class SaasSetting
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;
        try {
            $rows = Database::fetchAll("SELECT setting_key, value, kind FROM saas_settings");
        } catch (\Throwable) {
            return self::$cache = [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = self::cast($r['value'], (string) ($r['kind'] ?? 'string'));
        }
        return self::$cache = $out;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, mixed $value, string $kind = 'string'): void
    {
        $stringValue = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : (string) $value;
        $existing = Database::fetch("SELECT id FROM saas_settings WHERE setting_key = :k", ['k' => $key]);
        if ($existing) {
            Database::update('saas_settings', ['value' => $stringValue, 'kind' => $kind], ['id' => (int) $existing['id']]);
        } else {
            Database::insert('saas_settings', ['setting_key' => $key, 'value' => $stringValue, 'kind' => $kind]);
        }
        self::$cache = null;
    }

    public static function setMany(array $kv, string $defaultKind = 'string'): void
    {
        foreach ($kv as $k => $v) self::set((string) $k, $v, $defaultKind);
    }

    private static function cast(?string $raw, string $kind): mixed
    {
        return match ($kind) {
            'bool', 'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'int', 'integer' => (int) $raw,
            'float'          => (float) $raw,
            'json'           => $raw !== null && $raw !== '' ? json_decode($raw, true) : null,
            default          => (string) $raw,
        };
    }
}
