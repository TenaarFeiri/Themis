<?php
declare(strict_types=1);
// StrictErrorHandler.php
// Drop-in strict error/exception handler for Themis applications.
// On include this file registers handlers that log the error context and exit(1).

require_once __DIR__ . '/ThemisErrorLog.php';

// Allow disabling via environment variable for tests/dev if needed (set THEMIS_STRICT=0)
$__themis_strict_enabled = getenv('THEMIS_STRICT');
if ($__themis_strict_enabled !== false && ($__themis_strict_enabled === '0' || strtolower($__themis_strict_enabled) === 'false')) {
    return; // registration disabled by env
}

function themis_strict_exception_handler(Throwable $e): void
{
    // Build compact request/session context
    $req = $_SERVER ?? [];
    $context = [
        'method' => $req['REQUEST_METHOD'] ?? null,
        'uri'    => $req['REQUEST_URI'] ?? null,
        'remote' => $req['REMOTE_ADDR'] ?? null,
        'host'   => $req['SERVER_NAME'] ?? null,
    ];

    $sessionId = null;
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        $sessionId = session_id();
    } elseif (function_exists('session_id')) {
        $sid = session_id();
        $sessionId = $sid !== '' ? $sid : null;
    }

    $message = sprintf(
        "Uncaught exception: %s in %s:%d\nCode: %s\nTrace:\n%s\nContext: %s\nSession: %s",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        (string)$e->getCode(),
        $e->getTraceAsString(),
        json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $sessionId ?? ''
    );

    // Log the full message reliably
    try {
        themis_error_log($message, false);
    } catch (Throwable $_) {
        // if logging fails, continue to ensure we still send a response
    }

    // Send a clean HTTP 500 response if possible, then exit strongly.
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8', true, 500);
        echo 'Internal Server Error';
    }

    exit(1);
}

function themis_strict_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    // Convert PHP errors to exceptions so the exception handler can handle them uniformly.
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

function themis_strict_shutdown_handler(): void
{
    $err = error_get_last();
    if ($err !== null) {
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (in_array($err['type'] ?? 0, $fatal, true)) {
            $message = sprintf(
                "Fatal error: %s in %s:%d",
                $err['message'] ?? 'unknown',
                $err['file'] ?? 'unknown',
                $err['line'] ?? 0
            );
            try {
                themis_error_log($message, false);
            } catch (Throwable $_) {
            }
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8', true, 500);
                echo 'Internal Server Error';
            }
            exit(1);
        }
    }
}

// Register handlers
set_exception_handler('themis_strict_exception_handler');
set_error_handler('themis_strict_error_handler');
register_shutdown_function('themis_strict_shutdown_handler');

// end of StrictErrorHandler.php
