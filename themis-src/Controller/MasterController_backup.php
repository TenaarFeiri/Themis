<?php
namespace Themis\Controller;
exit("No. Nice try, though!"); // Just... no.

use Themis\System\SystemDataStorage; // ALL objects use this, NO EXCEPTIONS.
use Themis\Interface\MasterControllerInterface;
use Themis\Controller\DatabaseController;
use Themis\Controller\CharacterController;
use Themis\Controller\TestController;
use Exception;

class MasterController implements MasterControllerInterface
{
    // --- Properties ---
    private SystemDataStorage $systemData;
    private array $options = [];
    private bool $inDebugMode = false;
    private const MODULE_CONTROLLERS = [
        'character' => CharacterController::class,
        'database' => DatabaseController::class,
        'test' => TestController::class // This is where we'll write our test cases.
    ];
    // --- End Properties ---

    // --- Constants ---
    private const MAXIMUM_REPEAT_REQUESTS = 5; // Maximum number re-runs.
    private const ERROR_MASTER_CONTROLLER_GENERIC = "MasterController failure.";
    private const ERROR_MASTER_CONTROLLER_NO_ACTION = "No action specified in the request data.";
    private const ERROR_MASTER_CONTROLLER_NO_MODULE = "No module specified in the request data.";
    private const ERROR_MASTER_CONTROLLER_NO_ACTION_OR_MODULE = "No action or module specified in the request data.";
    private const ERROR_MASTER_CONTROLLER_USER_AUTHENTICATION_FAILURE = "User authentication failed. USER: %s";
    private const ERROR_CLASS_NOT_FOUND = "Controller class %s does not exist.";
    private const ERROR_CLASS_NOT_INSTANCE = "Controller class %s is not an instance of %s.";
    private const ERROR_MODULE_RETURN_INVALID_STRUCTURE = "Module did not return a correctly structured array.";
    // --- End Constants ---

    public function __construct(array $headerData, array $requestData, bool $inDebugMode)
    {
        $this->systemData = new SystemDataStorage();
        $this->systemData->inDebugMode = $inDebugMode; // Set debug mode.
        $this->systemData->storeData('headerData', $headerData); 
        $this->systemData->storeData('requestData', $requestData); 
        if ($this->systemData->inDebugMode) {
            echo PHP_EOL, "MasterController initialized.", PHP_EOL;
        }
        // Here is where we will check user credentials with the database.
        if(!$this->authenticateUser()) {
            //throw new Exception("User authentication failed.", 1);
        }
        $headerData = $this->systemData->readData('headerData');
        if (array_key_exists('options', $headerData) && !empty($headerData['options'])) 
        {
            $this->options = explode(';', $headerData['options']);
        }
        if (!$this->checkRequestParameters()) {
            throw new Exception("No action specified in the request data.", 1);
        }
    }

    // --- Methods ---
    public function run() : void
    {
        // Find which module we are running, load the controller and run the execute() method.
        // Each module may have the ability to return data that requests
        // a reload of itself, or execution of another module.
        $maxRepeats = self::MAXIMUM_REPEAT_REQUESTS;
        $count = 0;
        do {
            // Run loop for handling kickbacks.
            if (!$this->checkRequestParameters()) {
                // Throw error here.
            }

            $moduleName = $this->systemData->readData('requestData')['module'];
            if (!array_key_exists($moduleName, self::MODULE_CONTROLLERS)) {
                throw new Exception(self::ERROR_MASTER_CONTROLLER_NO_MODULE);
            }
            $moduleControllerClass = self::MODULE_CONTROLLERS[$moduleName];
            if (!class_exists($moduleControllerClass)) {
                error_log(sprintf(self::ERROR_CLASS_NOT_FOUND, $moduleControllerClass));
                throw new Exception("Controller class $moduleControllerClass does not exist.", 1);
            }
            $moduleController = new $moduleControllerClass($this->systemData);
            if (!($moduleController instanceof $moduleControllerClass)) {
                error_log(sprintf(self::ERROR_CLASS_NOT_INSTANCE, $moduleControllerClass, self::MODULE_CONTROLLERS[$moduleName]));
                throw new Exception("Controller class $moduleControllerClass is not an instance of " . self::MODULE_CONTROLLERS[$moduleName], 1);
            }

            if ($this->systemData->inDebugMode) {
                echo "Running module: $moduleName", PHP_EOL;
            }

            $run = $moduleController->execute(); // Execute the module's action based on request parameters.
            
            // First do some validation.
            if (!is_array($run) || !array_key_exists(0, $run) || !array_key_exists(1, $run)) {
                throw new Exception(self::ERROR_MODULE_RETURN_INVALID_STRUCTURE);
            }
            if (!is_int($run[0])) {
                throw new Exception(self::ERROR_MODULE_RETURN_INVALID_STRUCTURE);
            }
            if (!is_bool($run[1])) {
                throw new Exception(self::ERROR_MODULE_RETURN_INVALID_STRUCTURE);
            }

            // If the module returns an array, we check the first element for the return code.
            // The second element is a boolean indicating whether to continue running the module.
            if ($this->systemData->inDebugMode) {
                echo "Module returned: ", print_r($run, true), PHP_EOL;
            }
            if ($run[0] !== 0) {
                $errorMessage = "";
                if(array_key_exists(2, $run)) {
                    $errorMessage = PHP_EOL . $run[2] . PHP_EOL;
                }
                throw new Exception("Module error, returned code " . $run[0] . "." . $errorMessage);
            } elseif ($run[1] === false) {
                break;
            }
        } while (++$count < $maxRepeats);
    }

    private function authenticateUser() : bool
    {
        try {
            $databaseControllerClass = self::MODULE_CONTROLLERS['database'];
            if (!class_exists($databaseControllerClass)) {
                error_log(sprintf(self::ERROR_CLASS_NOT_FOUND, $databaseControllerClass));
                throw new Exception("Controller class $databaseControllerClass does not exist.", 1);
            }
            $controller = new $databaseControllerClass($this->systemData);
            if (!($controller instanceof $databaseControllerClass)) {
                error_log(sprintf(self::ERROR_CLASS_NOT_INSTANCE, $databaseControllerClass, self::MODULE_CONTROLLERS['database']));
                throw new Exception("Controller class $databaseControllerClass is not an instance of " . self::MODULE_CONTROLLERS['database'], 1);
            }
            if (!$controller->verifyOrImportUser()) {
                $user = [
                    'key' => $this->systemData->readData('headerData')['HTTP_X_SECONDLIFE_OWNER_KEY'],
                    'name' => $this->systemData->readData('headerData')['HTTP_X_SECONDLIFE_OWNER_NAME']
                ];
                error_log(sprintf(self::ERROR_MASTER_CONTROLLER_USER_AUTHENTICATION_FAILURE, print_r($user, true)));
                throw new Exception("User authentication failed.", 1);
                return false;
            }
            // Save username and key to system data.
            $this->systemData->storeData('user', [
                'key' => $this->systemData->readData('headerData')['HTTP_X_SECONDLIFE_OWNER_KEY'],
                'name' => $this->systemData->readData('headerData')['HTTP_X_SECONDLIFE_OWNER_NAME']
            ]);
            return true;
        } catch (Exception $e) {
            if ($this->systemData->inDebugMode) {
                echo "Error: ", $e->getMessage(), PHP_EOL;
            }
            throw new Exception("Error initializing database controller.", 1);
        }
        // TODO: implement, need operator class done first
    }

    private function checkRequestParameters() : bool
    {
        $requestParams = $this->systemData->readData('requestData');
        if (array_key_exists('module', $requestParams) && !empty($requestParams['module'])) {
            if (array_key_exists('action', $requestParams) && !empty($requestParams['action'])) {
                return true;
            }
        }
        return false;
    }
    // --- End Methods ---
}
