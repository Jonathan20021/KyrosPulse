<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Acceso unificado a archivos de configuracion bajo /config.
 * Carga lazy + soporte de notacion punteada: Config::get('app.name').
 */
final class Config
{
    private static array $items = [];
    private static string $path = '';

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, DIRECTORY_SEPARATOR);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file     = array_shift($segments);

        if (!isset(self::$items[$file])) {
            $filename = self::$path . DIRECTORY_SEPARATOR . $file . '.php';
            if (!is_file($filename)) {
                return $default;
            }
            self::$items[$file] = require $filename;
        }

        $value = self::$items[$file];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
