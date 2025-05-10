<?php
    namespace Themis\Core;

    // --- Includes ---
    use Themis\Controller\MasterController;
    use Exception;
    // --- End Includes ---

    // --- Headers ---
    header('Content-Type: text/plain; charset=utf-8');
    // --- End Headers ---

    // --- Constants ---
    // System constants
    const DEBUG = true; // Set to false in production
    const DIR_SCALE = 2; // Directory levels to go up for base path
    const MAXIMUM_AMOUNT_OF_LOG_FILES = 5;
    $srcPath = dirname(__DIR__, DIR_SCALE) . '/themis-src/';
    $logPath = dirname(__DIR__, DIR_SCALE) . '/themis-logs/';
    const ERROR_LOG_DIRECTORY = 'Errors';
    const ERROR_LOG_FILE_NAME = 'error_log';
    const MAXIMUM_ERROR_LOG_FILE_SIZE = 200; // In MB
    const MODULE_NAMES = [ // Map error codes to module names
        0 => 'Unknown',
        1 => 'MasterController',
        2 => 'Database'
    ];

    // Error Log Messages
    const ERROR_MESSAGE_GENERIC = "An error occurred. Code: %s";
    const ERROR_MESSAGE_DIR_NOT_WRITEABLE = "Error log directory is not writeable: %s. Please check permissions.";
    const ERROR_MESSAGE_DIR_FAILED_TO_CREATE = "Failed to create error log directory: %s. Please check permissions.";
    const ERROR_MESSAGE_ERROR_LOG_DELETED_OLDEST = "Deleted oldest error log file: %s";
    const ERROR_MESSAGE_ERROR_LOG_FAILED_TO_DELETE = "Failed to delete oldest error log file: %s";
    const ERROR_MESSAGE_ERROR_LOG_FAILED_TO_GLOB = "Failed to glob log files pattern: %s";
    const ERROR_MESSAGE_ERROR_LOG_ROTATED = "Rotated error log file: %s";
    const ERROR_MESSAGE_ERROR_LOG_FAILED_TO_ROTATE = "Failed to rotate error log file: %s";
    const ERROR_MESSAGE_HEADER_MISSING_REQUIRED = "Missing required header(s): %s";
    const ERROR_MESSAGE_HEADER_INVALID_USER_AGENT = "Invalid User-Agent header. Expected 'Second Life LSL/' prefix. Received: %s";
    const ERROR_MESSAGE_AUTOLOADER_FILE_NOT_FOUND = "Autoloader: Could not load file for class: %s - Expected path: %s";
    const ERROR_MESSAGE_AUTH_INVALID_TOKEN = "Invalid token: %s";
    // --- End Constants ---

    // --- Error Logging Setup ---
    error_reporting(E_ALL);
    ini_set('display_errors', DEBUG ? 1 : 0);
    ini_set('display_startup_errors', DEBUG ? 1 : 0);
    ini_set('log_errors', 1);

    $errorLogDirectory = $logPath . ERROR_LOG_DIRECTORY;

    // Ensure the directory exists (attempt creation on all OS)
    if (!is_dir($errorLogDirectory)) {
        // Attempt to create it recursively
        // Mode 0775 is ignored on Windows, applied on Linux/Unix
        if (!mkdir($errorLogDirectory, 0775, true) && !is_dir($errorLogDirectory)) {
            // This failure is critical on any OS
            trigger_error(sprintf(ERROR_MESSAGE_DIR_FAILED_TO_CREATE, $errorLogDirectory), E_USER_ERROR); // Use E_USER_ERROR
        }
    }

    // Perform strict writability check only on non-Windows
    if (PHP_OS_FAMILY !== 'Windows') {
        if (!is_writable($errorLogDirectory)) {
            trigger_error(sprintf(ERROR_MESSAGE_DIR_NOT_WRITEABLE, $errorLogDirectory), E_USER_ERROR);  // Use E_USER_ERROR
        }
    }

    $errorLogFilePath = $errorLogDirectory . '/' . ERROR_LOG_FILE_NAME . '.log';
    ini_set('error_log', $errorLogFilePath);

    // --- Log Rotation ---
    // Log file pattern (base name + optional timestamp)
    $logFilesPattern = $errorLogDirectory . '/' . ERROR_LOG_FILE_NAME . '*.log';
    $files = glob($logFilesPattern);

    if ($files !== false && !empty($files)) {
        $fileCount = count($files);

        // Delete oldest logs if limit exceeded
        if ($fileCount >= MAXIMUM_AMOUNT_OF_LOG_FILES) {
            // Sort files by modification time (oldest first)
            usort($files, function ($a, $b) {
                return filemtime($a) <=> filemtime($b);
            });

            // Calculate number of files to delete (ensure at least one remains if limit is 1)
            $filesToDeleteCount = max(0, $fileCount - MAXIMUM_AMOUNT_OF_LOG_FILES);


            for ($i = 0; $i < $filesToDeleteCount; $i++) {
                if (unlink($files[$i])) {
                    error_log(sprintf(ERROR_MESSAGE_ERROR_LOG_DELETED_OLDEST, basename($files[$i])));
                } else {
                    error_log(sprintf(ERROR_MESSAGE_ERROR_LOG_FAILED_TO_DELETE, basename($files[$i])));
                }
            }
        }
    } elseif ($files === false) {
        error_log(sprintf(ERROR_MESSAGE_ERROR_LOG_FAILED_TO_GLOB, $logFilesPattern));
    }

    // Rotate the main log file if it exceeds the size limit
    $maxFileSize = MAXIMUM_ERROR_LOG_FILE_SIZE * 1024 * 1024; // Convert MB to Bytes
    clearstatcache(true, $errorLogFilePath);
    if (file_exists($errorLogFilePath) && filesize($errorLogFilePath) > $maxFileSize) {
        $timestamp = date('Y-m-d_H-i-s');
        $newFileName = ERROR_LOG_FILE_NAME . '_' . $timestamp . '.log';
        $newFilePath = $errorLogDirectory . '/' . $newFileName;
        if (rename($errorLogFilePath, $newFilePath)) {
            error_log(sprintf(ERROR_MESSAGE_ERROR_LOG_ROTATED, basename($newFilePath)));
            // Create new empty log file
            touch($errorLogFilePath);
            if (PHP_OS_FAMILY !== "Windows") {
                chmod($errorLogFilePath, 0664); // Set permissions for the new log file
            }
        } else {
            error_log(sprintf(ERROR_MESSAGE_ERROR_LOG_FAILED_TO_ROTATE, basename($errorLogFilePath)));
        }
    }
    // --- End Error Logging Setup ---

    spl_autoload_register(function ($class) use ($srcPath) {
        $prefix = 'Themis\\';
        $baseDir = $srcPath;

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
            error_log(sprintf(ERROR_MESSAGE_AUTOLOADER_FILE_NOT_FOUND, $class, $file));
        }
    });
     // --- Debug Overrides ---
     // Headers and other system overrides for debugging/development.
     // Lets us actually access the system from the browser.
     if (DEBUG) {
        $themisSecret = getenv('THEMIS_SECRET') ?: '001'; // Default value for testing
        $debugHeaders = [
            'HTTP_X_SECONDLIFE_SHARD' => 'Production', // SL shard will always be production or this MUST fail.
            'HTTP_X_SECONDLIFE_REGION' => 'Starfall Roleplay',
            'HTTP_USER_AGENT' => 'Second Life LSL/srv.version (http://secondlife.com)',
            'HTTP_X_SECONDLIFE_OWNER_KEY' => '5675c8a0-430b-4281-af36-60734935fad3',
            'HTTP_X_SECONDLIFE_OWNER_NAME' => 'Tenaar Feiri',
            'HTTP_X_THEMIS_TOKEN' => hash_hmac(
                'sha256', 
                '5675c8a0-430b-4281-af36-60734935fad3', 
                $themisSecret
            )
        ];
        foreach($debugHeaders as $key => $value) {
            $_SERVER[$key] = $value;
        }
     }
    $requestActions = DEBUG ? $_GET : $_POST; // Use GET for debugging, POST for production.
     // --- End Debug Overrides ---
    
    // --- Authentication ---
    $requiredHeaders = [
        'HTTP_X_SECONDLIFE_SHARD',
        'HTTP_X_SECONDLIFE_REGION',
        'HTTP_USER_AGENT',
        'HTTP_X_SECONDLIFE_OWNER_KEY',
        'HTTP_X_SECONDLIFE_OWNER_NAME',
        'HTTP_X_THEMIS_TOKEN'
    ];
    $headersMissing = [];
    foreach ($requiredHeaders as $header) {
        if (!isset($_SERVER[$header])) {
            // If any of the headers are missing, we fail.
            $headersMissing[] = $header;
        }
    }
    if(isset($_SERVER['HTTP_USER_AGENT']) && !str_starts_with($_SERVER['HTTP_USER_AGENT'], 'Second Life LSL/')) {
        // If the user agent is not from Second Life, we fail.
        $headersMissing[] = 'HTTP_USER_AGENT';
        error_log(sprintf(ERROR_MESSAGE_HEADER_INVALID_USER_AGENT, $_SERVER['HTTP_USER_AGENT']));
    }
    if ($headersMissing) {
        http_response_code(403); // Forbidden
        error_log(sprintf(ERROR_MESSAGE_HEADER_MISSING_REQUIRED, implode(", ", $headersMissing)));
        exit("Missing required headers.");
    } else {
        // All required headers are present, proceed with authentication.
        $themisSecret = getenv('THEMIS_SECRET') ?: '001'; // Default value for testing
        $expectedToken = hash_hmac(
            'sha256', 
            $_SERVER['HTTP_X_SECONDLIFE_OWNER_KEY'], 
            $themisSecret
        );
        if ($_SERVER['HTTP_X_THEMIS_TOKEN'] !== $expectedToken) {
            // Token does not match, fail authentication.
            http_response_code(403); // Forbidden
            error_log(sprintf(ERROR_MESSAGE_AUTH_INVALID_TOKEN, $_SERVER['HTTP_X_THEMIS_TOKEN']));
            exit("Invalid token.");
        }
    }
    // --- End Authentication ---

    // --- Execution ---
    try {
        $masterController = new MasterController($_SERVER, $requestActions, DEBUG);
    } catch (Exception $e) {
        error_log(MODULE_NAMES[$e->getCode()] . ": " . $e->getMessage());
        $httpCode = http_response_code();
        if ($httpCode !== 200 && $httpCode !== 201) {
            http_response_code(500); // Internal Server Error
        }
        exit(sprintf(ERROR_MESSAGE_GENERIC, $e->getCode()));
    }
