<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class User extends Model
{
    protected static string $table = 'users';
    protected static bool $tenantScoped = false;
    protected static bool $softDelete = true;

    public static function findById(int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM users WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch(
            "SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1",
            ['email' => strtolower(trim($email))]
        );
    }

    public static function emailExists(string $email): bool
    {
        $count = Database::fetchColumn(
            "SELECT COUNT(*) FROM users WHERE email = :email AND deleted_at IS NULL",
            ['email' => strtolower(trim($email))]
        );
        return ((int) $count) > 0;
    }

    public static function createUser(array $data): int
    {
        $data['email']     = strtolower(trim($data['email']));
        $data['password']  = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        $data['uuid']      = $data['uuid'] ?? uuid4();
        return Database::insert('users', $data);
    }

    public static function updatePassword(int $userId, string $newPassword): void
    {
        Database::update('users', [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
        ], ['id' => $userId]);
    }

    public static function touchLogin(int $userId): void
    {
        Database::update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ], ['id' => $userId]);
    }

    public static function markEmailVerified(int $userId): void
    {
        Database::update('users', [
            'email_verified_at' => date('Y-m-d H:i:s'),
        ], ['id' => $userId]);
    }

    public static function assignRole(int $userId, int $roleId, ?int $tenantId = null): void
    {
        Database::run(
            "INSERT IGNORE INTO user_roles (user_id, role_id, tenant_id) VALUES (:u, :r, :t)",
            ['u' => $userId, 'r' => $roleId, 't' => $tenantId]
        );
    }

    public static function rolesOf(int $userId): array
    {
        return Database::fetchAll(
            "SELECT r.* FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :u",
            ['u' => $userId]
        );
    }

    public static function hasRole(int $userId, string $slug): bool
    {
        $count = Database::fetchColumn(
            "SELECT COUNT(*) FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :u AND r.slug = :s",
            ['u' => $userId, 's' => $slug]
        );
        return ((int) $count) > 0;
    }

    public static function listByTenant(int $tenantId): array
    {
        return Database::fetchAll(
            "SELECT * FROM users WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY first_name ASC",
            ['t' => $tenantId]
        );
    }

    public static function fullName(array $user): string
    {
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    }

    public static function initials(array $user): string
    {
        $first = mb_substr((string) ($user['first_name'] ?? ''), 0, 1);
        $last  = mb_substr((string) ($user['last_name']  ?? ''), 0, 1);
        return strtoupper($first . $last) ?: 'U';
    }
}
