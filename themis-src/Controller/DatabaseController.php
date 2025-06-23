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
        private SystemDataStorage $systemData;
        private array $methodExpectedArgs = [
            'select' => ['select', 'from', 'where', 'equals', 'options'],
            'update' => ['table', 'columns', 'values', 'where', 'equals'],
            'insert' => ['table', 'columns', 'values', 'methodOptions'],
        ];
        // --- End Properties ---

        public function __construct(SystemDataStorage $systemDataStorage)
        {
            $this->systemData = $systemDataStorage;
            if ($this->systemData->inDebugMode) {
                print_r($this->systemData->getAllData()); // Print all stored data in debug mode.
                echo "DatabaseController initialized.", PHP_EOL;
            }
        }

        public function execute() : array
        {
            if ($this->systemData->inDebugMode) {
                echo "Executing DatabaseController.", PHP_EOL;
            }

            $userData = $this->systemData->readData('user');
            if (empty($userData) || !is_array($userData)) {
                throw new Exception("User data is empty or not an array.", 1);
            }

            $dbCmd = $this->systemData->readData('dbCommand'); // Always array.
            if (empty($dbCmd) || !is_array($dbCmd)) {
                throw new Exception("Database command is empty or not an array.", 1);
            }
            $dbOperator = new DatabaseOperator($this->systemData);
            $method = $dbCmd['method'] ?? null;
            if (!$method || !method_exists($dbOperator, $method)) {
                throw new Exception("Database method '$method' does not exist in DatabaseOperator.", 1);
            }
            if (!$this->execMethodHasAllArgs($dbCmd, $method)) {
                throw new Exception("Database method '$method' does not have all required arguments.", 1);
            }

            // Move provided arguments to separate array.
            $args = [];
            foreach ($dbCmd as $key => $value) {
                if (in_array($key, $this->methodExpectedArgs[$method] ?? [])) {
                    $args[$key] = $value;
                }
            }
            if ($this->systemData->inDebugMode) {
                echo "Executing method '$method' with arguments: ", print_r($args, true), PHP_EOL;
            }
            try {
                if ($method !== 'select') {
                    $dbOperator->beginTransaction($dbOperator);
                }
                $result = call_user_func_array([$dbOperator, $method], $args);
                if ($this->systemData->inDebugMode) {
                    echo "Method '$method' executed successfully.", PHP_EOL;
                }
                if (!is_array($result)) {
                    if (is_bool($result) && $result === false) {
                        $dbOperator->rollback($dbOperator);
                        return [1, $result, "Update failed."]; // 1 = error, $result = boolean result
                    }
                }
                if ($method !== 'select') {
                    $dbOperator->commit($dbOperator);
                }
                if ($this->systemData->inDebugMode) {
                    echo "Database operation completed successfully.", PHP_EOL;
                }
                return [0, false, $result]; // 0 = success, false = do not repeat, $result = data
            } catch (PDOException $e) {
                throw new Exception("Database operation failed: " . $e->getMessage(), 1);
            }
            return [];
        }

        private function execMethodHasAllArgs(array $args, string $method) : bool
        {
            if ($this->systemData->inDebugMode) {
                echo "Checking if method '$method' has all required arguments.", PHP_EOL;
            }
            if (!array_key_exists($method, $this->methodExpectedArgs)) {
                throw new Exception("Method '$method' is not defined in expected arguments.", 1);
            }
            $expectedArgs = $this->methodExpectedArgs[$method];
            foreach ($expectedArgs as $arg) {
                if (!array_key_exists($arg, $args)) {
                    return false; // Missing argument.
                }
            }
            return true; // All arguments present.
        }

        public function verifyOrImportUser() : bool
        {
            if ($this->systemData->inDebugMode) {
                echo "Verifying or importing user.", PHP_EOL;
            }
            $headers = $this->systemData->readData('headerData');
            if (empty($headers) || !is_array($headers)) {
                throw new Exception("Header data is empty or not an array.", 1);
            }
            // Get username and key from headers. Second Life x http headers.
            $key = $headers['HTTP_X_SECONDLIFE_OWNER_KEY'] ?? false;
            $username = $headers['HTTP_X_SECONDLIFE_OWNER_NAME'] ?? false;
            if (!$key || !$username) {
                throw new Exception("Key or username not found in headers.", 1);
            }

            return true; // testing
        }

        // --- Methods ---
    }