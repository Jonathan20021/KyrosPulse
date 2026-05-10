<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Gestion central de limites de licencia por tenant.
 *
 * Hoy controla el limite de "clientes" (contactos del CRM). Otras cuotas
 * existentes (mensajes, IA tokens) siguen viviendo en sus servicios propios.
 *
 * Resolucion del limite efectivo de clientes:
 *   1) tenants.max_contacts_override   (si no es NULL)
 *   2) plans.max_contacts              (heredado del plan asignado)
 *   3) DEFAULT_FALLBACK                (por si no hay plan)
 */
final class LicenseService
{
    private const DEFAULT_FALLBACK = 1000;
    private const WARN_THRESHOLD   = 0.85; // a partir de 85% se muestra advertencia
    private const NEAR_THRESHOLD   = 0.95; // a partir de 95% se considera "lleno"

    /**
     * Snapshot completo del estado de licencia del tenant para clientes.
     *
     * @return array{
     *     limit:int,
     *     used:int,
     *     remaining:int,
     *     percent:int,
     *     locked:bool,
     *     source:string,
     *     warn:bool,
     *     near_full:bool,
     *     full:bool
     * }
     */
    public static function clientsSnapshot(int $tenantId): array
    {
        $row = Database::fetch(
            "SELECT t.id, t.plan_id, t.max_contacts_override, t.client_limit_locked,
                    t.clients_count_cached, p.max_contacts AS plan_max_contacts
               FROM tenants t
               LEFT JOIN plans p ON p.id = t.plan_id
              WHERE t.id = :id",
            ['id' => $tenantId]
        );

        $override = $row['max_contacts_override'] ?? null;
        $planMax  = $row['plan_max_contacts'] ?? null;

        if ($override !== null) {
            $limit  = (int) $override;
            $source = 'override';
        } elseif ($planMax !== null) {
            $limit  = (int) $planMax;
            $source = 'plan';
        } else {
            $limit  = self::DEFAULT_FALLBACK;
            $source = 'default';
        }
        if ($limit < 0) $limit = 0;

        $used = self::countClients($tenantId);

        $remaining = max(0, $limit - $used);
        $percent   = $limit > 0 ? (int) min(100, round(($used / $limit) * 100)) : 0;
        $locked    = $limit > 0 ? (bool) ($row['client_limit_locked'] ?? 1) : false;

        return [
            'limit'     => $limit,
            'used'      => $used,
            'remaining' => $remaining,
            'percent'   => $percent,
            'locked'    => $locked,
            'source'    => $source,
            'warn'      => $limit > 0 && $percent >= (int) (self::WARN_THRESHOLD * 100),
            'near_full' => $limit > 0 && $percent >= (int) (self::NEAR_THRESHOLD * 100),
            'full'      => $limit > 0 && $used >= $limit,
        ];
    }

    /**
     * Cuenta los clientes (contactos no eliminados) del tenant. Usa el cache si
     * esta presente; recalcula y persiste si no.
     */
    public static function countClients(int $tenantId): int
    {
        $cached = Database::fetchColumn(
            "SELECT clients_count_cached FROM tenants WHERE id = :id",
            ['id' => $tenantId]
        );
        if ($cached !== null && $cached !== false) {
            return (int) $cached;
        }
        return self::recountAndCache($tenantId);
    }

    /**
     * Recalcula y persiste el contador. Llamar cada vez que cambie el numero de
     * contactos (alta, baja, soft-delete, importacion).
     */
    public static function recountAndCache(int $tenantId): int
    {
        $count = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM contacts WHERE tenant_id = :t AND deleted_at IS NULL",
            ['t' => $tenantId]
        );
        Database::update('tenants', ['clients_count_cached' => $count], ['id' => $tenantId]);
        return $count;
    }

    /**
     * Verifica si el tenant puede crear N clientes mas. Si hay bloqueo duro y
     * se pasaria del limite, retorna false. Limite suave (locked=0) siempre OK.
     */
    public static function canAddClients(int $tenantId, int $newOnes = 1): bool
    {
        $snap = self::clientsSnapshot($tenantId);
        if (!$snap['locked'] || $snap['limit'] <= 0) {
            return true;
        }
        return ($snap['used'] + $newOnes) <= $snap['limit'];
    }

    /**
     * Hook a llamar cuando se rechace una creacion por limite. Marca cuando
     * se golpeo el limite por ultima vez (para alertas en panel admin).
     */
    public static function markLimitHit(int $tenantId): void
    {
        Database::update('tenants', ['client_limit_hit_at' => date('Y-m-d H:i:s')], ['id' => $tenantId]);
    }

    /**
     * Mensaje de error consistente para flashes/JSON cuando se rechaza una alta.
     */
    public static function limitMessage(array $snap): string
    {
        return sprintf(
            'Limite de clientes alcanzado (%d/%d). Solicita ampliar tu licencia o elimina contactos antiguos.',
            (int) $snap['used'],
            (int) $snap['limit']
        );
    }

    /**
     * Listado para el panel super admin: cada tenant con su uso, plan,
     * override y limite efectivo. Ordenado por mayor uso primero.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function tenantsOverview(): array
    {
        $rows = Database::fetchAll(
            "SELECT t.id, t.uuid, t.name, t.email, t.status, t.plan_id,
                    t.max_contacts_override, t.client_limit_locked,
                    t.clients_count_cached, t.client_limit_hit_at,
                    p.name AS plan_name, p.max_contacts AS plan_max_contacts,
                    p.price_monthly,
                    (SELECT COUNT(*) FROM contacts c WHERE c.tenant_id = t.id AND c.deleted_at IS NULL) AS clients_real
               FROM tenants t
               LEFT JOIN plans p ON p.id = t.plan_id
              WHERE t.deleted_at IS NULL
              ORDER BY clients_real DESC, t.created_at DESC"
        );

        $out = [];
        foreach ($rows as $r) {
            $override = $r['max_contacts_override'];
            $planMax  = $r['plan_max_contacts'];
            if ($override !== null) {
                $limit  = (int) $override;
                $source = 'override';
            } elseif ($planMax !== null) {
                $limit  = (int) $planMax;
                $source = 'plan';
            } else {
                $limit  = self::DEFAULT_FALLBACK;
                $source = 'default';
            }
            $used    = (int) $r['clients_real'];
            $percent = $limit > 0 ? (int) min(100, round(($used / $limit) * 100)) : 0;

            // Sincronizar cache si quedo desactualizado
            if ((int) ($r['clients_count_cached'] ?? -1) !== $used) {
                Database::update('tenants', ['clients_count_cached' => $used], ['id' => (int) $r['id']]);
            }

            $r['limit_effective']  = $limit;
            $r['limit_source']     = $source;
            $r['clients_used']     = $used;
            $r['clients_percent']  = $percent;
            $r['clients_remaining']= max(0, $limit - $used);
            $r['locked_bool']      = (bool) $r['client_limit_locked'];
            $out[] = $r;
        }
        return $out;
    }
}
