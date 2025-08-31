<?php
require_once __DIR__ . '/ThemisErrorLog.php';

/**
 * Registers an autoloader function for classes in the 'Themis' namespace.
 *
 * @param string $srcDir   The source directory containing class files. Defaults to 'themis-src'.
 * @param int    $dirDepth The number of parent directories to traverse from the current directory to locate the base directory. Defaults to 2.
 */
function setAutoloader(string $srcDir = 'themis-src', int $dirDepth = 2): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    // Normalize srcDir to avoid accidental double slashes
    $srcDir = rtrim($srcDir, '/');

    spl_autoload_register(function ($class) use ($srcDir, $dirDepth) {
        $prefix = 'Themis\\';
        $baseDir = dirname(__DIR__, $dirDepth) . '/' . $srcDir . '/';

        // If the computed base directory doesn't exist, log an informational message.
        // By design this autoloader is placed alongside Init.php; moving it changes dirname(__DIR__, $dirDepth).
        if (!is_dir($baseDir)) {
            themis_error_log(sprintf(
                "Autoloader: computed baseDir '%s' does not exist. Autoloader.php is expected to remain in the same directory as Init.php; move it back or adjust dirDepth/srcDir.",
                $baseDir
            ));
        }

        // Check if the class uses the namespace prefix
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return; // not our namespace
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        // Prefer readable check to ensure file can actually be required.
        if (is_readable($file)) {
            require_once $file;
            return;
        }

        // Class file not found or unreadable: log and terminate, this is a fatal error for the application.
        themis_error_log(sprintf(
            "Autoloader: Class '%s' not found or not readable at '%s' (prefix='%s', baseDir='%s'). Shutting down.",
            $class,
            $file,
            $prefix,
            $baseDir
        ));
        exit(1);
    });

    $registered = true;
}

// Auto-register the autoloader when this file is included.
// This makes using the library simpler: including Autoloader.php is sufficient.
setAutoloader();

