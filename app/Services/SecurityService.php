<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Orquestador de la capa de seguridad:
 *   - 2FA enable/disable + recovery codes
 *   - Login attempt tracking + lockout temporal (sliding window)
 *   - Session tracking (active sessions + revocation)
 *   - Security events log (login_ok, login_fail, 2fa_*, password_change, etc.)
 *
 * Llamado desde AuthController, ApiKeyController, 2FA controllers.
 */
final class SecurityService
{
    /** Lockout: N intentos fallidos en una ventana -> bloquear hasta. */
    private const LOCKOUT_THRESHOLD = 5;
    private const LOCKOUT_WINDOW    = 900;   // 15 min
    private const LOCKOUT_DURATION  = 900;   // 15 min de bloqueo

    // ============================================================
    // Login attempts + lockout
    // ============================================================

    public static function recordLoginAttempt(string $email, string $ip, ?int $userId, bool $success, ?string $reason = null, ?string $userAgent = null): void
    {
        try {
            Database::insert('login_attempts', [
                'email'      => mb_substr(mb_strtolower($email), 0, 160),
                'ip'         => mb_substr($ip, 0, 64),
                'user_id'    => $userId,
                'success'    => $success ? 1 : 0,
                'reason'     => $reason ? mb_substr($reason, 0, 60) : null,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('login_attempts insert fallo', ['msg' => $e->getMessage()]);
        }
    }

    /**
     * Devuelve segundos de lockout restantes (>0 si bloqueado, 0 si libre).
     * Combina email + IP: si CUALQUIERA acumulo >= threshold fallos recientes,
     * la combinacion entera se bloquea.
     */
    public static function lockoutSeconds(string $email, string $ip): int
    {
        $email = mb_strtolower($email);
        try {
            $row = Database::fetch(
                "SELECT COUNT(*) AS fails, MAX(created_at) AS last_at
                 FROM `login_attempts`
                 WHERE `success` = 0
                   AND (`email` = :e OR `ip` = :i)
                   AND `created_at` > DATE_SUB(NOW(), INTERVAL :w SECOND)",
                ['e' => $email, 'i' => $ip, 'w' => self::LOCKOUT_WINDOW]
            );
            $fails = (int) ($row['fails'] ?? 0);
            if ($fails < self::LOCKOUT_THRESHOLD) return 0;
            $lastTs = strtotime((string) ($row['last_at'] ?? '0'));
            $unlockAt = $lastTs + self::LOCKOUT_DURATION;
            $remaining = $unlockAt - time();
            return $remaining > 0 ? $remaining : 0;
        } catch (\Throwable $e) {
            Logger::warning('lockoutSeconds fallo', ['msg' => $e->getMessage()]);
            return 0;
        }
    }

    /** Limpia el historial reciente de un email tras login exitoso. */
    public static function clearLockout(string $email): void
    {
        try {
            Database::run(
                "DELETE FROM `login_attempts`
                 WHERE `email` = :e AND `success` = 0
                   AND `created_at` > DATE_SUB(NOW(), INTERVAL :w SECOND)",
                ['e' => mb_strtolower($email), 'w' => self::LOCKOUT_WINDOW]
            );
        } catch (\Throwable) {}
    }

    // ============================================================
    // Security events log
    // ============================================================

    public static function logEvent(string $event, ?int $userId = null, ?int $tenantId = null, string $severity = 'info', ?array $metadata = null, ?string $ip = null, ?string $ua = null): void
    {
        try {
            Database::insert('security_events', [
                'tenant_id'  => $tenantId,
                'user_id'    => $userId,
                'event'      => mb_substr($event, 0, 60),
                'severity'   => in_array($severity, ['info','warning','critical'], true) ? $severity : 'info',
                'ip'         => $ip ? mb_substr($ip, 0, 64) : ($_SERVER['REMOTE_ADDR'] ?? null),
                'user_agent' => $ua ? mb_substr($ua, 0, 255) : (isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null),
                'metadata'   => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            ]);

            // Disparar alerta si es critical (con cooldown anti-spam)
            if ($severity === 'critical' && $tenantId !== null) {
                try {
                    AlertService::fire('security.critical', $tenantId, [
                        'event'    => $event,
                        'ip'       => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '?'),
                        'metadata' => $metadata ?? [],
                    ]);
                } catch (\Throwable) {}
            }
        } catch (\Throwable $e) {
            Logger::warning('security_events insert fallo', ['msg' => $e->getMessage()]);
        }
    }

    public static function recentEventsForTenant(int $tenantId, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT * FROM `security_events`
             WHERE `tenant_id` = :t
             ORDER BY `id` DESC
             LIMIT $limit",
            ['t' => $tenantId]
        );
    }

    public static function recentEventsForUser(int $userId, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT * FROM `security_events`
             WHERE `user_id` = :u
             ORDER BY `id` DESC
             LIMIT $limit",
            ['u' => $userId]
        );
    }

    // ============================================================
    // Active sessions
    // ============================================================

    public static function recordSession(int $userId, ?int $tenantId, string $phpSessionId, string $ip, string $userAgent): void
    {
        $hash = hash('sha256', $phpSessionId);
        try {
            $existing = Database::fetch("SELECT id FROM `user_sessions` WHERE `session_id` = :s LIMIT 1", ['s' => $hash]);
            if ($existing) {
                Database::run(
                    "UPDATE `user_sessions` SET `last_seen_at` = NOW(), `ip` = :ip, `user_agent` = :ua, `revoked_at` = NULL WHERE `id` = :i",
                    ['i' => (int) $existing['id'], 'ip' => mb_substr($ip, 0, 64), 'ua' => mb_substr($userAgent, 0, 255)]
                );
                return;
            }
            Database::insert('user_sessions', [
                'user_id'      => $userId,
                'tenant_id'    => $tenantId,
                'session_id'   => $hash,
                'ip'           => mb_substr($ip, 0, 64),
                'user_agent'   => mb_substr($userAgent, 0, 255),
                'last_seen_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('user_sessions upsert fallo', ['msg' => $e->getMessage()]);
        }
    }

    public static function touchSession(string $phpSessionId): void
    {
        $hash = hash('sha256', $phpSessionId);
        try {
            Database::run(
                "UPDATE `user_sessions` SET `last_seen_at` = NOW() WHERE `session_id` = :s AND `revoked_at` IS NULL",
                ['s' => $hash]
            );
        } catch (\Throwable) {}
    }

    public static function isSessionRevoked(string $phpSessionId): bool
    {
        $hash = hash('sha256', $phpSessionId);
        try {
            $row = Database::fetch(
                "SELECT `revoked_at` FROM `user_sessions` WHERE `session_id` = :s LIMIT 1",
                ['s' => $hash]
            );
            if (!$row) return false;
            return !empty($row['revoked_at']);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function activeSessionsForUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM `user_sessions`
             WHERE `user_id` = :u AND `revoked_at` IS NULL
             ORDER BY `last_seen_at` DESC",
            ['u' => $userId]
        );
    }

    public static function revokeSession(int $userId, int $sessionRowId): int
    {
        return Database::run(
            "UPDATE `user_sessions` SET `revoked_at` = NOW()
             WHERE `id` = :i AND `user_id` = :u AND `revoked_at` IS NULL",
            ['i' => $sessionRowId, 'u' => $userId]
        )->rowCount();
    }

    public static function revokeAllOtherSessions(int $userId, string $currentPhpSessionId): int
    {
        $hash = hash('sha256', $currentPhpSessionId);
        return Database::run(
            "UPDATE `user_sessions` SET `revoked_at` = NOW()
             WHERE `user_id` = :u AND `session_id` != :s AND `revoked_at` IS NULL",
            ['u' => $userId, 's' => $hash]
        )->rowCount();
    }

    public static function markSessionRevokedByHash(string $phpSessionId): void
    {
        $hash = hash('sha256', $phpSessionId);
        try {
            Database::run(
                "UPDATE `user_sessions` SET `revoked_at` = NOW() WHERE `session_id` = :s AND `revoked_at` IS NULL",
                ['s' => $hash]
            );
        } catch (\Throwable) {}
    }

    // ============================================================
    // Recovery codes (2FA)
    // ============================================================

    /** Genera y persiste 10 recovery codes nuevos. Devuelve los plain text 1 sola vez. */
    public static function generateRecoveryCodes(int $userId, int $count = 10): array
    {
        // Borrar viejos
        Database::run("DELETE FROM `user_recovery_codes` WHERE `user_id` = :u", ['u' => $userId]);
        $plains = [];
        for ($i = 0; $i < $count; $i++) {
            $plain = self::randomCode();
            $plains[] = $plain;
            Database::insert('user_recovery_codes', [
                'user_id'   => $userId,
                'code_hash' => hash('sha256', $plain),
            ]);
        }
        return $plains;
    }

    public static function recoveryCodesUnusedCount(int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM `user_recovery_codes` WHERE `user_id` = :u AND `used_at` IS NULL",
            ['u' => $userId]
        );
    }

    /** Consume un recovery code si es valido y no usado. Devuelve true si matched. */
    public static function consumeRecoveryCode(int $userId, string $code): bool
    {
        $code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $code) ?? '');
        if ($code === '') return false;
        $hash = hash('sha256', $code);
        $row = Database::fetch(
            "SELECT * FROM `user_recovery_codes` WHERE `user_id` = :u AND `code_hash` = :h AND `used_at` IS NULL LIMIT 1",
            ['u' => $userId, 'h' => $hash]
        );
        if (!$row) return false;
        Database::run(
            "UPDATE `user_recovery_codes` SET `used_at` = NOW() WHERE `id` = :i",
            ['i' => (int) $row['id']]
        );
        return true;
    }

    /** Formato XXXX-XXXX (8 chars alfanumericos en bloques de 4). */
    private static function randomCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin 0/O/1/I para evitar confusion
        $aLen = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            if ($i === 4) $out .= '-';
            $out .= $alphabet[random_int(0, $aLen)];
        }
        return $out;
    }

    // ============================================================
    // 2FA enable / disable
    // ============================================================

    public static function get2faRow(int $userId): ?array
    {
        $row = Database::fetch("SELECT * FROM `user_2fa` WHERE `user_id` = :u LIMIT 1", ['u' => $userId]);
        return $row ?: null;
    }

    public static function set2faSecret(int $userId, string $secret): void
    {
        $row = self::get2faRow($userId);
        if ($row) {
            Database::update('user_2fa', [
                'secret'   => $secret,
                'enabled'  => 0,
                'enabled_at' => null,
                'last_used_code' => null,
                'last_used_at'   => null,
            ], ['id' => (int) $row['id']]);
        } else {
            Database::insert('user_2fa', [
                'user_id' => $userId,
                'secret'  => $secret,
                'enabled' => 0,
            ]);
        }
    }

    public static function enable2fa(int $userId): void
    {
        Database::run(
            "UPDATE `user_2fa` SET `enabled` = 1, `enabled_at` = NOW() WHERE `user_id` = :u",
            ['u' => $userId]
        );
    }

    public static function disable2fa(int $userId): void
    {
        Database::run("DELETE FROM `user_2fa` WHERE `user_id` = :u", ['u' => $userId]);
        Database::run("DELETE FROM `user_recovery_codes` WHERE `user_id` = :u", ['u' => $userId]);
    }

    public static function user2faEnabled(int $userId): bool
    {
        $row = self::get2faRow($userId);
        return $row !== null && !empty($row['enabled']);
    }

    /** Marca codigo como usado (previene reuso dentro del mismo step de 30s). */
    public static function record2faCodeUsed(int $userId, string $code): void
    {
        Database::run(
            "UPDATE `user_2fa` SET `last_used_code` = :c, `last_used_at` = NOW() WHERE `user_id` = :u",
            ['c' => mb_substr($code, 0, 8), 'u' => $userId]
        );
    }
}
