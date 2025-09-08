<?php
declare(strict_types=1);
namespace Themis;

require_once __DIR__ . '/Autoloader.php'; // Also includes error handler.


use Themis\System\ThemisContainer;
use Themis\System\DatabaseOperator;
use Themis\System\DataContainer;
use Themis\User\UserValidation;

use Exception;
use Throwable;
/**
 * HUD Gate Initialization
 * Handles the initial reception of the end user, checks the provided token and optionally requests a pin
 * if the token was manually regenerated.
 * 
 * Once everything is verified, pull user information from the database, set a session, store user info in the session
 * and redirect to the HUD interface.
 */

class HudGateException extends Exception {}
class HudGate {
    private bool $debug = true;
    private ThemisContainer $container;
    private DataContainer $dataContainer;

    private const SYSTEM_CLASSES = [
        'dbOperator' => DatabaseOperator::class,
        'userValidation' => UserValidation::class,
    ];

    public function __construct(ThemisContainer $container) {
        $this->container = $container;
        $this->dataContainer = new DataContainer();
        $this->container->set('dataContainer', function () {
            return $this->dataContainer;
        });
        foreach (self::SYSTEM_CLASSES as $name => $class) {
            $this->container->set($name, function () use ($class) {
                return new $class($this->container);
            });
        }
        $this->dataContainer->set('debug', $this->debug);
    }

    public function registerOrValidateUser(): void {
        // User registration or validation logic goes here
    }

    public function init(): void {
        // Defensive session teardown before creating a new session.
        // session functions require headers; fail fast if output already sent.
        if (headers_sent($file, $line)) {
            throw new HudGateException("Cannot manage session: headers already sent in $file:$line");
        }

        // Ensure a session is available to clean up; start one if needed.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!session_start()) {
                throw new HudGateException('Failed to start session for cleanup');
            }
        }

        // Clear session variables deterministically, flush and destroy server store, remove cookie.
        $_SESSION = [];
        session_unset(); // harmless after assignment, kept for clarity
        session_write_close();

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!session_destroy()) {
                // Non-fatal but log for diagnostics
                themis_error_log('session_destroy() returned false during cleanup');
            }
        }

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? false
            );
        }

        // We should have received a login token in GET. Sanitize and validate.
        $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($token)) {
            throw new HudGateException('No token provided');
        }

        $this->container->set('dataContainer', function () {
            return $this->dataContainer;
        });
        foreach (self::SYSTEM_CLASSES as $name => $class) {
            $this->container->set($name, function () use ($class) {
                return new $class($this->container);
            });
        }

        $dbOperator = $this->container->get('dbOperator');
        $dbOperator->connectToDatabase();

    // Intentionally begin a transaction and hold the row lock acquired by the
    // SELECT ... FOR UPDATE below until commit. We'll only have a maximum of 100
    // concurrent users, so it should be impossible to create a scenario where any
    // one of them is waiting for a lock to be released.
    // Hoard that shit like a greedy dragon!
    $dbOperator->beginTransaction();
        try {
            $findToken = $dbOperator->select(
                select: ['*'],
                from: 'launch_tokens',
                where: ['token', 'used'],
                equals: [$token, 0],
                for: 'UPDATE'
            );

            if (!$findToken || count($findToken) === 0) {
                throw new HudGateException('Invalid or already used token.');
            }
            $findToken = $findToken[0];
            $sessionId = $findToken['session_id'];

            // Mark launch token as used.
            $dbOperator->update(
                table: 'launch_tokens',
                columns: ['used'],
                values: [($this->debug ? 0 : 1)],
                where: ['token', 'used'],
                equals: [$findToken['token'], 0]
            );

            $session = $dbOperator->select(
                select: ['*'],
                from: 'sessions',
                where: ['session_id', 'revoked'],
                equals: [$sessionId, 0]
            );

            if (!$session || count($session) === 0) {
            throw new HudGateException('Session not found or revoked.');
            }

            $session = $session[0];
            $uuid = $session['uuid'];

            $dbOperator->update(
                table: 'sessions',
                columns: ['expires'],
                values: [date('Y-m-d H:i:s', strtotime('+24 hours'))],
                where: ['id'],
                equals: [$session['id']]
            );
            $dbOperator->update(
                table: 'sessions',
                columns: ['revoked'],
                values: [1],
                where: ['uuid'],
                equals: [$uuid],
                notWhere: ['id'],
                notEquals: [$session['id']]
            );

            $playerInformation = $dbOperator->select(
                select: ['*'],
                from: 'players',
                where: ['player_uuid'],
                equals: [$uuid]
            );
            $dbOperator->commitTransaction();
        } catch (Throwable $e) {
            $dbOperator->rollbackTransaction();
            throw $e;
        }

        if (!$playerInformation) {
            throw new HudGateException('Player information not found.');
        }

        // Configure secure session cookie parameters before starting the new session.
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new HudGateException('Failed to start session');
        }

        if (!session_regenerate_id(true)) {
            themis_error_log('session_regenerate_id() returned false');
        }

        $playerInformation = $playerInformation[0];

        $_SESSION['player'] = $playerInformation; // Store player information in session

        session_write_close();
    }
}

try {
    $hudGate = new HudGate(new ThemisContainer());
    $hudGate->init();
    // Let's go to the hud interface from here.
    header('Location: hud_interface.php');
    exit;
} catch (Throwable $e) {
    // Handle exceptions (e.g., log the error, display a user-friendly message)
    themis_error_log($e->getMessage());
    http_response_code(500);
    echo 'An error occurred during initialization.';
}
