<?php
declare(strict_types=1);
namespace Themis;

header('Content-Type: text/plain; charset=utf-8');

use Themis\System\ThemisContainer;
use Themis\System\DataContainer;
use Themis\System\DatabaseOperator;
use Themis\System\DatabaseConnector;

use Themis\Character\Character;

use Themis\User\UserValidation;
use Themis\User\UserLegacyImport;

use Exception;

class Init {
    public static bool $debug = true;
    private string $version = '0.0.1';
    private string $name = 'Themis RP System';
    private ThemisContainer $container;
    private DataContainer $dataContainer;
    const SYSTEM_CLASSES = [
        'userValidate' => UserValidation::class,
        'databaseOperator' => DatabaseOperator::class,
        'userLegacyImport' => UserLegacyImport::class,
        'character' => Character::class
    ];

    const ALLOWED_CLASSES = [
        // Whitelist of which classes the SL side can interact with.
        // i.e. POST contains "&class=something", 'something' must be a valid SYSTEM_CLASS,
        // AND be in this list.
        // One example can be 'class=character' which would be a class that handles character data.
        // After that, it will also define a method=something, which the class will resolve.
        "character"
    ];

    public function __construct() {
        // Report all errors, except notices, if debug is enabled
        if (self::$debug) {
            error_reporting(E_ALL & ~E_NOTICE);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            echo "Themis RP System initialized with debug mode enabled.\n";
        }

        $this->container = new ThemisContainer();
        $this->dataContainer = new DataContainer();
        // Set up the container with default bindings.
        $this->container->set('dataContainer', function () {
            return $this->dataContainer;
        });
        foreach (self::SYSTEM_CLASSES as $name => $class) {
            $this->container->set($name, function () use ($class) {
                return new $class($this->container);
            });
        }

        // Now give the DataContainer the debug flag and version and headers.
        $this->dataContainer->set('debug', self::$debug);
        $this->dataContainer->set('version', $this->version);
        switch (self::$debug) {
            case true:
                $themisSecret = getenv('THEMIS_SECRET') ?: '001'; // Default value for testing
                $debugHeaders = [
                    'HTTP_X_SECONDLIFE_SHARD' => 'Production', // SL shard will always be production or this MUST fail.
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
                $this->dataContainer->set('headers', getallheaders());
                break;
        }

        try {
            $this->validateUser();
            $post = self::$debug ? $_GET : $_POST; // If debug mode, use GET data (we're using browser URLs)

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
                throw new Exception("No {$postOrGet} data received.");
            }

            $this->dataContainer->set('module', $post['module'] ?? null);
            $this->dataContainer->set('cmd', $post['cmd'] ?? null);

            if (!in_array($this->dataContainer->get('module'), self::ALLOWED_CLASSES)) {
                throw new Exception("Unknown module.");
            }

            $module = $this->container->get($this->dataContainer->get('module'));
            $class = self::SYSTEM_CLASSES[$this->dataContainer->get('module')];
            if (!$module instanceof $class) {
                throw new Exception("Module is not an instance of {$class}.");
            }

        } catch (Exception $e) {
            themis_error_log($e->__toString());
            http_response_code(400); // Bad Request
            exit();
        }

        http_response_code(200);
    }

    private function validateUser(): void {

        $userValidation = $this->container->get('userValidate');
        // Isolate all Second Life headers.
        $headers = $this->dataContainer->get('headers');
        $slHeaders = array_filter($headers, function ($key) {
            return str_starts_with(strtolower($key), 'http_x_secondlife_');
        }, ARRAY_FILTER_USE_KEY);

        // Kick that to our data container as a whole array.
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

    public static function setAutoloader(string $srcDir = 'themis-src', int $dirDepth = 2): void {
        spl_autoload_register(function ($class) use ($srcDir, $dirDepth) {
            $prefix = 'Themis\\';
            $baseDir = dirname(__DIR__, $dirDepth) . '/' . $srcDir . '/';

            // Check if the class uses the namespace prefix
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return; // Not our class, pass to next autoloader
            }

            // Get the relative class name
            $relativeClass = substr($class, $len);
            // Replace namespace separators with directory separators
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            // If the file exists, require it
            if (file_exists($file)) {
                require_once $file;
            } else {
                // Log if the file isn't found
                themis_error_log(sprintf("Autoloader: Class '%s' not found in '%s'", $class, $file));
            }
        });
    }

    public function checkAccess($user) {
        // Access control logic
    }
}
Init::setAutoloader();

// Set PHP error log location
ini_set('error_log', __DIR__ . '/../themis-errlogs/themis.log');

// Make an error wrapper.
function themis_error_log(string $message): void {
    if (empty($message)) {
        return;
    }
    $logDir = __DIR__ . '/../themis-errlogs/';
    $logFile = $logDir . 'themis.log';
    $maxSize = 250 * 1024 * 1024; // 250 MB

    // Ensure log directory exists
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    // Rotate if needed
    if (file_exists($logFile) && filesize($logFile) >= $maxSize) {
        $timestamp = date('Ymd_His');
        $rotated = $logDir . "themis_{$timestamp}.log";
        // Delete previous rotated log if it exists
        foreach (glob($logDir . "themis_*.log") as $old) {
            @unlink($old);
        }
        // Rename current log
        rename($logFile, $rotated);
        // Create a new log file
        touch($logFile);
        chmod($logFile, 0666);
    }

    // Prepend date/time to message
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    error_log($entry, 3, $logFile);
    if (Init::$debug) {
        echo PHP_EOL, "ERROR (debug msg): ", PHP_EOL, $entry, PHP_EOL;
    }
}

try {
    $init = new Init(); // Create an instance of Init
} catch (Throwable $e) {
    themis_error_log($e->getMessage()); 
    echo "An error occurred during initialization. Please check the logs.";
    exit();
}


