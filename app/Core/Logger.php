<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Logger minimo, con archivos por dia.
 */
final class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3, 'critical' => 4];

    public static function log(string $level, string $message, array $context = []): void
    {
        $configuredLevel = (string) Config::get('app.log.level', 'debug');
        $threshold = self::LEVELS[$configuredLevel] ?? 0;
        $current   = self::LEVELS[$level] ?? 0;
        if ($current < $threshold) {
            return;
        }

        $logsPath = Config::get('app.paths.logs');
        if (!is_dir($logsPath)) {
            @mkdir($logsPath, 0775, true);
        }

        $file = $logsPath . DIRECTORY_SEPARATOR . 'kyros-' . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            date('Y-m-d H:i:s'),
            Config::get('app.env', 'local'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $msg, array $ctx = []): void    { self::log('debug', $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void     { self::log('info', $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void  { self::log('warning', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void    { self::log('error', $msg, $ctx); }
    public static function critical(string $msg, array $ctx = []): void { self::log('critical', $msg, $ctx); }
}
