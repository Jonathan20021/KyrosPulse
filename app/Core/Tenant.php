<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Tenant as TenantModel;

/**
 * Resolucion del tenant activo en la peticion.
 */
final class Tenant
{
    private static ?array $current = null;

    public static function current(): ?array
    {
        if (self::$current !== null) {
            return self::$current;
        }
        $id = Auth::tenantId();
        if (!$id) return null;
        self::$current = TenantModel::findById($id);
        return self::$current;
    }

    public static function id(): ?int
    {
        $t = self::current();
        return $t ? (int) $t['id'] : null;
    }

    public static function setCurrent(?array $tenant): void
    {
        self::$current = $tenant;
    }
}
