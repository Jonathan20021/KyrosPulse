<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Registro de auditoria. Captura quien, que, cuando y cambios.
 */
final class Audit
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $oldValues = [],
        array $newValues = []
    ): void {
        try {
            Database::insert('audit_logs', [
                'tenant_id'   => Tenant::id(),
                'user_id'     => Auth::id(),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'old_values'  => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values'  => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Audit log fallo', ['msg' => $e->getMessage()]);
        }
    }
}
