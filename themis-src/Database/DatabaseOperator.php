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
        private bool $inDebugMode = false;
        private ?PDO $pdo = null;
        private ?string $whoBeganTransaction = null;

        // --- End Properties ---

        // --- Constants ---
        private const INFO_PDO_TRANSACTION_STARTED = "Info: Transaction started.";

        private const ERROR_NOT_PDO = "Error: PDO instance failed to initialise.";
        private const ERROR_UNKNOWN_PDO_FAILURE = "Error: PDO somehow passed initialisation but is not a PDO instance or still null.";
        private const ERROR_WHITELIST_MISMATCH = "Error: Key '%s' is not in the whitelist.";
        private const ERROR_WHITELIST_MISMATCH_COUNT = "Error: Key count mismatch between options and whitelist.";
        private const ERROR_SELECT_WHERE_MISMATCH = "Error: Key count mismatch between where and equals.";
        private const ERROR_UPDATE_ARRAY_POPULATION_ASSERTION_FAILURE = "Error: One or more update argument arrays are empty.";
        private const ERROR_UPDATE_COL_VAL_WILDCARD_MISMATCH = "Error: Number of wildcards do not match number of columns or values.";
        private const ERROR_UPDATE_COL_VAL_MISMATCH = "Error: Number of columns do not match number of values.";
        private const ERROR_UPDATE_WHERE_EMPTY = "Error: Where clause is empty.";
        private const ERROR_UPDATE_WHERE_EQUALS_MISMATCH = "Error: Number of where clauses do not match number of equals.";
        private const ERROR_UPDATE_WHERE_EQUALS_WILDCARD_MISMATCH = "Error: Number of wildcards do not match number of where clauses or equals.";
        private const ERROR_PDO_EXISTING_TRANSACTION = "Error: PDO already in transaction. Aborting."; // Consider removing? Ideally a running transaction is a good thing.
        private const ERROR_PDO_NOT_IN_TRANSACTION = "Error: PDO not in transaction.";
        private const ERROR_PDO_EXEC_FAILURE = "Error: PDO execute failed: %s";
        
        private const WARNING_PDO_IN_TRANSACTION = "Warning: Transaction already exists. Proceeding anyway.";
        // --- End Constants ---

        public function __construct(bool $inDebugMode)
        {
            if ($inDebugMode) {
                $this->inDebugMode = $inDebugMode;
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

        public function beginTransaction() : void
        {
            if ($this->startOrGetPDO()) { // Lazily initialise PDO if not already done
                if ($this->pdo->inTransaction()) {
                    // If we are already in a transaction, we don't need to start a new one.
                    if ($this->inDebugMode) {
                        echo WARNING_PDO_IN_TRANSACTION, PHP_EOL;
                    }
                } else {
                    $this->pdo->beginTransaction();
                }
                // Confirm we are in a transaction
                if ($this->pdo->inTransaction()) {
                    if ($this->inDebugMode) {
                        echo INFO_PDO_TRANSACTION_STARTED, PHP_EOL;
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

        public function select(array $select, string $from, array $where, array $equals, ?string $options = null) : array
        {
            if (!$this->startOrGetPDO()) {
                error_log(self::ERROR_UNKNOWN_PDO_FAILURE, 0);
                throw new Exception(self::ERROR_UNKNOWN_PDO_FAILURE, 1);
            }
            if (count($where) !== count($equals)) {
                error_log(self::ERROR_SELECT_WHERE_MISMATCH, 0);
                throw new Exception(self::ERROR_SELECT_WHERE_MISMATCH, 1);
            }
            if (in_array("*", $select)) {
                $select = ["*"];
            }
            $arrayProcessor = new ArrayProcessor($this->inDebugMode);
            $whereWildcards = $arrayProcessor->generateWildcards($where);
            if (count($whereWildcards) !== count($where) || count($whereWildcards) !== count($equals)) {
                // And one more check & failure if the count is incorrect.
                error_log(self::ERROR_SELECT_WHERE_MISMATCH, 0);
                throw new Exception(self::ERROR_SELECT_WHERE_MISMATCH, 1);
            }
            $combinedWhereClause = array_combine($where, $whereWildcards);
            $finalWhereString = "";
            foreach ($combinedWhereClause as $key => $value) {
                $finalWhereString .= $key . " = " . $value . " AND ";
            }
            $finalWhereString = rtrim($finalWhereString, " AND ");
            $statement = "SELECT " . implode(",", $select) . "
            FROM " . $from . "
            WHERE " . $finalWhereString . " 
            " . $options;
            if ($this->inDebugMode) {
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
                // Fail here if, for some reason, $execute is false.
                error_log("\$execute false. This should have been caught by the catch block. Statement: " . $preparedStatement, 0);
                throw new Exception("Failed to execute prepared statements. See logs.", 1);
            }

            $result = $preparedStatement->fetchAll(PDO::FETCH_ASSOC);
            return $result; // Return result. Empty array is valid.
        }

        public function update(string $table, array $columns, array $values, array $where, array $equals) : bool
	    {
            Assert::noArraysAreEmpty([$columns, $values, $where, $equals], self::ERROR_UPDATE_ARRAY_POPULATION_ASSERTION_FAILURE);
            Assert::arraysHaveEqualCount($columns, $values, self::ERROR_UPDATE_COL_VAL_MISMATCH);
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

            $arrayProcessor = new ArrayProcessor($this->inDebugMode);
            $wildcards = $arrayProcessor->generateWildcards($columns);
            $numberOfWildcards = count($wildcards);

            Assert::arraysHaveEqualCount($columns, $wildcards, self::ERROR_UPDATE_COL_VAL_WILDCARD_MISMATCH);

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
        }
    }

