<?php
declare(strict_types=1);
namespace Themis;

header('Content-Type: text/plain; charset=utf-8');

// Essential requires
require_once 'Autoloader.php';

// System Classes
use Themis\System\ThemisContainer;
use Themis\System\DataContainer;
use Themis\System\DatabaseOperator;
use Themis\System\DatabaseConnector;

// Character Classes
use Themis\Character\Character;

// User Classes
use Themis\User\UserValidation;
use Themis\User\UserLegacyImport;

// Utilities
use Exception;
use Throwable;
use Themis\System\Dialogs;
use ReflectionMethod; // Inspects object methods, great for making sure they exist & are as you expect.

// Custom Exceptions
use Themis\Utils\Exceptions\BadRequestException;

class Init {
    // Properties
    public static bool $debug = true;
    private string $version = '0.0.1';
    private string $name = 'Themis RP System';
    private ThemisContainer $container;
    private DataContainer $dataContainer;

    // System Class Bindings
    const SYSTEM_CLASSES = [
        'userValidate' => UserValidation::class,
        'databaseOperator' => DatabaseOperator::class,
        'userLegacyImport' => UserLegacyImport::class,
        'character' => Character::class,
        'dialogs' => Dialogs::class
    ];

    // Allowed Classes for SL Interaction
    const ALLOWED_CLASSES = [
        "character",
        "dialogs"
    ];

    // Constructor
    public function __construct() {

        // Debug Setup
        if (self::$debug) {
            error_reporting(E_ALL & ~E_NOTICE);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            echo "Themis RP System initialized with debug mode enabled.\n";
        }

        // Dependency Injection
        $this->container = new ThemisContainer();
        $this->dataContainer = new DataContainer();
        $this->container->set('dataContainer', function () {
            return $this->dataContainer;
        });
        foreach (self::SYSTEM_CLASSES as $name => $class) {
            $this->container->set($name, function () use ($class) {
                return new $class($this->container);
            });
        }

        // DataContainer Setup
        $this->dataContainer->set('debug', self::$debug);
        $this->dataContainer->set('version', $this->version);

        // Header Setup
        switch (self::$debug) {
            case true:
                $themisSecret = getenv('THEMIS_SECRET') ?: '001';
                $debugHeaders = [
                    'HTTP_X_SECONDLIFE_SHARD' => 'Production',
                    'HTTP_X_SECONDLIFE_REGION' => 'Starfall Roleplay',
                    'HTTP_USER_AGENT' => 'Second Life LSL/srv.version (http://secondlife.com)',
                    'HTTP_X_SECONDLIFE_OWNER_KEY' => '59ee7fce-5203-4d8c-b4db-12cb50ad2c10',
                    'HTTP_X_SECONDLIFE_OWNER_NAME' => 'Symphicat Resident',
                    'HTTP_X_THEMIS_TOKEN' => hash_hmac(
                        'sha256',
                        '59ee7fce-5203-4d8c-b4db-12cb50ad2c10',
                        $themisSecret
                    )
                ];
                $this->dataContainer->set('headers', $debugHeaders);
                $this->dataContainer->set('themisSecret', $themisSecret);
                break;
            default:
                $this->dataContainer->set('headers', $this->fetchAllHeaders());
                break;
        }

        // Request Handling & Validation
        $this->validateUser();
        $post = self::$debug ? $_GET : $_POST;

        switch (self::$debug) {
            case true:
                $postOrGet = "GET";
                echo PHP_EOL, "Debug mode: Using GET data.", PHP_EOL;
                print_r($post);
                break;
            default:
                $postOrGet = "POST";
        }

        if (empty($post)) {
            throw new BadRequestException("No {$postOrGet} data received.");
        }

        $this->dataContainer->set('module', $post['module'] ?? null);
        $this->dataContainer->set('cmd', $post['cmd'] ?? null);

        if (!in_array($this->dataContainer->get('module'), self::ALLOWED_CLASSES)) {
            throw new BadRequestException("Unknown module.", $this->dataContainer->get('module'));
        }

        $module = (object)$this->container->get($this->dataContainer->get('module'));
        $class = self::SYSTEM_CLASSES[$this->dataContainer->get('module')];
        if (!$module instanceof $class) {
            throw new BadRequestException("Module is not an instance of {$class}.", (string)$module);
        }

        // Check if the loaded module contains a public run() method
        if (!method_exists($module, 'run')) {
            throw new BadRequestException("Module is missing run() method.");
        }
        // is_callable will be false for non-public methods; ensures run() is publicly invokable
        if (!is_callable([$module, 'run'])) {
            throw new Exception("Module's run() method is not public.");
        }

        // Execute the module's run() method
        $module->run();
    }


    // User Validation
    private function validateUser(): void {
        $userValidation = $this->container->get('userValidate');

        // Isolate all Second Life headers
        $headers = $this->dataContainer->get('headers');
        $slHeaders = array_filter($headers, function ($key) {
            return str_starts_with(strtolower($key), 'http_x_secondlife_');
        }, ARRAY_FILTER_USE_KEY);

        $this->dataContainer->set('slHeaders', $slHeaders);

        $userExists = $userValidation->checkUserExists();
        if (!$userExists) {
            throw new Exception("Could not verify user, somehow we ended up here. This should not be possible; a user would have been registered during validation.");
        }

        switch (self::$debug) {
            case true:
                echo PHP_EOL, "User exists, proceeding with validation.", PHP_EOL;
                break;
        }
    }

    // Access Control Placeholder
    public function checkAccess($user) {
        // Access control logic
    }

    /**
     * Fetch all request headers with a fallback for SAPIs that don't provide getallheaders().
     * Returns headers keyed by their original header name.
     */
    private function fetchAllHeaders(): array {
        $headers = [];
        // First, copy any HTTP_ and CONTENT_ keys directly from $_SERVER (these already match the desired format)
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') || in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[$key] = $value;
            }
        }

        // If getallheaders is available, normalize its output into HTTP_ uppercase underscore form
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $norm = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
                if (!isset($headers[$norm])) {
                    $headers[$norm] = $value;
                }
            }
        }

        return $headers;
    }
}

// Error Log Setup
ini_set('error_log', __DIR__ . '/../themis-errlogs/themis.log');


// Main Init
try {
    ob_start();
    $init = new Init(); // Initialise app. Constructor will perform all execution logic.
    http_response_code(200); // If we make it this far, send a 200 code.
    ob_end_flush();
} catch (Throwable $e) {
    themis_error_log($e->__toString(), Init::$debug);

    // Check if any exception in the chain is a BadRequestException
    $badRequest = false;
    $current = $e;
    while ($current) {
        if ($current instanceof BadRequestException) {
            $badRequest = true;
            break;
        }
        $current = $current->getPrevious();
    }

    switch (Init::$debug) {
        case false:
            http_response_code($badRequest ? 400 : 500);
            break;

        case true:
            http_response_code(200);
            var_dump($e);
            break;
    }
    
    echo "An error occurred during initialization. Please check the logs.";
    switch (Init::$debug) {
        case false:
            http_response_code($badRequest ? 400 : 500);
            ob_end_clean();
            break;

        case true:
            http_response_code(200);
            ob_end_flush();
            break;
    }
    exit();
}

