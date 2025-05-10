<?php
    namespace Themis\Controller;
    
    use Themis\System\SystemDataStorage; // ALL objects use this, NO EXCEPTIONS.
    use Themis\Interface\MasterControllerInterface;
    use Themis\Controller\DatabaseController;
    use Themis\Controller\CharacterController;
    use Exception;

    class MasterController implements MasterControllerInterface
    {
        // --- Properties ---
        private SystemDataStorage $data;
        private array $headerData = [];
        private array $requestData = [];
        private array $options = [];
        private bool $inDebugMode = false;
        private const MODULE_CONTROLLERS = [
            'character' => CharacterController::class,
            'database' => DatabaseController::class
        ];
        // --- End Properties ---

        // --- Constants ---
        private const ERROR_MASTER_CONTROLLER_GENERIC = "MasterController failure.";
        private const ERROR_MASTER_CONTROLLER_NO_ACTION = "No action specified in the request data.";
        private const ERROR_MASTER_CONTROLLER_NO_MODULE = "No module specified in the request data.";
        private const ERROR_MASTER_CONTROLLER_NO_ACTION_OR_MODULE = "No action or module specified in the request data.";
        private const ERROR_MASTER_CONTROLLER_USER_AUTHENTICATION_FAILURE = "User authentication failed. USER: %s";
        private const ERROR_CLASS_NOT_FOUND = "Controller class %s does not exist.";
        private const ERROR_CLASS_NOT_INSTANCE = "Controller class %s is not an instance of %s.";
        // --- End Constants ---

        public function __construct(array $headerData, array $requestData, bool $inDebugMode)
        {
            $this->data = new SystemDataStorage($inDebugMode);
            $this->data->storeData('headerData', $headerData); 
            $this->data->storeData('requestData', $requestData); 
            if ($this->data->inDebugMode()) {
                echo PHP_EOL, "MasterController initialized.", PHP_EOL;
            }
            // Here is where we will check user credentials with the database.
            if(!$this->authenticateUser()) {
                //throw new Exception("User authentication failed.", 1);
            }
            if (array_key_exists('options', $this->headerData) && !empty($this->headerData['options'])) 
            {
                $this->options = implode(';', $this->headerData['options']);
            }
            if (!$this->checkRequestParameters()) {
                throw new Exception("No action specified in the request data.", 1);
            }
        }

        // --- Methods ---
        public function getRequestData() : array
        {
            return $this->requestData;
        }

        public function getHeaderData() : array
        {
            return $this->headerData;
        }

        private function authenticateUser() : bool
        {
            try {
                $databaseControllerClass = self::MODULE_CONTROLLERS['database'];
                if (!class_exists($databaseControllerClass)) {
                    error_log(sprintf(self::ERROR_CLASS_NOT_FOUND, $databaseControllerClass));
                    throw new Exception("Controller class $databaseControllerClass does not exist.", 1);
                }
                $controller = new $databaseControllerClass($this->data);
                if (!($controller instanceof $databaseControllerClass)) {
                    error_log(sprintf(self::ERROR_CLASS_NOT_INSTANCE, $databaseControllerClass, self::MODULE_CONTROLLERS['database']));
                    throw new Exception("Controller class $databaseControllerClass is not an instance of " . self::MODULE_CONTROLLERS['database'], 1);
                }
                if (!$controller->verifyOrImportUser()) {
                    $user = [
                        'key' => $this->data->readData('headerData')['HTTP_X_SECONDLIFE_OWNER_KEY'],
                        'name' => $this->data->readData('headerData')['HTTP_X_SECONDLIFE_OWNER_NAME']
                    ];
                    error_log(sprintf(self::ERROR_MASTER_CONTROLLER_USER_AUTHENTICATION_FAILURE, print_r($user, true)));
                    throw new Exception("User authentication failed.", 1);
                    return false;
                }
                return true;
            } catch (Exception $e) {
                if ($this->data->inDebugMode()) {
                    echo "Error: ", $e->getMessage(), PHP_EOL;
                }
                throw new Exception("Error initializing database controller.", 1);
            }
            // TODO: implement, need operator class done first
        }

        private function checkRequestParameters() : bool
        {
            if (array_key_exists('module', $this->requestData) && !empty($this->requestData['module'])) {
                if (array_key_exists('action', $this->requestData) && !empty($this->requestData['action'])) {
                    return true;
                }
            }
            return false;
        }
        // --- End Methods ---
    }
    