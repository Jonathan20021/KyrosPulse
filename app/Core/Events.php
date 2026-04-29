<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sistema de eventos minimo. Cualquier parte de la app puede emitir eventos
 * y el motor de automatizaciones (App\Services\AutomationEngine) los escucha
 * para evaluar reglas configuradas por el tenant.
 *
 * Uso:
 *   Events::listen('contact.created', fn($p) => ...);
 *   Events::dispatch('contact.created', ['tenant_id' => 1, 'contact_id' => 42]);
 */
final class Events
{
    /** @var array<string, array<int, callable>> */
    private static array $listeners = [];

    public static function listen(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    public static function dispatch(string $event, array $payload = []): void
    {
        $payload['_event'] = $event;
        $payload['_timestamp'] = time();

        foreach (self::$listeners[$event] ?? [] as $listener) {
            try {
                $listener($payload);
            } catch (\Throwable $e) {
                Logger::error("Error en listener de $event", ['msg' => $e->getMessage()]);
            }
        }

        // Wildcard: '*' siempre se ejecuta
        foreach (self::$listeners['*'] ?? [] as $listener) {
            try {
                $listener($payload);
            } catch (\Throwable $e) {
                Logger::error("Error en listener wildcard", ['msg' => $e->getMessage()]);
            }
        }
    }

    public static function reset(): void
    {
        self::$listeners = [];
    }
}
