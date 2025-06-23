<?php
namespace Themis\Controller;

use Themis\System\SystemDataStorage;
use Themis\Interface\MasterControllerInterface;
use Themis\Controller\DatabaseController;
use Themis\Controller\CharacterController;
use Themis\Controller\TestController;
use Exception;

// Themis Dependency Injection Container
class ThemisContainer {
    private array $bindings = [];
    private array $instances = [];
    private array $resolving = [];

    public function set(string $name, callable $resolver) {
        // Unbind previous instance if type is identical
        if (isset($this->bindings[$name])) {
            unset($this->instances[$name]);
        }
        $this->bindings[$name] = $resolver;
    }
    public function get(string $name) {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        if (!isset($this->bindings[$name])) {
            throw new Exception("No binding for {$name}");
        }
        // Circular dependency detection
        if (in_array($name, $this->resolving, true)) {
            throw new Exception("Circular dependency detected for: {$name}");
        }
        $this->resolving[] = $name;
        $instance = $this->bindings[$name]($this);
        array_pop($this->resolving);
        $this->instances[$name] = $instance;
        return $instance;
    }
}

class MasterController implements MasterControllerInterface
{
    // --- Properties ---
    private SystemDataStorage $systemData;
    private array $options = [];
    private bool $inDebugMode = false;
    private ThemisContainer $container;
    private const MODULE_CONTROLLERS = [
        'character' => CharacterController::class,
        'database' => DatabaseController::class,
        'test' => TestController::class
    ];
    // --- End Properties ---

    // --- Constants ---
    private const MAXIMUM_REPEAT_REQUESTS = 5;
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
        // Set up the DI container
        $this->container = new ThemisContainer();
        // Register a single SystemDataStorage instance
        $this->container->set(SystemDataStorage::class, function() {
            return new SystemDataStorage();
        });
        // Register controllers dynamically using the MODULE_CONTROLLERS array
        foreach (self::MODULE_CONTROLLERS as $controllerClass) {
            $this->container->set($controllerClass, function($c) use ($controllerClass) {
                return new $controllerClass($c->get(SystemDataStorage::class));
            });
        }
        // Use the shared SystemDataStorage
        $this->systemData = $this->container->get(SystemDataStorage::class);
        $this->systemData->inDebugMode = $inDebugMode;
        $this->systemData->storeData('headerData', $headerData);
        $this->systemData->storeData('requestData', $requestData);
        if ($this->systemData->inDebugMode) {
            echo PHP_EOL, "MasterControllerDI initialized.", PHP_EOL;
        }
        if(!$this->authenticateUser()) {
            throw new Exception("User authentication failed.", 1);
        }
        $headerData = $this->systemData->readData('headerData');
        if (array_key_exists('options', $headerData) && !empty($headerData['options'])) {
            $this->options = explode(';', $headerData['options']);
        }
        if (!$this->checkRequestParameters()) {
            throw new Exception("No action specified in the request data.", 1);
        }
    }

    public function run() : void
    {
        $maxRepeats = self::MAXIMUM_REPEAT_REQUESTS;
        $count = 0;
        do {
            if (!$this->checkRequestParameters()) {
                throw new Exception(self::ERROR_MASTER_CONTROLLER_NO_ACTION_OR_MODULE);
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
            // Get our controller from the container.
            $moduleController = $this->container->get($moduleControllerClass);
            if (!($moduleController instanceof $moduleControllerClass)) {
                error_log(sprintf(self::ERROR_CLASS_NOT_INSTANCE, $moduleControllerClass, self::MODULE_CONTROLLERS[$moduleName]));
                throw new Exception("Controller class $moduleControllerClass is not an instance of " . self::MODULE_CONTROLLERS[$moduleName], 1);
            }
            if ($this->systemData->inDebugMode) {
                echo "Running module: $moduleName", PHP_EOL;
            }
            $run = $moduleController->execute();
            if (!is_array($run) || !array_key_exists(0, $run) || !array_key_exists(1, $run)) {
                throw new Exception(self::ERROR_MODULE_RETURN_INVALID_STRUCTURE);
            }
            if (!is_int($run[0])) {
                throw new Exception(self::ERROR_MODULE_RETURN_INVALID_STRUCTURE);
            }
            if (!is_bool($run[1])) {
                throw new Exception(self::ERROR_MODULE_RETURN_INVALID_STRUCTURE);
            }
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
            if ($this->systemData->inDebugMode) {
                echo "Authenticating user...", PHP_EOL;
            }
            $databaseControllerClass = self::MODULE_CONTROLLERS['database'];
            if (!class_exists($databaseControllerClass)) {
                error_log(sprintf(self::ERROR_CLASS_NOT_FOUND, $databaseControllerClass));
                throw new Exception("Controller class $databaseControllerClass does not exist.", 1);
            }
            $controller = $this->container->get($databaseControllerClass);
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
            $this->systemData->storeData('user', [
                'key' => $this->systemData->readData('headerData')['HTTP_X_SECONDLIFE_OWNER_KEY'],
                'name' => $this->systemData->readData('headerData')['HTTP_X_SECONDLIFE_OWNER_NAME']
            ]);
            if ($this->systemData->inDebugMode) {
                echo "User authenticated successfully.", PHP_EOL;
            }
            return true;
        } catch (Exception $e) {
            if ($this->systemData->inDebugMode) {
                echo "Error: ", $e->getMessage(), PHP_EOL;
            }
            throw new Exception("Error initializing database controller.", 1);
        }
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
}
