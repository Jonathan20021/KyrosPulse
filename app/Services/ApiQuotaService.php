<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Gestion de cuota mensual de API calls por tenant.
 *
 * Cuota efectiva (en orden):
 *   1. tenants.api_quota_override (si NULL, se ignora)
 *   2. plans.api_quota_monthly (del plan asignado)
 *   3. 1000 (fallback)
 *
 * Valores especiales:
 *   -1 = ilimitado (enterprise)
 *    0 = sin acceso al API (rechaza todo)
 *   >0 = limite numerico
 *
 * Periodo: ventana mensual rolling (30 dias) desde api_period_starts_at.
 * Si el periodo vencio, contador se resetea automaticamente al primer hit.
 */
final class ApiQuotaService
{
    /** Snapshot completo de cuota + uso para un tenant. */
    public static function snapshot(int $tenantId): array
    {
        try {
            $tenant = Database::fetch(
                "SELECT t.id, t.plan_id, t.api_quota_override, t.api_calls_period, t.api_period_starts_at,
                        p.api_quota_monthly AS plan_quota, p.name AS plan_name, p.slug AS plan_slug
                 FROM `tenants` t
                 LEFT JOIN `plans` p ON p.id = t.plan_id
                 WHERE t.id = :i LIMIT 1",
                ['i' => $tenantId]
            );
        } catch (\Throwable $e) {
            Logger::warning('ApiQuotaService snapshot fallo', ['msg' => $e->getMessage()]);
            return self::empty($tenantId);
        }
        if (!$tenant) return self::empty($tenantId);

        $quota = self::resolveQuota($tenant);
        [$used, $periodStart] = self::resolveCurrentUsage($tenant);

        $remaining = $quota === -1 ? -1 : max(0, $quota - $used);
        $usedPct   = ($quota > 0) ? min(100, (int) round(($used / $quota) * 100)) : 0;

        // Calcular renovacion: 30 dias desde periodStart
        $resetAt = $periodStart ? strtotime($periodStart) + (30 * 86400) : (time() + 30 * 86400);

        return [
            'tenant_id'        => $tenantId,
            'plan_name'        => $tenant['plan_name'] ?? null,
            'plan_slug'        => $tenant['plan_slug'] ?? null,
            'quota'            => $quota,                          // -1 unlimited, 0 no access, >0 limit
            'used'             => $used,
            'remaining'        => $remaining,
            'used_pct'         => $usedPct,
            'unlimited'        => $quota === -1,
            'no_access'        => $quota === 0,
            'period_starts_at' => $periodStart,
            'period_resets_at' => date('Y-m-d H:i:s', $resetAt),
            'is_overridden'    => isset($tenant['api_quota_override']) && $tenant['api_quota_override'] !== null,
        ];
    }

    /**
     * Incrementa el contador del periodo en 1 y devuelve true si el call
     * estaba dentro de cuota (false si excedio). Auto-resetea el periodo
     * si ya vencio. Llamado desde ApiAuthMiddleware antes del scope check.
     */
    public static function consume(int $tenantId): array
    {
        try {
            $tenant = Database::fetch(
                "SELECT t.id, t.plan_id, t.api_quota_override, t.api_calls_period, t.api_period_starts_at,
                        p.api_quota_monthly AS plan_quota
                 FROM `tenants` t
                 LEFT JOIN `plans` p ON p.id = t.plan_id
                 WHERE t.id = :i LIMIT 1",
                ['i' => $tenantId]
            );
            if (!$tenant) {
                return ['ok' => true, 'reason' => 'tenant_not_found'];
            }

            $quota = self::resolveQuota($tenant);

            // Sin acceso
            if ($quota === 0) {
                return ['ok' => false, 'reason' => 'no_api_access', 'quota' => 0, 'used' => 0, 'remaining' => 0];
            }

            // Ilimitado: solo registrar, no chequear
            [$used, $periodStart] = self::resolveCurrentUsage($tenant);
            if ($quota === -1) {
                self::bumpCounter($tenantId, $periodStart, $used + 1);
                return ['ok' => true, 'quota' => -1, 'used' => $used + 1, 'remaining' => -1];
            }

            // Verificar antes de incrementar
            if ($used >= $quota) {
                // Alerta 100% inline (con cooldown anti-spam)
                self::fireQuotaAlert($tenantId, 'api.quota.100', 100, $used, $quota, $tenant);
                return ['ok' => false, 'reason' => 'quota_exceeded', 'quota' => $quota, 'used' => $used, 'remaining' => 0];
            }

            $newUsed = $used + 1;
            self::bumpCounter($tenantId, $periodStart, $newUsed);

            // Alerta 80% inline (justo cuando cruza el umbral, no en cada hit posterior)
            $prevPct = $quota > 0 ? (int) round(($used   / $quota) * 100) : 0;
            $newPct  = $quota > 0 ? (int) round(($newUsed / $quota) * 100) : 0;
            if ($prevPct < 80 && $newPct >= 80 && $newPct < 100) {
                self::fireQuotaAlert($tenantId, 'api.quota.80', $newPct, $newUsed, $quota, $tenant);
            }

            return ['ok' => true, 'quota' => $quota, 'used' => $newUsed, 'remaining' => $quota - $newUsed];
        } catch (\Throwable $e) {
            Logger::warning('ApiQuotaService consume fallo', ['msg' => $e->getMessage()]);
            return ['ok' => true, 'reason' => 'db_error'];
        }
    }

    /** Reset manual (super admin lo usa). */
    public static function resetPeriod(int $tenantId): void
    {
        Database::run(
            "UPDATE `tenants`
             SET `api_calls_period` = 0,
                 `api_period_starts_at` = NOW(),
                 `api_quota_alerted_at` = NULL
             WHERE `id` = :i",
            ['i' => $tenantId]
        );
    }

    /** Super admin override de cuota; NULL = volver a usar la del plan. */
    public static function setOverride(int $tenantId, ?int $newQuota): void
    {
        Database::run(
            "UPDATE `tenants` SET `api_quota_override` = :q WHERE `id` = :i",
            ['q' => $newQuota, 'i' => $tenantId]
        );
    }

    /**
     * Resuelve cuota efectiva del tenant. Devuelve -1 (unlimited),
     * 0 (no access) o entero positivo.
     */
    public static function resolveQuota(array $tenant): int
    {
        $override = $tenant['api_quota_override'] ?? null;
        if ($override !== null) return (int) $override;
        $planQuota = $tenant['plan_quota'] ?? null;
        if ($planQuota !== null) return (int) $planQuota;
        return 1000;
    }

    /**
     * Lee el contador actual aplicando reset si el periodo vencio.
     * Devuelve [used, period_starts_at_after_reset].
     */
    private static function resolveCurrentUsage(array $tenant): array
    {
        $start = (string) ($tenant['api_period_starts_at'] ?? '');
        if ($start === '' || strtotime($start) === false) {
            // Primera vez: arrancar ahora
            $now = date('Y-m-d H:i:s');
            Database::update('tenants', [
                'api_period_starts_at' => $now,
                'api_calls_period'     => 0,
            ], ['id' => (int) $tenant['id']]);
            return [0, $now];
        }

        // Vencio? 30 dias rolling
        if (time() - strtotime($start) > 30 * 86400) {
            $now = date('Y-m-d H:i:s');
            Database::update('tenants', [
                'api_period_starts_at'  => $now,
                'api_calls_period'      => 0,
                'api_quota_alerted_at'  => null,
            ], ['id' => (int) $tenant['id']]);
            return [0, $now];
        }

        return [(int) ($tenant['api_calls_period'] ?? 0), $start];
    }

    /**
     * Dispara una alerta de cuota usando AlertService. Anti-spam: el cooldown
     * de la regla evita que se mande de nuevo dentro de la ventana.
     */
    private static function fireQuotaAlert(int $tenantId, string $slug, int $pct, int $used, int $quota, array $tenant): void
    {
        try {
            $periodEnd = date('Y-m-d', strtotime((string) ($tenant['api_period_starts_at'] ?? 'now')) + 30 * 86400);
            AlertService::fire($slug, $tenantId, [
                'pct'       => $pct,
                'used'      => $used,
                'quota'     => $quota,
                'plan'      => (string) ($tenant['plan_slug'] ?? '—'),
                'resets_at' => $periodEnd,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Quota alert fire fallo', ['msg' => $e->getMessage()]);
        }
    }

    private static function bumpCounter(int $tenantId, string $periodStart, int $newUsed): void
    {
        Database::run(
            "UPDATE `tenants`
             SET `api_calls_period` = :u, `api_period_starts_at` = :s
             WHERE `id` = :i",
            ['u' => $newUsed, 's' => $periodStart, 'i' => $tenantId]
        );
    }

    private static function empty(int $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'plan_name' => null, 'plan_slug' => null,
            'quota' => 1000, 'used' => 0, 'remaining' => 1000, 'used_pct' => 0,
            'unlimited' => false, 'no_access' => false,
            'period_starts_at' => null,
            'period_resets_at' => date('Y-m-d H:i:s', time() + 30 * 86400),
            'is_overridden' => false,
        ];
    }
}
