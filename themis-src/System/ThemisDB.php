<?php
declare(strict_types=1);

namespace Themis\System;

use Themis\System\DatabaseConnector;
use PDO;
use PDOException;
use Exception;

/**
 * Class ThemisDB
 *
 * A fluent, safe database query builder for Themis.
 * Replaces the legacy DatabaseOperator with a more developer-friendly interface
 * while maintaining strict security controls (whitelisting, non-destructive defaults).
 */
class ThemisDBException extends Exception
{
}

class ThemisDB
{
    private array $pdoInstances = [];
    private ?string $currentConnectionName = null;
    private ?PDO $pdo = null;

    // Query State
    private ?string $table = null;
    private array $selectColumns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private ?string $lockMode = null;

    private const TABLES = [
        "players",
        "users",
        "player_characters",
        "rp_tool_character_repository",
        "player_tags",
        "launch_tokens",
        "sessions"
    ];

    private const COLUMN_WHITELIST = "*ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890._";
    private const VALID_LOCK_MODES = ['FOR UPDATE'];

    /**
     * Reset query state to defaults.
     */
    private function reset(): void
    {
        $this->table = null;
        $this->selectColumns = ['*'];
        $this->wheres = [];
        $this->bindings = [];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        $this->lockMode = null;
    }


    public function __construct()
    {
        // No-op for DI compatibility
    }

    /**
     * Connect to the database.
     */
    public function connect(?string $databaseName = null, array $options = []): self
    {
        $name = $databaseName ?? 'default';
        if (isset($this->pdoInstances[$name])) {
            $this->useConnection($name);
            return $this;
        }

        try {
            $connector = new DatabaseConnector();
            $pdo = $connector->connect($databaseName, $options);
        } catch (Exception $e) {
            throw new ThemisDBException("Database connection failed: " . $e->getMessage());
        }

        $this->pdoInstances[$name] = $pdo;
        $this->useConnection($name);

        return $this;
    }

    public function useConnection(string $name): self
    {
        if (!isset($this->pdoInstances[$name])) {
            throw new ThemisDBException("Connection '$name' not established.");
        }
        if ($this->pdo && $this->pdo->inTransaction()) {
            throw new ThemisDBException("Cannot switch connections while in a transaction.");
        }
        $this->pdo = $this->pdoInstances[$name];
        $this->currentConnectionName = $name;
        return $this;
    }

    public function getPdo(): PDO
    {
        if (!$this->pdo) {
            throw new ThemisDBException("No active database connection.");
        }
        return $this->pdo;
    }

    // -- Fluent Query Builder Methods --

    /**
     * Start a query against a specific table.
     */
    public function table(string $table): self
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new ThemisDBException("Invalid table: $table");
        }
        $this->reset();
        $this->table = $table;
        return $this;
    }

    /**
     * Validate column name(s) against whitelist and multi-byte characters.
     */
    private function validateColumns(string|array $columns): string|array
    {
        $items = is_array($columns) ? $columns : [$columns];
        
        foreach ($items as $column) {
            if (mb_strlen($column) !== strlen($column)) {
                throw new ThemisDBException("Multi-byte characters not allowed in column name: $column");
            }
            if (strspn($column, self::COLUMN_WHITELIST) !== strlen($column)) {
                throw new ThemisDBException("Invalid column name: $column");
            }
        }
        
        return $columns;
    }

    /**
     * Select specific columns.
     *
     * @param array<string> $columns
     */
    public function select(array $columns): self
    {
        $this->selectColumns = $this->validateColumns($columns);
        return $this;
    }

    /**
     * Add a WHERE clause.
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     */
    public function where(string $column, string $operator, mixed $value): self
    {
        $column = $this->validateColumns($column);
        
        // Validate operator
        $validOperators = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IS', 'IS NOT', 'IN', 'NOT IN'];
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, $validOperators)) {
            throw new ThemisDBException("Invalid operator: $operator");
        }
        
        // Validate IN/NOT IN operator value type
        if (in_array($operator, ['IN', 'NOT IN']) && !is_array($value)) {
            throw new ThemisDBException("Value for {$operator} operator must be an array");
        }

        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
        return $this;
    }

    /**
     * Add an ORDER BY clause.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $column = $this->validateColumns($column);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = compact('column', 'direction');
        return $this;
    }

    /**
     * Set LIMIT and OFFSET.
     */
    public function limit(int $limit, ?int $offset = null): self
    {
        if ($limit < 1) {
            throw new ThemisDBException("Limit must be at least 1");
        }
        $this->limit = $limit;
        if ($offset !== null) {
            if ($offset < 0) {
                throw new ThemisDBException("Offset cannot be negative");
            }
            $this->offset = $offset;
        }
        return $this;
    }

    /**
     * Lock the rows (e.g. FOR UPDATE).
     */
    public function lockForUpdate(): self
    {
        $lockMode = 'FOR UPDATE';
        if (!in_array($lockMode, self::VALID_LOCK_MODES)) {
            throw new ThemisDBException("Invalid lock mode: $lockMode");
        }
        $this->lockMode = $lockMode;
        return $this;
    }

    // -- Executors --

    /**
     * Execute a SELECT query and return all rows.
     */
    public function get(): array
    {
        $sql = $this->compileSelect();
        try {
            return $this->run($sql, $this->bindings);
        } finally {
            $this->reset();
        }
    }

    /**
     * Execute a SELECT query and return the first row, or null.
     */
    public function first(): ?array
    {
        $originalLimit = $this->limit;
        try {
            $this->limit(1);
            $rows = $this->get();
            return $rows[0] ?? null;
        } finally {
            $this->limit = $originalLimit;
        }
    }

    /**
     * Execute an INSERT query.
     * Returns the last insert ID or true.
     */
    public function insert(array $data): int|bool
    {
        if (!$this->table) {
            throw new ThemisDBException("No table specified for insert.");
        }

        $columns = array_keys($data);
        $this->validateColumns($columns);
        $values = array_values($data);

        $quotedTable = $this->quoteIdentifier($this->table);
        $quotedColumns = array_map([$this, 'quoteIdentifier'], $columns);
        $placeholders = array_fill(0, count($values), '?');

        $sql = "INSERT INTO {$quotedTable} (" . implode(', ', $quotedColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        try {
            $this->run($sql, $values);
            $id = $this->getPdo()->lastInsertId();
            return is_numeric($id) ? (int) $id : true;
        } finally {
            $this->reset();
        }
    }

    /**
     * Execute an UPDATE query.
     * Uses the current WHERE clauses.
     */
    public function update(array $data): int
    {
        if (!$this->table) {
            throw new ThemisDBException("No table specified for update.");
        }
        if (empty($this->wheres)) {
            throw new ThemisDBException("UPDATE without WHERE clause is not allowed for safety.");
        }

        $columns = array_keys($data);
        $this->validateColumns($columns);
        
        $setParts = [];
        $bindings = [];

        foreach ($data as $col => $val) {
            $setParts[] = $this->quoteIdentifier($col) . " = ?";
            $bindings[] = $val;
        }

        $quotedTable = $this->quoteIdentifier($this->table);
        $sql = "UPDATE {$quotedTable} SET " . implode(', ', $setParts);

        // Append WHEREs
        $whereSql = $this->compileWheres($bindings); // $bindings is passed by ref and appended to
        $sql .= $whereSql;

        try {
            return $this->run($sql, $bindings);
        } finally {
            $this->reset();
        }
    }

    // -- Transactions --

    public function beginTransaction(): void
    {
        $pdo = $this->getPdo();
        if ($pdo->inTransaction()) {
            throw new ThemisDBException("Transaction already active");
        }
        $pdo->beginTransaction();
    }

    public function commit(): void
    {
        $pdo = $this->getPdo();
        if (!$pdo->inTransaction()) {
            throw new ThemisDBException("No active transaction to commit");
        }
        $pdo->commit();
    }

    public function rollback(): void
    {
        $pdo = $this->getPdo();
        if (!$pdo->inTransaction()) {
            throw new ThemisDBException("No active transaction to rollback");
        }
        $pdo->rollBack();
    }

    // -- SQL Compilation --

    private function compileSelect(): string
    {
        if (!$this->table) {
            throw new ThemisDBException("No table selected.");
        }

        $cols = $this->selectColumns;
        if ($cols === ['*']) {
            $colString = '*';
        } else {
            $colString = implode(', ', array_map([$this, 'quoteIdentifier'], $cols));
        }

        $sql = "SELECT {$colString} FROM " . $this->quoteIdentifier($this->table);

        $bindings = [];
        $sql .= $this->compileWheres($bindings);
        $this->bindings = $bindings;

        if (!empty($this->orders)) {
            $orderParts = [];
            foreach ($this->orders as $order) {
                $orderParts[] = $this->quoteIdentifier($order['column']) . ' ' . $order['direction'];
            }
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT " . (int) $this->limit;
            if ($this->offset !== null) {
                $sql .= " OFFSET " . (int) $this->offset;
            }
        }

        if ($this->lockMode) {
            $sql .= " " . $this->lockMode;
        }

        return $sql;
    }

    private function compileWheres(array &$bindings): string
    {
        if (empty($this->wheres)) {
            return "";
        }

        $clauses = [];
        foreach ($this->wheres as $where) {
            $col = $this->quoteIdentifier($where['column']);

            if (in_array($where['operator'], ['IN', 'NOT IN'])) {
                if (empty($where['value'])) {
                    $clauses[] = $where['operator'] === 'IN' ? "0 = 1" : "1 = 1";
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($where['value']), '?'));
                $clauses[] = "{$col} {$where['operator']} ({$placeholders})";
                foreach ($where['value'] as $v) {
                    $bindings[] = $v;
                }
            } elseif (in_array($where['operator'], ['IS', 'IS NOT'], true)) {
                $val = $where['value'];
                if ($val === null) {
                    $clauses[] = "{$col} {$where['operator']} NULL";
                } else {
                    $clauses[] = "{$col} {$where['operator']} ?";
                    $bindings[] = $val;
                }
            } else {
                $clauses[] = "{$col} {$where['operator']} ?";
                $bindings[] = $where['value'];
            }
        }

        return " WHERE " . implode(' AND ', $clauses);
    }

    // -- Query Execution --

    private function run(string $sql, array $bindings): array|int
    {
        try {
            $stmt = $this->getPdo()->prepare($sql);
            $stmt->execute($bindings);

            if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\s/i', $sql)) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new ThemisDBException("Query Error: " . $e->getMessage());
        }
    }

    // -- Utilities --

    private function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }
        return "`" . str_replace("`", "``", $identifier) . "`";
    }

    // -- Advanced Methods --

    /**
     * Manual query escape hatch.
     * Only allows single SELECT, INSERT, or UPDATE statements.
     */
    public function manualQuery(string $query, array $params = []): array|int
    {
        // Prevent multiple statements by checking for semicolons (except at the very end)
        $trimmedQuery = rtrim($query, " \t\n\r\0\x0B;");
        if (strpos($trimmedQuery, ';') !== false) {
            throw new ThemisDBException("Multiple statements are not allowed in manual queries.");
        }
        
        // Explicitly forbid operations outside our scope
        $forbiddenOps = ['DELETE', 'DROP', 'TRUNCATE', 'REPLACE', 'ALTER', 'CREATE', 'SHOW', 'DESCRIBE', 'EXPLAIN'];
        if (preg_match('/^\s*(?:' . implode('|', $forbiddenOps) . ')\s/i', $query)) {
            throw new ThemisDBException("Only SELECT, INSERT, and UPDATE operations are allowed.");
        }
        
        return $this->run($query, $params);
    }
}
