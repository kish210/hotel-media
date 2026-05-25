<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    // فیلدهایی که هرگز نباید به DB برن
    private const FORBIDDEN_KEYS = ['_token', '_method', '_action', 'edit_mode', 'password_confirmation', 'submit', 'section', 'content_type', '_layout_id'];

    private function __construct()
    {
        $config = require CONFIG_PATH . '/database.php';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'], $config['port'], $config['database'], $config['charset']);

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            error_log("DB connection failed: " . $e->getMessage());
            throw new \RuntimeException("Database connection failed", 500, $e);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function pdo(): PDO { return $this->pdo; }

    /** Clean data — remove forbidden keys and non-string keys */
    private function clean(array $data): array
    {
        $clean = [];
        foreach ($data as $k => $v) {
            if (!is_string($k)) continue;
            if (in_array($k, self::FORBIDDEN_KEYS, true)) continue;
            $clean[$k] = $v;
        }
        return $clean;
    }

    // ── Query helpers ────────────────────────────────────────

    public function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("[DB ERROR] " . $e->getMessage() . " | SQL: " . substr($sql, 0, 200));
            throw $e;
        }
    }

    public function rows(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function row(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }

    public function insert(string $table, array $data): int|string
    {
        $data = $this->clean($data);
        if (empty($data)) throw new \InvalidArgumentException("No data to insert into $table");

        $cols   = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $this->query("INSERT INTO `$table` ($cols) VALUES ($placeholders)", array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        $data = $this->clean($data);
        if (empty($data)) return 0;

        $set   = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $conds = implode(' AND ', array_map(fn($c) => "`$c` = ?", array_keys($where)));
        $params = array_merge(array_values($data), array_values($where));

        return $this->query("UPDATE `$table` SET $set WHERE $conds", $params)->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $conds  = implode(' AND ', array_map(fn($c) => "`$c` = ?", array_keys($where)));
        return $this->query("DELETE FROM `$table` WHERE $conds", array_values($where))->rowCount();
    }

    public function exists(string $table, array $where): bool
    {
        $conds = implode(' AND ', array_map(fn($c) => "`$c` = ?", array_keys($where)));
        return (bool)$this->value("SELECT 1 FROM `$table` WHERE $conds LIMIT 1", array_values($where));
    }

    public function count(string $table, array $where = []): int
    {
        if (empty($where)) return (int)$this->value("SELECT COUNT(*) FROM `$table`");
        $conds = implode(' AND ', array_map(fn($c) => "`$c` = ?", array_keys($where)));
        return (int)$this->value("SELECT COUNT(*) FROM `$table` WHERE $conds", array_values($where));
    }

    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool           { return $this->pdo->commit(); }
    public function rollback(): bool         { return $this->pdo->rollBack(); }

    public function paginate(string $sql, array $params = [], int $page = 1, int $perPage = 20): array
    {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        // Wrap query in subquery for safe COUNT — handles subqueries and complex SELECTs
        $countSql = "SELECT COUNT(*) FROM ({$sql}) AS _paginate_count";
        $total    = (int)($this->value($countSql, $params) ?? 0);

        // Remove existing ORDER BY / LIMIT then add pagination
        $pageSql  = preg_replace('/ORDER\s+BY\s+[^)]+$/i', '', $sql);
        $pageSql  = trim(preg_replace('/LIMIT\s+\d+(\s+OFFSET\s+\d+)?/i', '', $pageSql));
        // Restore ORDER BY from original if present
        if (preg_match('/ORDER\s+BY\s+(.+?)(?:LIMIT|$)/is', $sql, $m)) {
            $pageSql .= ' ORDER BY ' . trim($m[1]);
        }
        $pageSql .= " LIMIT {$perPage} OFFSET {$offset}";

        $data = $this->rows($pageSql, $params);

        return [
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, (int)ceil($total / $perPage)),
            'from'         => $total ? $offset + 1 : 0,
            'to'           => min($total, $offset + $perPage),
        ];
    }

}