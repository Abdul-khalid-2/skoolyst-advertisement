<?php

namespace Core;

use PDO;
use PDOException;

/**
 * Database
 *
 * One PDO connection wrapper, reused by every module's repository.
 * Reads connection details from environment variables (see
 * config/database.php, Section 9, and .env.example, Section 11).
 *
 * Query-helper methods (query(), fetchOne(), ...) are added next —
 * this stub only establishes the connection.
 */
class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $config = require __DIR__ . '/../config/database.php';

            $host = $config['host'];
            $port = $config['port'];
            $name = $config['name'];
            $user = $config['user'];
            $pass = $config['pass'];
            $charset = $config['charset'];

            $dsn = "{$config['driver']}:host={$host};port={$port};dbname={$name};charset={$charset}";

            try {
                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                throw new PDOException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }

    /**
     * Run a prepared statement and return it, ready to be fetched from.
     * Always parameterized — no repository should ever build raw SQL
     * with interpolated values (see Section 6 SQL-injection rule).
     *
     * @param string $sql
     * @param array<string, mixed> $params
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Run a query and return only the first row (or null if none).
     * Convenience wrapper over query() for the common "fetch one record"
     * case (e.g. find a user by id, find an app by API key hash).
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }
}
