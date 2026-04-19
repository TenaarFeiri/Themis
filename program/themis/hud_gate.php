<?php
declare(strict_types=1);
namespace Themis;

require_once __DIR__ . '/Autoloader.php'; // Also includes error handler.


use Themis\Auth\HudGateService;
use Themis\Auth\HudGateServiceException;
use Themis\Auth\SessionManager;
use Themis\Auth\SessionManagerException;
use Themis\System\DatabaseOperator;

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
    private HudGateService $hudGateService;
    private SessionManager $sessionManager;

    public function __construct() {
        $dbOperator = new DatabaseOperator();
        $dbOperator->connectToDatabase();
        $this->hudGateService = new HudGateService($dbOperator);
        $this->sessionManager = new SessionManager();
    }

    public function init(): void {
        try {
            $this->sessionManager->resetForNewAuthentication();
        } catch (SessionManagerException $e) {
            throw new HudGateException($e->getMessage(), 0, $e);
        }

        // We should have received a login token in GET. Validate with strict charset.
        $token = (string)(filter_input(INPUT_GET, 'token', FILTER_UNSAFE_RAW) ?? '');
        $token = trim($token);
        if ($token === '') {
            throw new HudGateException('No token provided');
        }
        try {
            $playerInformation = $this->hudGateService->authenticateFromToken($token);
        } catch (HudGateServiceException $e) {
            throw new HudGateException($e->getMessage(), 0, $e);
        }

        try {
            $this->sessionManager->startAuthenticatedSession($playerInformation);
        } catch (SessionManagerException $e) {
            throw new HudGateException($e->getMessage(), 0, $e);
        }
    }
}

try {
    $hudGate = new HudGate();
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
