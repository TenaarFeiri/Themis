<?php
    namespace Themis\Controller;

    use Themis\System\SystemDataStorage;
    use Themis\Interface\DatabaseControllerInterface;
    use Themis\Database\DatabaseOperator;
    use PDO;
    use PDOException;
    use Exception;
    class DatabaseController implements DatabaseControllerInterface
    {
        // --- Properties ---
        private bool $inDebugMode = false;
        private SystemDataStorage $data;

        // --- End Properties ---

        public function __construct(SystemDataStorage $systemDataStorage)
        {
            $this->data = $systemDataStorage;
            if ($this->data->inDebugMode()) {
                print_r($this->data->getAllData()); // Print all stored data in debug mode.
                echo "DatabaseController initialized.", PHP_EOL;
            }
        }

        public function verifyOrImportUser() : bool
        {
            try {
                $databaseOperator = new DatabaseOperator($this->inDebugMode);
            } catch (Exception $e) {
                error_log("make error msg later: " . $e->getMessage(), 0);
                return false;
            }
            return false;
        }

        // --- Methods ---
    }