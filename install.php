<?php
/**
 * Instalador via CLI: ejecuta migracion y seeders.
 * Uso:
 *   php install.php                  (usa .env)
 *   php install.php --env=production (usa .env.production)
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
    fwrite(STDERR, "[ERROR] No existe el archivo de entorno: $envFile\n");
    exit(1);
}

App\Core\Env::load($envFile);
App\Core\Config::setPath(__DIR__ . '/config');

echo "\n=== Kyros Pulse - Instalador ===\n";
echo "Entorno: " . basename($envFile) . "\n\n";

$cfg    = App\Core\Config::get('database.connections.mysql');
$dbName = $cfg['database'];

try {
    // 1) Intentar conectar directamente a la BD (caso shared hosting: ya existe).
    try {
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$dbName};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "[+] Conectado a MySQL ({$cfg['host']}) BD `$dbName`.\n";
    } catch (PDOException $e) {
        // 2) Fallback: conectar sin BD y crearla (entorno local con privilegios).
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "[+] Conectado a MySQL ({$cfg['host']}). Creando BD `$dbName`...\n";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");
    }

    $files = [
        __DIR__ . '/database/migrations/001_initial_schema.sql',
        __DIR__ . '/database/seeders/001_basic_data.sql',
    ];

    foreach ($files as $file) {
        if (!is_file($file)) {
            echo "[!] No se encontro: $file\n";
            continue;
        }
        $sql = file_get_contents($file);
        echo "[~] Ejecutando " . basename($file) . " ...\n";
        $pdo->exec($sql);
        echo "[+] " . basename($file) . " OK.\n";
    }

    echo "\n=== Instalacion completada ===\n";
    echo "Super Admin: admin@kyrosrd.com / admin12345\n";
    echo "Demo Owner:  owner@kyrosrd.com / demo12345\n\n";

} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
