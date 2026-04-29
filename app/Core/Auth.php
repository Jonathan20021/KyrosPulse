<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Sesion del usuario autenticado.
 */
final class Auth
{
    private static ?array $cachedUser = null;

    public static function attempt(string $email, string $password): ?array
    {
        $user = User::findByEmail($email);
        if (!$user || empty($user['password'])) {
            return null;
        }
        if (!$user['is_active']) {
            return null;
        }
        if (!password_verify($password, $user['password'])) {
            return null;
        }

        // Rehash si es necesario
        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
            User::updatePassword((int) $user['id'], $password);
        }

        self::login($user);
        return $user;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('tenant_id', $user['tenant_id'] ? (int) $user['tenant_id'] : null);
        Session::set('is_super_admin', (bool) $user['is_super_admin']);
        Session::set('login_at', time());

        User::touchLogin((int) $user['id']);
        self::$cachedUser = $user;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$cachedUser = null;
    }

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id ? (int) $id : null;
    }

    public static function tenantId(): ?int
    {
        $id = Session::get('tenant_id');
        return $id ? (int) $id : null;
    }

    public static function isSuperAdmin(): bool
    {
        return (bool) Session::get('is_super_admin', false);
    }

    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        $id = self::id();
        if (!$id) return null;
        self::$cachedUser = User::findById($id);
        return self::$cachedUser;
    }

    public static function hasRole(string $slug): bool
    {
        $id = self::id();
        if (!$id) return false;
        return User::hasRole($id, $slug);
    }

    public static function hasAnyRole(array $slugs): bool
    {
        foreach ($slugs as $s) {
            if (self::hasRole($s)) return true;
        }
        return false;
    }
}
