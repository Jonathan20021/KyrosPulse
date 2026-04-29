<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Conexion PDO singleton para MySQL con prepared statements.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = Config::get('database.connections.mysql');
        if (!is_array($cfg)) {
            throw new \RuntimeException('Configuracion de base de datos invalida.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'] ?? '3306',
            $cfg['database'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO(
                $dsn,
                (string) $cfg['username'],
                (string) $cfg['password'],
                $cfg['options'] ?? []
            );
            self::$pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            Logger::error('DB connection failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('No se pudo conectar a la base de datos.');
        }

        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = [], int $col = 0): mixed
    {
        $value = self::run($sql, $params)->fetchColumn($col);
        return $value === false ? null : $value;
    }

    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $columnList = '`' . implode('`,`', $columns) . '`';
        $placeholders = ':' . implode(', :', $columns);

        $sql = "INSERT INTO `$table` ($columnList) VALUES ($placeholders)";
        self::run($sql, $data);
        return (int) self::connection()->lastInsertId();
    }

    public static function update(string $table, array $data, array $where): int
    {
        $set = [];
        foreach ($data as $col => $_) {
            $set[] = "`$col` = :$col";
        }
        $whereParts = [];
        $params = $data;
        foreach ($where as $col => $val) {
            $whereParts[] = "`$col` = :w_$col";
            $params["w_$col"] = $val;
        }

        $sql = "UPDATE `$table` SET " . implode(', ', $set) . ' WHERE ' . implode(' AND ', $whereParts);
        return self::run($sql, $params)->rowCount();
    }

    public static function delete(string $table, array $where): int
    {
        $whereParts = [];
        foreach ($where as $col => $_) {
            $whereParts[] = "`$col` = :$col";
        }
        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $whereParts);
        return self::run($sql, $where)->rowCount();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
