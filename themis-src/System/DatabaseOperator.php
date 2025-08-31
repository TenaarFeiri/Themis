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
 * This class provides a standardized and robust interface for managing a PDO
 * database connection and transactions. It centralizes error handling and
 * promotes the "Don't Repeat Yourself" (DRY) principle.
 */
/**
 * Class DatabaseOperator
 *
 * Provides a robust abstraction layer for PDO database operations, including connection management,
 * transaction handling, and CRUD operations. Centralizes error handling and enforces safe query practices.
 */
class DatabaseOperatorException extends Exception {}
class DatabaseOperator {
    private ?ThemisContainer $container = null;
    private DataContainer $dataContainer;
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
     * @param ThemisContainer $container Dependency injection container.
     */
    public function __construct(ThemisContainer $container) {
        $this->container = $container;
        $this->dataContainer = $this->container->get('dataContainer');
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
     * @throws DatabaseOperatorException If a transaction is active or connection does not exist.
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
     * @param string $query The SQL query to execute.
     * @param array $params Parameters to bind to the query.
     * @return array Query results as an associative array.
     * @throws DatabaseOperatorException If query is destructive or execution fails.
     */
    public function manualQuery(string $query, array $params = []): array {
        // Forbid some destructive queries
        if (preg_match(pattern: '/^(?:DELETE|DROP|TRUNCATE|REPLACE)/i', subject: $query)) {
            throw new DatabaseOperatorException("Destructive queries are not allowed.");
        }
        $pdo = $this->getPdo();
        try {
            $statement = $pdo->prepare(query: $query);
            $statement->execute(params: $params);
            return $statement->fetchAll(mode: PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new DatabaseOperatorException("Error executing query: " . $e->getMessage());
        }
    }

    /**
     * Begins a new database transaction.
     *
     * @throws DatabaseOperatorException If already in a transaction or PDO is not established.
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
     * @throws DatabaseOperatorException If not in a transaction or PDO is not established.
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
     * @throws DatabaseOperatorException If not in a transaction or PDO is not established.
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
     * @param string $identifier Identifier to quote.
     * @return string Quoted identifier.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return "`" . str_replace("`", "``", $identifier) . "`";
    }


    /**
     * Selects data from a table with specified columns and conditions.
     *
     * @param array $select Columns to select (e.g., ['*'] or specific column names).
     * @param string $from Table name to select from.
     * @param array $where Columns to filter by.
     * @param array $equals Values to filter by (must match $where count).
     * @return array Selected data as associative array.
     * @throws DatabaseOperatorException If table is invalid or where/equals count mismatch.
     */
    public function select(array $select, string $from, array $where, array $equals): array {
        if (!in_array($from, self::TABLES)) {
            throw new DatabaseOperatorException("Invalid table.");
        }

        $pdo = $this->getPdo();

        $table = $this->quoteIdentifier((string)$from);
        if (count($where) !== count($equals)) {
            throw new DatabaseOperatorException("Mismatched where and equals count.");
        }

        $selectString = "";
        if (in_array("*", $select, true)) {
            $selectString = "*";
        } else {
            $quotedColumns = array_map(fn($column) => $this->quoteIdentifier($column), $select);
            $selectString = implode(", ", $quotedColumns);
        }

        $quotedWhere = array_map(fn($column) => $this->quoteIdentifier($column) . " = ?", $where);
        $whereString = implode(" AND ", $quotedWhere);

        $stmt = "SELECT {$selectString} FROM {$table} WHERE {$whereString}";

        try {
            $query = $pdo->prepare($stmt);
            $query->execute($equals);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new DatabaseOperatorException("Error executing query: " . $e->getMessage());
        }
    }

    /**
     * Inserts a new row into the specified table.
     *
     * @param string $into Table name to insert into.
     * @param array $columns Column names for the insert.
     * @param array $values Values to insert (must match columns count).
     * @throws DatabaseOperatorException If columns/values count mismatch, table is invalid, or query fails.
     */
    public function insert(string $into, array $columns, array $values): void {
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
        } catch (PDOException $e) {
            throw new DatabaseOperatorException("Error executing query: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Updates rows in the specified table with given columns and values, filtered by conditions.
     *
     * @param string $table Table name to update.
     * @param array $columns Columns to update.
     * @param array $values New values for the columns.
     * @param array $where Columns to filter by.
     * @param array $equals Values to filter by (must match $where count).
     * @throws DatabaseOperatorException If columns/values count mismatch, table is invalid, or query fails.
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
