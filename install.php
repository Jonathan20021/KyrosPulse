<?php
/**
 * Instalador via CLI: ejecuta migracion y seeders.
 * Uso:  php install.php
 */
declare(strict_types=1);

require __DIR__ . '/app/Core/Env.php';
require __DIR__ . '/app/Core/Config.php';
require __DIR__ . '/app/Helpers/helpers.php';

App\Core\Env::load(__DIR__ . '/.env');
App\Core\Config::setPath(__DIR__ . '/config');

echo "\n=== Kyros Pulse - Instalador ===\n\n";

$cfg = App\Core\Config::get('database.connections.mysql');
$dsn = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "[+] Conectado a MySQL.\n";

    $dbName = $cfg['database'];
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");
    echo "[+] Base de datos `$dbName` lista.\n";

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
