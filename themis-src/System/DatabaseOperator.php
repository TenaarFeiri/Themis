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
        "player_tags"
    ];
    
    public function __construct(ThemisContainer $container) {
        $this->container = $container;
        $dataContainer = $this->container->get('dataContainer');
    }

    /**
     * Connects to the database, using the DatabaseConnector class.
     *
     * @param string|null $databaseName The name of the database to connect to.
     * @param array $options Additional options for the PDO connection.
     * @throws DatabaseOperatorException If the PDO connection fails.
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

    public function useConnection(string $connectionName): void {
        if ($this->pdo->inTransaction()) {
            // STOP! Throw error!
            throw new DatabaseOperatorException("Cannot change connection while in transaction.");
        }
        $this->whichPdo = $connectionName;
        $this->pdo = $this->pdoInstances[$connectionName];
    }

    public function isCurrentConnection(string $connectionName): bool {
        return $this->whichPdo === $connectionName;
    }

    public function hasConnection(string $connectionName): bool {
        return isset($this->pdoInstances[$connectionName]);
    }

    /**
     * Gets the internal PDO instance.
     *
     * @return PDO The PDO instance.
     * @throws DatabaseOperatorException If the PDO instance is not established.
     */
    public function getPdo(): PDO {
        if (!$this->pdo) {
            throw new DatabaseOperatorException("PDO not established.");
        }
        return $this->pdo;
    }

    /**
     * Prepares and executes a direct SQL query input from invoking object.
     */
    public function manualQuery(string $query, array $params = []): array {
        // Forbid some destructive queries
        if (preg_match('/^(?:DELETE|DROP|TRUNCATE|REPLACE)/i', $query)) {
            throw new DatabaseOperatorException("Destructive queries are not allowed.");
        }
        $pdo = $this->getPdo();
        try {
            $statement = $pdo->prepare($query);
            $statement->execute($params);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new DatabaseOperatorException("Error executing query: " . $e->getMessage());
        }
    }

    /**
     * Begins a transaction.
     *
     * @throws DatabaseOperatorException If already in a transaction or if the PDO connection is not established.
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
     * Commits the current transaction.
     *
     * @throws DatabaseOperatorException If not in a transaction or if the PDO connection is not established.
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
     * Rolls back the current transaction.
     *
     * @throws DatabaseOperatorException If not in a transaction or if the PDO connection is not established.
     */
    public function rollbackTransaction() : void
    {
        $pdo = $this->getPdo();
        if (!$pdo->inTransaction()) {
            throw new DatabaseOperatorException("Could not rollback; not in transaction.");
        }
        $pdo->rollBack();
    }

    private function quoteIdentifier(string $identifier): string
    {
        // A simple check to ensure we don't double-quote
        return "`" . str_replace("`", "``", $identifier) . "`";
    }


    /**
     * Selects data from a table.
     *
     * @param array $select The columns to select.
     * @param int $from The table to select from, represented as an integer id from the TABLES constant.
     * @param array $where The columns to filter by.
     * @param array $equals The values to filter by.
     * @return array The selected data.
     * @throws DatabaseOperatorException If the table is invalid or if the where and equals count mismatch.
     */
    public function select(array $select, string $from, array $where, array $equals): array {
        if (!in_array($from, self::TABLES)) {
            throw new DatabaseOperatorException("Invalid table.");
        }

        $pdo = $this->getPdo();

        $table = $this->quoteIdentifier($from);
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

    public function update(string $table, array $columns, array $values, array $where, array $equals): void {
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

        $quotedWhere = array_map(fn($column) => $this->quoteIdentifier($column) . " = ?", $where);
        $whereString = implode(" AND ", $quotedWhere);

        $stmt = "UPDATE {$table} SET {$columnString} WHERE {$whereString}";

        try {
            $query = $pdo->prepare($stmt);
            $query->execute(array_merge($values, $equals));
        } catch (PDOException $e) {
            throw new DatabaseOperatorException("Error executing query: " . $e->getMessage(), 0, $e);
        }
    }
}
