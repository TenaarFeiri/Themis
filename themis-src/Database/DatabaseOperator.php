<?php
namespace Themis\Database;

use Themis\System\SystemDataStorage;
use Themis\Interface\DatabaseOperatorInterface;
use Themis\Database\DatabaseConnector;
use Themis\Data\ArrayProcessor;
use Themis\Data\StringProcessor;
use Themis\Utilities\Assertion as Assert;
use PDO;
use PDOException;
use Exception;

class DatabaseOperator implements DatabaseOperatorInterface
{
    // --- Properties ---
    private SystemDataStorage $sysData;
    private ?PDO $pdo = null;
    private ?string $transactionOwnerHash = null;
    private array $methodExpectedArgs = [
        'select' => ['select', 'from', 'where', 'equals', 'options'],
        'update' => ['table', 'columns', 'values', 'where', 'equals'],
        'insert' => ['table', 'columns', 'values', 'methodOptions'],
    ];

    // --- End Properties ---

    // --- Constants ---
    // --- Info Messages ---
    private const INFO_PDO_TRANSACTION_STARTED = "Info: Transaction started.";

    // --- Error Messages ---
    private const ERROR_UNKNOWN_PDO_FAILURE = "Error: PDO somehow passed initialisation but is not a PDO instance or still null.";
    private const ERROR_WHITELIST_MISMATCH = "Error: Key '%s' is not in the whitelist.";
    private const ERROR_WHITELIST_MISMATCH_COUNT = "Error: Key count mismatch between options and whitelist.";
    private const ERROR_SELECT_WHERE_MISMATCH = "Error: Key count mismatch between where and equals.";
    private const ERROR_UPDATE_ARRAY_POPULATION_ASSERTION_FAILURE = "Error: One or more update argument arrays are empty.";
    private const ERROR_COL_VAL_WILDCARD_MISMATCH = "Error: Number of wildcards do not match number of columns or values.";
    private const ERROR_COL_VAL_MISMATCH = "Error: Number of columns do not match number of values.";
    private const ERROR_UPDATE_WHERE_EMPTY = "Error: Where clause is empty.";
    private const ERROR_UPDATE_WHERE_EQUALS_MISMATCH = "Error: Number of where clauses do not match number of equals.";
    private const ERROR_UPDATE_WHERE_EQUALS_WILDCARD_MISMATCH = "Error: Number of wildcards do not match number of where clauses or equals.";
    private const ERROR_UPDATE_EXECUTION_FAILED = "Error: Update execution failed. See logs for details.";
    private const ERROR_INSERT_ARRAY_POPULATION_ASSERTION_FAILURE = "Error: One or more insert argument arrays are empty.";
    private const ERROR_INSERT_EXECUTION_FAILED = "Error: Insert execution failed. See logs for details.";
    
    private const ERROR_NOT_PDO = "Error: PDO instance failed to initialise.";
    private const ERROR_PDO_EXISTING_TRANSACTION = "Error: PDO already in transaction. Aborting."; // Consider removing? Ideally a running transaction is a good thing.
    private const ERROR_PDO_NOT_IN_TRANSACTION = "Error: PDO not in transaction.";
    private const ERROR_PDO_EXEC_FAILURE = "Error: PDO execute failed: %s";
    private const ERROR_PDO_NO_TRANSACTION_COMMIT = "Error: Cannot commit transaction. PDO not in transaction.";
    private const ERROR_PDO_TRANSACTION_OWNER_WRONG = "Error: Commit attempted by instance that is not the owner of this PDO transaction.";
    private const ERROR_PDO_TRANSACTION_WITHOUT_OWNER = "Error: Cannot commit transaction. No transaction owner hash set. This is a critical error.";
    
    private const WARNING_PDO_IN_TRANSACTION = "Warning: Transaction already exists. Proceeding anyway.";
    // --- End Constants ---

    public function __construct(SystemDataStorage $sysData)
    {
        $this->sysData = $sysData;
        if (!($this->sysData instanceof SystemDataStorage)) {
            throw new Exception("SystemDataStorage not included in DatabaseOperator class.");
        }
        if ($this->sysData->inDebugMode) {
            echo "DatabaseOperator initialized.", PHP_EOL;
            $test = $this->startOrGetPDO();
            if (($this->pdo instanceof PDO) === $this->startOrGetPDO()) {
                echo "DatabaseOperator PDO instance initialised.", PHP_EOL;
            } else {
                echo "DatabaseOperator PDO instance failed to initialise.", PHP_EOL;
            }
        }
    }
    // --- Methods ---
    public function startOrGetPDO(?string $connectTo = null, array $options = [], bool $getPdo = false) : PDO | bool
    {
        if ($this->pdo === null) {
            $connector = new DatabaseConnector();
            $this->pdo = $connector->connect($connectTo, $options);
            if (!$this->pdo instanceof PDO) {
                error_log(self::ERROR_NOT_PDO, 0);
                throw new Exception(self::ERROR_NOT_PDO, 1);
            }
        }
        if ($getPdo) {
            return $this->pdo;
        } elseif ($this->pdo instanceof PDO) {
            return true;
        } 
        return false;
    }

    public function beginTransaction(object $owner) : void
    {
        if ($this->startOrGetPDO()) { // Lazily initialise PDO if not already done
            if ($this->pdo->inTransaction()) {
                // If we are already in a transaction, we don't need to start a new one.
                if ($this->sysData->inDebugMode) {
                    echo self::WARNING_PDO_IN_TRANSACTION, PHP_EOL;
                }
            } else {
                $this->pdo->beginTransaction();
                $this->transactionOwnerHash = spl_object_hash($owner);
            }
            // Confirm we are in a transaction
            if ($this->pdo->inTransaction()) {
                if ($this->sysData->inDebugMode) {
                    echo self::INFO_PDO_TRANSACTION_STARTED, PHP_EOL;
                }
            } else {
                // Fail here because failure to start a transaction in the first place is critical.
                error_log(self::ERROR_PDO_NOT_IN_TRANSACTION, 0);
                throw new Exception(self::ERROR_PDO_NOT_IN_TRANSACTION, 1);
            }
        } else {
            error_log(self::ERROR_UNKNOWN_PDO_FAILURE, 0);
            throw new Exception(self::ERROR_UNKNOWN_PDO_FAILURE, 1);
        }
    }

    public function commit(object $owner) : void
    {
        if ($this->transactionOwnerHash === null) {
            // If there is no transaction owner hash, we cannot commit.
            throw new Exception(self::ERROR_PDO_TRANSACTION_WITHOUT_OWNER, 1);
        }
        if (!$this->pdo || !$this->pdo->inTransaction()) {
            throw new Exception(self::ERROR_PDO_NO_TRANSACTION_COMMIT, 1);
        }
        if ($this->transactionOwnerHash !== spl_object_hash($owner)) {
            throw new Exception(self::ERROR_PDO_TRANSACTION_OWNER_WRONG, 1);
        }

        $this->pdo->commit();
        if ($this->sysData->inDebugMode) {
            echo "Transaction committed successfully.", PHP_EOL;
        }
        $this->transactionOwnerHash = null; // Clear the transaction owner hash after commit.
    }

    public function rollBack(object $owner) : void
    {
        if (!$this->pdo || !$this->pdo->inTransaction()) {
            throw new Exception(self::ERROR_PDO_NO_TRANSACTION_COMMIT, 1);
        }
        if ($this->transactionOwnerHash !== spl_object_hash($owner)) {
            throw new Exception(self::ERROR_PDO_TRANSACTION_OWNER_WRONG, 1);
        }

        $this->pdo->rollBack();
        if ($this->sysData->inDebugMode) {
            echo "Transaction rolled back successfully.", PHP_EOL;
        }
        $this->transactionOwnerHash = null; // Clear the transaction owner hash after commit.
        // Then, for safety, kill PDO.
        $this->pdo = null;
    }

    public function select(...$args) : array
    {
        $expectedArgs = $this->methodExpectedArgs['select'];
        if (count($args) < count($expectedArgs)) {
            error_log("Method 'select' does not have all required arguments.", 0);
            throw new Exception("Method 'select' does not have all required arguments.", 1);
        }
        $assocArgs = $args[0] ?? [];
        $arrayProcessor = new ArrayProcessor($this->sysData);
        if (!$arrayProcessor->isAssociative($assocArgs)) {
            error_log("Method 'select' arguments are not associative.", 0);
            throw new Exception("Method 'select' arguments are not associative.", 1);
        }
        $select  = $assocArgs['select']  ?? throw new Exception("Select fields are required for select.", 1);
        $from    = $assocArgs['from']    ?? throw new Exception("From table is required for select.", 1);
        $where   = $assocArgs['where']   ?? throw new Exception("Where conditions are required for select.", 1);
        $equals  = $assocArgs['equals']  ?? throw new Exception("Equals conditions are required for select.", 1);
        $options = $assocArgs['options'] ?? '';

        // Validate that all required arguments are provided.
        Assert::noArraysAreEmpty([$select, [$from], $where, $equals], self::ERROR_WHITELIST_MISMATCH);
        Assert::arraysHaveEqualCount($where, $equals, self::ERROR_SELECT_WHERE_MISMATCH);

        $stringProcessor = new StringProcessor();

        // Sanitize and backtick table name
        $from = $stringProcessor->addSqlBackticks(
            $stringProcessor->removeSpecialCharacters($from, true, true, "_")
        );

        if ($from === '``' || $from === '') {
            error_log("Sanitized table name for SELECT is empty. Original: " . ($assocArgs['from'] ?? 'N/A'), 0);
            throw new Exception("Sanitized table name for SELECT is empty. Aborting.", 1);
        }

        // Sanitize and backtick select columns, unless it's just "*"
        if (in_array("*", $select)) {
            $select = ["*"];
        } else {
            $select = array_map(function($col) use ($stringProcessor) {
                $sanitized = $stringProcessor->removeSpecialCharacters($col, true, true, "_");
                if ($sanitized === '') {
                    throw new Exception("Sanitized column name is empty. Aborting select.", 1);
                }
                $backticked = $stringProcessor->addSqlBackticks($sanitized);
                if ($backticked === '``') {
                    throw new Exception("Sanitized column name is empty after backtick. Aborting select.", 1);
                }
                return $backticked;
            }, $select);
        }

        // Sanitize and backtick where columns
        $whereSanitized = array_map(function($col) use ($stringProcessor) {
            $sanitized = $stringProcessor->removeSpecialCharacters($col, true, true, "_");
            if ($sanitized === '') {
                throw new Exception("Sanitized where column name is empty. Aborting select.", 1);
            }
            $backticked = $stringProcessor->addSqlBackticks($sanitized);
            if ($backticked === '``') {
                throw new Exception("Sanitized where column name is empty after backtick. Aborting select.", 1);
            }
            return $backticked;
        }, $where);

        if (!$this->startOrGetPDO()) {
            error_log(self::ERROR_UNKNOWN_PDO_FAILURE, 0);
            throw new Exception(self::ERROR_UNKNOWN_PDO_FAILURE, 1);
        }
        if (count($where) !== count($equals)) {
            error_log(self::ERROR_SELECT_WHERE_MISMATCH, 0);
            throw new Exception(self::ERROR_SELECT_WHERE_MISMATCH, 1);
        }

        $whereWildcards = $arrayProcessor->generateWildcards($whereSanitized);
        if (count($whereWildcards) !== count($whereSanitized) || count($whereWildcards) !== count($equals)) {
            error_log(self::ERROR_SELECT_WHERE_MISMATCH, 0);
            throw new Exception(self::ERROR_SELECT_WHERE_MISMATCH, 1);
        }

        $combinedWhereClause = array_combine($whereSanitized, $whereWildcards);
        $finalWhereParts = [];
        foreach ($combinedWhereClause as $key => $value) {
            $finalWhereParts[] = $key . " = " . $value;
        }
        $finalWhereString = implode(" AND ", $finalWhereParts);

        $statement = "SELECT " . implode(", ", $select) . "
        FROM " . $from . "
        WHERE " . $finalWhereString . " 
        " . $options;

        if ($this->sysData->inDebugMode) {
            echo "Select statement: ", $statement, PHP_EOL;
        }

        $preparedStatement = $this->pdo->prepare($statement);
        if ($preparedStatement === false) {
            error_log(self::ERROR_UNKNOWN_PDO_FAILURE, 0);
            throw new Exception(self::ERROR_UNKNOWN_PDO_FAILURE, 1);
        }
        try {
            $execute = $preparedStatement->execute($equals);
        } catch (PDOException $e) {
            error_log(sprintf(self::ERROR_PDO_EXEC_FAILURE, $e->getMessage()), 0);
            throw new Exception("Failed to execute prepared statements. See logs.", 1);
        }
        if (!$execute) {
            error_log("\$execute false. This should have been caught by the catch block. Statement: " . $statement, 0);
            throw new Exception("Failed to execute prepared statements. See logs.", 1);
        }

        $result = $preparedStatement->fetchAll(PDO::FETCH_ASSOC);
        return $result; // Return result. Empty array is valid.
    }

    public function update(...$args) : bool
    {
        // Check if the method has all expected arguments from $this->methodExpectedArgs.
        $expectedArgs = $this->methodExpectedArgs['update'];
        if (count($args) < count($expectedArgs)) {
            error_log("Method 'update' does not have all required arguments.", 0);
            throw new Exception("Method 'update' does not have all required arguments.", 1);
        }
        $assocArgs = $args[0] ?? [];
        $arrayProcessor = new ArrayProcessor($this->sysData);
        if (!$arrayProcessor->isAssociative($assocArgs)) {
            error_log("Method 'update' arguments are not associative.", 0);
            throw new Exception("Method 'update' arguments are not associative.", 1);
        }
        $table   = $assocArgs['table']   ?? throw new Exception("Table name is required for update.", 1);
        $columns = $assocArgs['columns'] ?? throw new Exception("Columns are required for update.", 1);
        $values  = $assocArgs['values']  ?? throw new Exception("Values are required for update.", 1);
        $where   = $assocArgs['where']   ?? throw new Exception("Where conditions are required for update.", 1);
        $equals  = $assocArgs['equals']  ?? throw new Exception("Equals conditions are required for update.", 1);
        Assert::noArraysAreEmpty([$columns, $values, $where, $equals], self::ERROR_UPDATE_ARRAY_POPULATION_ASSERTION_FAILURE);
        Assert::arraysHaveEqualCount($columns, $values, self::ERROR_COL_VAL_MISMATCH);
        Assert::arraysHaveEqualCount($where, $equals, self::ERROR_UPDATE_WHERE_EQUALS_MISMATCH);

        if (!$this->startOrGetPDO()) {
            error_log(self::ERROR_UNKNOWN_PDO_FAILURE, 0);
            throw new Exception(self::ERROR_UNKNOWN_PDO_FAILURE, 1);
        } elseif (!$this->pdo->inTransaction()) {
            // Update is a critical operation. We need to be in a transaction.
            error_log(self::ERROR_PDO_NOT_IN_TRANSACTION, 0);
            throw new Exception(self::ERROR_PDO_NOT_IN_TRANSACTION, 1);
        }

        $stringProcessor = new StringProcessor();   
        $table = $stringProcessor->addSqlBackticks(
            $stringProcessor->removeSpecialCharacters($table, true, true, "_")
        );

        $statement = "UPDATE " . $table . " SET ";

        $wildcards = $arrayProcessor->generateWildcards($columns);
        $numberOfWildcards = count($wildcards);

        Assert::arraysHaveEqualCount($columns, $wildcards, self::ERROR_COL_VAL_WILDCARD_MISMATCH);

        $update = [];
        foreach ($columns as $key => $column) {
            $update[] = $stringProcessor->addSqlBackticks(
                $stringProcessor->removeSpecialCharacters($column, true, true, "_")
             ) . " = " . $wildcards[$key];
        }
        $update = implode(", ", $update);

        $statement .= $update . " WHERE ";

        $whereWildcards = $arrayProcessor->generateWildcards($where);

        Assert::arraysHaveEqualCount($where, $whereWildcards, self::ERROR_UPDATE_WHERE_EQUALS_WILDCARD_MISMATCH);

        $combinedWhereClause = array_combine($where, $whereWildcards);
        $finalWhereParts = [];
        foreach ($combinedWhereClause as $key => $value) {
            $finalWhereParts[] = $stringProcessor->addSqlBackticks(
                $stringProcessor->removeSpecialCharacters($key, true, true, "_")
            ) . " = " . $value;
        }
        $finalWhereString = implode(" AND ", $finalWhereParts);
        $statement .= $finalWhereString;

        if ($this->sysData->inDebugMode) {
            echo "Update statement: ", $statement, PHP_EOL;
        }

        $preparedStatement = $this->pdo->prepare($statement);
        if ($preparedStatement === false) {
            error_log(self::ERROR_UNKNOWN_PDO_FAILURE, 0);
            throw new Exception(self::ERROR_UNKNOWN_PDO_FAILURE, 1);
        }

        try {
            $execute = $preparedStatement->execute(array_merge($values, $equals));
        } catch (PDOException $e) {
            error_log(sprintf(self::ERROR_PDO_EXEC_FAILURE, $e->getMessage()), 0);
            throw new Exception(self::ERROR_UPDATE_EXECUTION_FAILED, 1);
        }
        if (!$execute) {
            // Fail here if, for some reason, $execute is false.
            error_log("\$execute false. This should have been caught by the catch block. Statement: " . $preparedStatement, 0);
            throw new Exception(self::ERROR_UPDATE_EXECUTION_FAILED, 1);
        } else {
            if ($this->sysData->inDebugMode) {
                echo "Update executed successfully.", PHP_EOL;
            }
            return true;
        }
        // This should never be able to happen. Unreachable safeguard out of an abundance of caution.
        throw new Exception("Update failed for unknown reasons.", 1);
    }

    public function insert(...$args) : array
    {
        // Check if the method has all expected arguments from $this->methodExpectedArgs.
        $expectedArgs = $this->methodExpectedArgs['insert'];
        if (count($args) < count($expectedArgs)) {
            error_log("Method 'insert' does not have all required arguments.", 0);
            throw new Exception("Method 'insert' does not have all required arguments.", 1);
        }
        $assocArgs = $args[0] ?? [];
        $arrayProcessor = new ArrayProcessor($this->sysData);
        if (!$arrayProcessor->isAssociative($assocArgs)) {
            error_log("Method 'insert' arguments are not associative.", 0);
            throw new Exception("Method 'insert' arguments are not associative.", 1);
        }
        $table        = $assocArgs['table']        ?? throw new Exception("Table name is required for insert.", 1);
        $columns      = $assocArgs['columns']      ?? throw new Exception("Columns are required for insert.", 1);
        $values       = $assocArgs['values']       ?? throw new Exception("Values are required for insert.", 1);
        $methodOptions = $assocArgs['methodOptions'] ?? [];

        Assert::noArraysAreEmpty([$columns, $values], self::ERROR_INSERT_ARRAY_POPULATION_ASSERTION_FAILURE);

        if (!$this->startOrGetPDO()) {
            error_log(self::ERROR_UNKNOWN_PDO_FAILURE, 0);
            throw new Exception(self::ERROR_UNKNOWN_PDO_FAILURE, 1);
        } elseif (!$this->pdo->inTransaction()) {
            error_log(self::ERROR_PDO_NOT_IN_TRANSACTION, 0);
            throw new Exception(self::ERROR_PDO_NOT_IN_TRANSACTION, 1);
        }

        if (count($columns) !== count($values)) {
            error_log(self::ERROR_COL_VAL_MISMATCH, 0);
            throw new Exception(self::ERROR_COL_VAL_MISMATCH, 1);
        }

        $stringProcessor = new StringProcessor();
        $tableSanitized = $stringProcessor->addSqlBackticks(
        $stringProcessor->removeSpecialCharacters($table, true, true, "_")
        );
        if ($tableSanitized === '``' || $tableSanitized === '') {
        error_log("Sanitized table name is empty. Aborting insert.", 0);
        throw new Exception("Sanitized table name is empty. Aborting insert.", 1);
        }
        $columnsSanitized = array_map(function($col) use ($stringProcessor) {
        $sanitized = $stringProcessor->removeSpecialCharacters($col, true, true, "_");
        if ($sanitized === '') {
            throw new Exception("Sanitized column name is empty. Aborting insert.", 1);
        }
        $backticked = $stringProcessor->addSqlBackticks($sanitized);
        if ($backticked === '``') {
            throw new Exception("Sanitized column name is empty after backtick. Aborting insert.", 1);
        }
        return $backticked;
        }, $columns);

        $wildcards = $arrayProcessor->generateWildcards($columnsSanitized);
        Assert::arraysHaveEqualCount($columnsSanitized, $wildcards, self::ERROR_COL_VAL_WILDCARD_MISMATCH);

        $statement = "INSERT INTO $tableSanitized (" . implode(", ", $columnsSanitized) . ") VALUES (" . implode(", ", $wildcards) . ")";

        if ($this->sysData->inDebugMode) {
        echo "Insert statement: ", $statement, PHP_EOL;
        }

        $preparedStatement = $this->pdo->prepare($statement);
        if ($preparedStatement === false) {
        error_log(self::ERROR_UNKNOWN_PDO_FAILURE, 0);
        throw new Exception(self::ERROR_UNKNOWN_PDO_FAILURE, 1);
        }

        try {
        $execute = $preparedStatement->execute($values);
        } catch (PDOException $e) {
        error_log(sprintf(self::ERROR_PDO_EXEC_FAILURE, $e->getMessage()), 0);
        throw new Exception(self::ERROR_INSERT_EXECUTION_FAILED, 1);
        }
        if (!$execute) {
        error_log("\$execute false. This should have been caught by the catch block. Statement: " . $statement, 0);
        throw new Exception(self::ERROR_INSERT_EXECUTION_FAILED, 1);
        }

        $willReturn = [];
        if (!empty($methodOptions)) {
        if ($arrayProcessor->isInArray($methodOptions, "returnLastInsertId")) {
            $willReturn["lastInsertId"] = $this->pdo->lastInsertId();
        }
        }

        if ($this->sysData->inDebugMode) {
        echo "Insert executed successfully.", PHP_EOL;
        }
        return $willReturn;

        // Unreachable safeguard
        throw new Exception("Insert failed for unknown reasons.", 1);
    }
}

