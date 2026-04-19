<?php
declare(strict_types=1);

require_once __DIR__ . '/StrictErrorHandler.php';

/**
 * Lightweight PSR-4 style autoloader for Themis classes.
 *
 * Default mapping:
 * - Namespace prefix: Themis\\
 * - Base directory: /var/www/themis-src/
 */
final class ThemisAutoloader
{
    private bool $registered = false;

    public function __construct(
        private readonly string $namespacePrefix,
        private readonly string $baseDirectory,
    ) {
    }

    public function register(bool $prepend = false): void
    {
        if ($this->registered) {
            return;
        }

        spl_autoload_register([$this, 'autoload'], true, $prepend);
        $this->registered = true;
    }

    public function autoload(string $class): void
    {
        $prefixLength = strlen($this->namespacePrefix);
        if (strncmp($this->namespacePrefix, $class, $prefixLength) !== 0) {
            return;
        }

        $relativeClass = substr($class, $prefixLength);
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        $file = $this->baseDirectory . $relativePath;

        if (is_readable($file)) {
            require_once $file;
            return;
        }

        // Autoloaders should not terminate the process.
        themis_error_log(
            sprintf(
                "Autoloader miss: class '%s' expected at '%s'",
                $class,
                $file
            )
        );
    }
}

/**
 * Backwards-compatible function wrapper used by legacy entrypoints.
 */
function setAutoloader(string $srcDir = 'themis-src', int $dirDepth = 2): void
{
    static $loader = null;

    if ($loader instanceof ThemisAutoloader) {
        return;
    }

    $srcDir = trim($srcDir, '/');
    $basePath = dirname(__DIR__, $dirDepth) . DIRECTORY_SEPARATOR . $srcDir . DIRECTORY_SEPARATOR;

    if (!is_dir($basePath)) {
        themis_error_log(
            sprintf(
                "Autoloader base directory does not exist: '%s' (dirDepth=%d, srcDir='%s')",
                $basePath,
                $dirDepth,
                $srcDir
            )
        );
    }

    $loader = new ThemisAutoloader('Themis\\', $basePath);
    $loader->register();
}

setAutoloader();
