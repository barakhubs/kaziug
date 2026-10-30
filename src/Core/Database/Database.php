<?php

declare(strict_types=1);

namespace App\Core\Database;

use Closure;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper for MySQL/MariaDB: lazy connection, prepared statements only, typed parameter
 * binding (PHP bool -> 0/1, null -> NULL), and nested-safe transactions.
 */
final class Database
{
    private ?PDO $pdo = null;

    private int $transactionDepth = 0;

    /** @param array{host: string, port: int, database: string, username: string, password: string, ssl_ca: string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
            );
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                // UPDATE reports matched rows, not only changed ones, so "did it apply" checks stay reliable.
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ];
            if ($this->config['ssl_ca'] !== '') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $this->config['ssl_ca'];
            }
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
            // The collation matches the tables, so string literals and CASTs never clash with columns.
            $this->pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            // Strict mode turns silent truncation into errors; UTC keeps NOW() in step with DbTime.
            $this->pdo->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }

        return $this->pdo;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @param array<string|int, mixed> $params */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return int affected (matched) rows
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Runs an INSERT and returns the new AUTO_INCREMENT id.
     * @param array<string|int, mixed> $params
     */
    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);

        return (int) $this->pdo()->lastInsertId();
    }

    /** Runs one raw SQL statement with no parameters (migrations and test setup only). */
    public function unprepared(string $sql): void
    {
        $this->pdo()->exec($sql);
    }

    /**
     * @template T
     * @param Closure(Database): T $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed
    {
        $pdo = $this->pdo();
        $savepoint = 'sp' . $this->transactionDepth;

        if ($this->transactionDepth === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec("SAVEPOINT {$savepoint}");
        }
        $this->transactionDepth++;

        try {
            $result = $callback($this);
            $this->transactionDepth--;
            if ($this->transactionDepth === 0) {
                $pdo->commit();
            } else {
                $pdo->exec("RELEASE SAVEPOINT {$savepoint}");
            }

            return $result;
        } catch (Throwable $e) {
            $this->transactionDepth--;
            if ($this->transactionDepth === 0) {
                $pdo->rollBack();
            } else {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
            throw $e;
        }
    }

    /** @param array<string|int, mixed> $params */
    private function run(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $value = (int) $value;
            }
            $statement->bindValue(is_int($key) ? $key + 1 : $key, $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        }
        $statement->execute();

        return $statement;
    }
}
