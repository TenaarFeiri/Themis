<?php 
declare(strict_types=1);
namespace Themis\System;

use Themis\System\DatabaseConnector;
use Themis\System\DataContainer;
use Themis\System\ThemisContainer;
use PDO;
use PDOException;
use Exception;

/**
 * PDO Abstraction Layer
 *
 * Provides a standardized and robust interface for managing one or more PDO
 * database connections, transactions, and common CRUD operations. Centralizes
 * error handling and enforces safe query practices for the application.
 *
 * Note: this class intentionally performs strict validation on identifiers and
 * user-supplied SQL fragments (for example the select list and ORDER BY)
 * to reduce risk of SQL injection and accidental destructive queries.
 *
 * @package Themis\System
 */
class DatabaseOperatorException extends Exception {}
class DatabaseOperator {
    private array $pdoInstances = [];
    private ?string $whichPdo = null;
    private ?PDO $pdo = null;
    private const TABLES = [
        "players",
        "users",
        "player_characters",
        "rp_tool_character_repository",
        "player_tags",
        "launch_tokens",
        "sessions"
    ];

    /**
     * DatabaseOperator constructor.
     *
     * Accepts variable arguments for backward compatibility with dependency-injection containers
     * and intentionally ignores them. This constructor does not establish any connections.
     *
     * @param mixed ...$_args Optional, ignored. Present for DI compatibility.
     */
    public function __construct(...$_args) {
        // Guard against errors caused by DI injection since it's nice to have
        // this go through the injector for the sake of pattern, but we need
        // neither the ThemisContainer nor DataContainer in here.
        // So, empty $_args, and if we ever need to get back to add more stuff,
        // worry about it then.
        unset($_args);
    }

    /**
     * Establishes a PDO connection to the specified database.
     *
     * @param string|null $databaseName Name of the database to connect to, or null for default.
     * @param array $options Optional PDO connection options.
     * @throws DatabaseOperatorException If connection fails or PDO is not established.
     */
    public function connectToDatabase(?string $databaseName = null, array $options = []): void {
        if($databaseName === null) {
            $instanceName = "default";
        } else {
            $instanceName = $databaseName;
        }
        if ($this->hasConnection($instanceName)) {
            return; // Do nothing, connection already exists, it's fine.
        }
        $dbConnector = new DatabaseConnector();
        $pdo = $dbConnector->connect($databaseName, $options);
        if (!$pdo) {
            throw new DatabaseOperatorException("PDO not established (probably invalid DB name).");
        }
        $this->pdoInstances[$instanceName] = $pdo;
        if (count($this->pdoInstances) === 1) {
            $this->whichPdo = $instanceName;
            $this->pdo = $this->pdoInstances[$this->whichPdo];
        }
    }

    /**
     * Switches the active PDO connection to the specified connection name.
     *
     * @param string $connectionName Name of the connection to switch to.
     * @throws DatabaseOperatorException If a transaction is active or the connection does not exist.
     */
    public function useConnection(string $connectionName): void {
        if ($this->pdo->inTransaction()) {
            throw new DatabaseOperatorException("Cannot change connection while in transaction.");
        }
        $this->whichPdo = $connectionName;
        $this->pdo = $this->pdoInstances[$connectionName];
    }

    /**
     * Checks if the specified connection name is the current active connection.
     *
     * @param string $connectionName Connection name to check.
     * @return bool True if current, false otherwise.
     */
    public function isCurrentConnection(string $connectionName): bool {
        return $this->whichPdo === $connectionName;
    }

    /**
     * Determines if a PDO connection exists for the given connection name.
     *
     * @param string $connectionName Connection name to check.
     * @return bool True if connection exists, false otherwise.
     */
    public function hasConnection(string $connectionName): bool {
        return isset($this->pdoInstances[$connectionName]);
    }

    /**
     * Retrieves the current active PDO instance.
     *
     * @return PDO The active PDO instance.
     * @throws DatabaseOperatorException If PDO is not established.
     */
    public function getPdo(): PDO {
        if (!$this->pdo) {
            throw new DatabaseOperatorException("PDO not established.");
        }
        return $this->pdo;
    }

    /**
     * Executes a manual SQL query with parameters, forbidding destructive queries.
     *
     * For SELECT-like statements this returns an array of associative rows. For
     * non-SELECT statements (UPDATE/INSERT/etc) it returns the number of affected rows.
     *
    * @param string $query The SQL query to execute. Destructive queries (DELETE, DROP, TRUNCATE, REPLACE)
    *                     are deliberately rejected by this method.
    * @param array<int,mixed> $params Positional parameters to bind to the prepared statement.
    * @return array<int,array<string,mixed>>|int Returns rows (assoc arrays) for SELECTs, or the number of affected
    *                                          rows for non-SELECT statements.
    * @throws DatabaseOperatorException If a destructive query is attempted or the statement fails to prepare/execute.
     */
    public function manualQuery(string $query, array $params = []): array|int {
        // Forbid some destructive queries
        if (preg_match(pattern: '/^(?:DELETE|DROP|TRUNCATE|REPLACE)/i', subject: $query)) {
            throw new DatabaseOperatorException("Destructive queries are not allowed.");
        }
        $pdo = $this->getPdo();
        try {
            $statement = $pdo->prepare(query: $query);
            $ok = $statement->execute(params: $params);
            if ($ok === false) {
                $err = $statement->errorInfo();
                throw new DatabaseOperatorException("Error executing query: " . ($err[2] ?? 'unknown'));
            }
            if ($statement->columnCount() > 0) {
                // A result set is available (SELECT). Return rows as assoc array.
                return $statement->fetchAll(mode: PDO::FETCH_ASSOC);
            }
            // No result set: return number of affected rows (could be 0).
            return $statement->rowCount();
        } catch (PDOException $e) {
            throw new DatabaseOperatorException("Error executing query: " . $e->getMessage());
        }
    }

    /**
     * Begins a new database transaction.
     *
    * @throws DatabaseOperatorException If already in a transaction or no PDO connection is established.
     */
    public function beginTransaction() : void
    {
        $pdo = $this->getPdo();
        if ($pdo->inTransaction()) {
            throw new DatabaseOperatorException("Already in transaction.");
        }
        $pdo->beginTransaction();
    }

    /**
     * Checks if a transaction is currently active.
     *
    * @return bool True if in transaction, false otherwise.
     */
    public function inTransaction(): bool
    {
        $pdo = $this->getPdo();
        return $pdo->inTransaction();
    }

    /**
     * Commits the current database transaction.
     *
    * @throws DatabaseOperatorException If not in a transaction or no PDO connection is established.
     */
    public function commitTransaction() : void
    {
        $pdo = $this->getPdo();
        if (!$pdo->inTransaction()) {
            throw new DatabaseOperatorException("Could not commit; not in transaction.");
        }
        $pdo->commit();
    }

    /**
     * Rolls back the current database transaction.
     *
    * @throws DatabaseOperatorException If not in a transaction or no PDO connection is established.
     */
    public function rollbackTransaction() : void
    {
        $pdo = $this->getPdo();
        if (!$pdo->inTransaction()) {
            throw new DatabaseOperatorException("Could not rollback; not in transaction.");
        }
        $pdo->rollBack();
    }

    /**
     * Safely quotes a SQL identifier (e.g., table or column name).
     *
    * This function only quotes identifiers; it does not validate table whitelists.
    * Use the public APIs in this class to enforce table restrictions.
    *
    * @param string $identifier Identifier to quote (single identifier or dotted identifier parts).
    * @return string Quoted identifier suitable for inclusion in SQL statements.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return "`" . str_replace("`", "``", $identifier) . "`";
    }


    /**
     * Selects data from a table with specified columns and conditions.
     *
     * - The `$select` array is strictly validated: '*' is allowed only alone, and
     *   every other identifier must match the pattern [A-Za-z_][A-Za-z0-9_]* with
     *   optional dotted parts (table.column).
     * - `$where` and `$equals` are positional arrays; their counts must match when
     *   provided. A null in `$equals` produces an IS NULL check. An array in
     *   `$equals` produces an IN (...) clause.
     * - ORDER BY is enabled via `$ordered` and `$orderedBy`. `$orderedBy` may be a
     *   comma-separated list of identifiers and will be quoted for safety.
     * - `$for` accepts a small whitelist of lock clauses (FOR UPDATE, FOR SHARE, etc.).
     *
     * @param array<int,string> $select List of columns to select (or ['*']).
     * @param string $from Table name to select from (must be present in the internal whitelist).
     * @param array<int,string> $where Columns to filter by (positional; may be empty for no WHERE).
     * @param array<int,mixed> $equals Values to filter by (positional; may include null or arrays for IN).
     * @param bool $ordered Whether to append an ORDER BY clause.
     * @param string|null $orderedBy Comma-separated list of columns for ordering when $ordered is true.
     * @param bool $ascending If true ORDER BY uses ASC, otherwise DESC.
     * @param string|null $for Optional lock clause ("FOR UPDATE", "SHARE", etc.).
     * @return array<int,array<string,mixed>> Selected rows as associative arrays.
     * @throws DatabaseOperatorException If identifiers are invalid, counts mismatch, table not allowed, or the FOR clause is invalid.
     */
    public function select(array $select, string $from, array $where = [], array $equals = [], bool $ordered = false, ?string $orderedBy = null, bool $ascending = false, ?string $for = null): array {
        // Normalize and validate $select:
        // - coerce to strings and trim
        // - remove empty entries
        // - '*' is allowed only as the sole element
        // - identifiers must start with letter or underscore, may include digits/underscores, and may be dotted (table.column)
        $raw = array_values($select);
        $select = [];
        foreach ($raw as $col) {
            $c = trim((string)$col);
            if ($c === '') {
                continue;
            }
            $select[] = $c;
        }
        if (empty($select)) {
            throw new DatabaseOperatorException("Select array cannot be empty.");
        }
        if (count($select) === 1 && $select[0] === '*') {
            // okay: select all
        } else {
            foreach ($select as $c) {
                if ($c === '*') {
                    throw new DatabaseOperatorException("'*' may only be used alone in select list.");
                }
                // identifiers: letter or underscore start, then letters/digits/underscore; allow dot-separated parts
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $c)) {
                    throw new DatabaseOperatorException("Invalid select column: {$c}");
                }
            }
        }
        if (!in_array($from, self::TABLES)) {
            throw new DatabaseOperatorException("Invalid table.");
        }

        $pdo = $this->getPdo();

        $table = $this->quoteIdentifier((string)$from);

        // Allow empty WHERE/equals for "select all". Otherwise counts must match.
        if ((count($where) !== 0 || count($equals) !== 0) && count($where) !== count($equals)) {
            throw new DatabaseOperatorException("Mismatched where and equals count.");
        }

        $selectString = "";
        if (in_array("*", $select, true)) {
            $selectString = "*";
        } else {
            $quotedColumns = array_map(fn($column) => $this->quoteIdentifier($column), $select);
            $selectString = implode(", ", $quotedColumns);
        }

        $params = [];
        $whereString = '';
        if (!empty($where)) {
            $clauses = [];
            foreach ($where as $i => $column) {
                $quotedCol = $this->quoteIdentifier($column);
                $value = $equals[$i] ?? null;

                // Support IN (...) when caller provides an array of values
                if (is_array($value)) {
                    if (count($value) === 0) {
                        // Empty IN() is always false, produce a safe clause that never matches.
                        $clauses[] = '0 = 1';
                        continue;
                    }
                    $placeholders = implode(', ', array_fill(0, count($value), '?'));
                    $clauses[] = "{$quotedCol} IN ({$placeholders})";
                    foreach ($value as $v) {
                        $params[] = $v;
                    }
                    continue;
                }

                // Support explicit NULL checks when value is null
                if ($value === null) {
                    $clauses[] = "{$quotedCol} IS NULL";
                    continue;
                }

                // Default: equality
                $clauses[] = "{$quotedCol} = ?";
                $params[] = $value;
            }
            $whereString = ' WHERE ' . implode(' AND ', $clauses);
        }

        $stmt = "SELECT {$selectString} FROM {$table}" . $whereString;

        // Optional ORDER BY support (use $ordered flag and $orderedBy column(s)).
        if ($ordered) {
            if ($orderedBy === null || trim($orderedBy) === '') {
                throw new DatabaseOperatorException("ORDER BY requested but no column provided.");
            }
            // Allow comma-separated list in $orderedBy, quote each identifier safely.
            $cols = array_map('trim', explode(',', $orderedBy));
            $quoted = array_map(fn($c) => $this->quoteIdentifier($c), $cols);
            $dir = $ascending ? 'ASC' : 'DESC';
            $orderParts = [];
            foreach ($quoted as $c) {
                $orderParts[] = $c . ' ' . $dir;
            }
            $stmt .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        // Optional FOR clause (must be one of a small whitelist). Append at the end.
        $forClause = '';
        if ($for !== null) {
            $normalized = strtoupper(trim($for));
            // Accept a few short synonyms for convenience.
            $map = [
                'FOR UPDATE' => 'FOR UPDATE',
                'UPDATE' => 'FOR UPDATE',
                'FOR SHARE' => 'FOR SHARE',
                'SHARE' => 'FOR SHARE',
                'LOCK IN SHARE MODE' => 'LOCK IN SHARE MODE',
                'LOCK' => 'LOCK IN SHARE MODE',
            ];
            if (!isset($map[$normalized])) {
                throw new DatabaseOperatorException("Invalid FOR clause requested: {$for}");
            }
            $forClause = ' ' . $map[$normalized];
            $stmt = $stmt . $forClause;
        }

        try {
            $query = $pdo->prepare($stmt);
            $query->execute($params);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new DatabaseOperatorException("Error executing query: " . $e->getMessage());
        }
    }

    /**
     * Inserts a new row into the specified table.
     *
     * @param string $into Table name to insert into (must be present in the internal whitelist).
     * @param array<int,string> $columns Column names for the insert.
     * @param array<int,mixed> $values Values to insert (must match columns count).
     * @return int|bool Returns the last insert ID as an int when available, or true on success
     *                  when the driver does not provide an insert ID.
     * @throws DatabaseOperatorException If columns/values count mismatch, table is invalid, or the query fails.
     */
    public function insert(string $into, array $columns, array $values): bool|int {
        if (count($columns) !== count($values)) {
            throw new DatabaseOperatorException("Mismatched columns and values count.");
        }
        if (!in_array($into, self::TABLES)) {
            throw new DatabaseOperatorException("Invalid table.");
        }

        $pdo = $this->getPdo();

        $table = $this->quoteIdentifier($into);
        $quotedColumns = array_map(fn($column) => $this->quoteIdentifier($column), $columns);
        $columnString = implode(", ", $quotedColumns);

        $placeholders = implode(", ", array_fill(0, count($values), "?"));
        $stmt = "INSERT INTO {$table} ({$columnString}) VALUES ({$placeholders})";

        try {
            $query = $pdo->prepare($stmt);
            $query->execute($values);
            $lastInsertId = $pdo->lastInsertId();
            return $lastInsertId !== false ? (int)$lastInsertId : true;
        } catch (PDOException $e) {
            throw new DatabaseOperatorException("Error executing query: " . $e->getMessage(), 0, $e);
        }
        return false; // Should never reach here.
    }

    /**
     * Updates rows in the specified table with given columns and values, filtered by conditions.
     *
     * The method binds parameters in the order: SET values, WHERE equals, NOT equals.
     * Negative conditions are optional and will be wrapped in an AND NOT (... ) clause.
     *
     * @param string $table Table name to update (must be present in the internal whitelist).
     * @param array<int,string> $columns Columns to update.
     * @param array<int,mixed> $values New values for the columns.
     * @param array<int,string> $where Columns to filter by (positional; counts must match $equals).
     * @param array<int,mixed> $equals Values to filter by (positional; counts must match $where).
     * @param array<int,string> $notWhere Optional negative WHERE columns used in an AND NOT (...) clause.
     * @param array<int,mixed> $notEquals Optional negative WHERE values used in an AND NOT (...) clause (counts must match $notWhere).
     * @return void
     * @throws DatabaseOperatorException If counts mismatch, table is not allowed, or the query fails.
     */
    public function update(string $table, array $columns, array $values, array $where, array $equals, array $notWhere = [], array $notEquals = []): void {
        if (count($columns) !== count($values)) {
            throw new DatabaseOperatorException("Mismatched columns and values count.");
        }
        if (!in_array($table, self::TABLES)) {
            throw new DatabaseOperatorException("Invalid table.");
        }

        $pdo = $this->getPdo();

        $table = $this->quoteIdentifier($table);
        $quotedColumns = array_map(fn($column) => $this->quoteIdentifier($column) . " = ?", $columns);
        $columnString = implode(", ", $quotedColumns);

        if (count($where) !== count($equals)) {
            throw new DatabaseOperatorException("Mismatched where and equals count.");
        }

        $quotedWhere = array_map(fn($column) => $this->quoteIdentifier($column) . " = ?", $where);
        $whereString = implode(" AND ", $quotedWhere);

        // Handle optional negative WHEREs (AND NOT (...))
        $negativeClause = '';
        if (!empty($notWhere) || !empty($notEquals)) {
            if (count($notWhere) !== count($notEquals)) {
                throw new DatabaseOperatorException("Mismatched notWhere and notEquals count.");
            }
            if (!empty($notWhere)) {
                $quotedNegative = array_map(fn($column) => $this->quoteIdentifier($column) . " = ?", $notWhere);
                $negativeClause = ' AND NOT (' . implode(' AND ', $quotedNegative) . ')';
            }
        }

        $stmt = "UPDATE {$table} SET {$columnString} WHERE {$whereString}" . $negativeClause;

        try {
            $query = $pdo->prepare($stmt);
            // Bind parameters in order: values (SET), equals (WHERE), notEquals (AND NOT)
            $params = array_merge($values, $equals, $notEquals);
            $query->execute($params);
        } catch (PDOException $e) {
            throw new DatabaseOperatorException("Error executing query: " . $e->getMessage(), 0, $e);
        }
    }
}
