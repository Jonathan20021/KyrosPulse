<?php
/**
 * Script de verificacion post-instalacion.
 * Uso: php verify_db.php --env=production
 */
declare(strict_types=1);

require __DIR__ . '/app/Core/Env.php';
require __DIR__ . '/app/Core/Config.php';
require __DIR__ . '/app/Helpers/helpers.php';

$envSuffix = '';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--env=')) {
        $envSuffix = '.' . substr($arg, 6);
    }
}
$envFile = __DIR__ . '/.env' . $envSuffix;
if (!is_file($envFile)) {
    fwrite(STDERR, "[ERROR] No existe: $envFile\n");
    exit(1);
}

App\Core\Env::load($envFile);
App\Core\Config::setPath(__DIR__ . '/config');

$cfg = App\Core\Config::get('database.connections.mysql');
$dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";

echo "\n=== Verificacion BD ({$cfg['host']} / {$cfg['database']}) ===\n\n";

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 1) Listado de tablas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "[+] Tablas creadas: " . count($tables) . "\n";
    foreach ($tables as $t) {
        echo "    - $t\n";
    }

    // 2) Conteo en tablas clave
    echo "\n[+] Conteos:\n";
    $checks = ['plans', 'tenants', 'users', 'roles', 'permissions'];
    foreach ($checks as $tbl) {
        if (!in_array($tbl, $tables, true)) {
            echo "    - $tbl: (no existe)\n";
            continue;
        }
        $n = (int) $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        echo "    - $tbl: $n registros\n";
    }

    // 3) Planes sembrados
    if (in_array('plans', $tables, true)) {
        echo "\n[+] Planes:\n";
        $rows = $pdo->query("SELECT id, slug, name, price_monthly, currency FROM plans ORDER BY sort_order, id")->fetchAll();
        foreach ($rows as $r) {
            printf("    #%d  %-12s  %-20s  %s %s\n", $r['id'], $r['slug'], $r['name'], $r['price_monthly'], $r['currency']);
        }
    }

    // 4) Usuarios sembrados
    if (in_array('users', $tables, true)) {
        echo "\n[+] Usuarios:\n";
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $hasRole   = in_array('role', $cols, true);
        $hasTenant = in_array('tenant_id', $cols, true);
        $select = ['id', 'email'];
        if ($hasRole)   { $select[] = 'role'; }
        if ($hasTenant) { $select[] = 'tenant_id'; }
        $rows = $pdo->query("SELECT " . implode(',', $select) . " FROM users")->fetchAll();
        foreach ($rows as $r) {
            $extra = [];
            if ($hasRole)   { $extra[] = "role={$r['role']}"; }
            if ($hasTenant) { $extra[] = "tenant_id=" . ($r['tenant_id'] ?? 'NULL'); }
            printf("    #%d  %-30s  %s\n", $r['id'], $r['email'], implode('  ', $extra));
        }
    }

    // 5) Tenants sembrados
    if (in_array('tenants', $tables, true)) {
        echo "\n[+] Tenants:\n";
        $rows = $pdo->query("SELECT id, slug, name, status, plan_id FROM tenants")->fetchAll();
        foreach ($rows as $r) {
            printf("    #%d  %-15s  %-25s  status=%s  plan=%s\n", $r['id'], $r['slug'], $r['name'], $r['status'], $r['plan_id']);
        }
    }

    echo "\n=== OK ===\n\n";

} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
