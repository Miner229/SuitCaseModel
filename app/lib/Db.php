<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                self::cfg('DB_HOST', 'MYSQLHOST', '127.0.0.1'),
                self::cfg('DB_PORT', 'MYSQLPORT', '3306'),
                self::cfg('DB_NAME', 'MYSQLDATABASE', 'chemodan')
            );
            self::$pdo = new PDO($dsn, self::cfg('DB_USER', 'MYSQLUSER', ''), self::cfg('DB_PASS', 'MYSQLPASSWORD', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+05:00'");
        }
        return self::$pdo;
    }

    /** DB_* из .env, иначе переменные, которые выдаёт MySQL-сервис Railway (MYSQLHOST и т.д.). */
    private static function cfg(string $key, string $railwayKey, string $default): string
    {
        return Env::get($key) ?: Env::get($railwayKey, $default);
    }

    /** Все запросы — только через подготовленные выражения (защита от SQL-инъекций). */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function lastId(): int
    {
        return (int)self::pdo()->lastInsertId();
    }
}
