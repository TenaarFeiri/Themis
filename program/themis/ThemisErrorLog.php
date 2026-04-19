<?php
/**
 * ThemisErrorLog.php
 *
 * Provides a centralized error logging function for the Themis RP System.
 * Handles log file rotation, directory creation, and optional debug output.
 */

/**
 * Logs error messages to the Themis error log file, with rotation and optional debug output.
 *
 * - Ensures the log directory exists.
 * - Rotates the log file if it exceeds the maximum size (250 MB).
 * - Logs the message with a timestamp.
 * - Optionally outputs the message to stdout if debug is enabled.
 *
 * @param string $message The error message to log. If empty, nothing is logged.
 * @param bool $debug If true, also outputs the error message to stdout.
 * @return void
 */
function themis_error_log(string $message, bool $debug = false, string $logname = 'themis.log'): void {
    if (empty($message)) {
        return;
    }
    $logDir = __DIR__ . '/../themis-errlogs/';
    $logFile = $logDir . $logname;
    $maxSize = 250 * 1024 * 1024; // 250 MB

    // Ensure log directory exists
    if (!is_dir($logDir)) {
        if (!@mkdir($logDir, 0777, true) && !is_dir($logDir)) {
            @error_log('[Themis] ' . $message);
            if ($debug) {
                echo PHP_EOL, "ERROR (debug msg): ", PHP_EOL, $message, PHP_EOL;
            }
            return;
        }
    }

    if (!is_writable($logDir)) {
        @error_log('[Themis] ' . $message);
        if ($debug) {
            echo PHP_EOL, "ERROR (debug msg): ", PHP_EOL, $message, PHP_EOL;
        }
        return;
    }

    // Rotate if needed
    if (file_exists($logFile) && filesize($logFile) >= $maxSize) {
        $timestamp = date('Ymd_His');
        $rotated = $logDir . $logname . "_{$timestamp}.log";
        foreach (glob($logDir . "{$logname}_*.log") as $old) {
            @unlink($old);
        }
        @rename($logFile, $rotated);
        @touch($logFile);
        @chmod($logFile, 0666);
    }

    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    $ok = @error_log($entry, 3, $logFile);
    if ($ok === false) {
        @error_log('[Themis] ' . $message);
    }
    if ($debug) {
        echo PHP_EOL, "ERROR (debug msg): ", PHP_EOL, $entry, PHP_EOL;
    }
}
