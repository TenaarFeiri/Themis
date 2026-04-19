<?php
declare(strict_types=1);

namespace Themis;

require_once __DIR__ . '/StrictErrorHandler.php';
require_once __DIR__ . '/Autoloader.php';

use Throwable;
use Themis\Combat\CombatService;
use Themis\System\DatabaseOperator;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function realtime_respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function realtime_get_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function realtime_normalize_uuid(string $value): string
{
    $uuid = strtolower(trim($value));
    if ($uuid === '') {
        return '';
    }
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid)) {
        return '';
    }
    return $uuid;
}

function realtime_expected_token(): string
{
    $env = getenv('THEMIS_REALTIME_TOKEN');
    if (is_string($env)) {
        $env = trim($env);
    } else {
        $env = '';
    }
    return $env;
}

function realtime_request_token(): string
{
    $token = trim((string)($_SERVER['HTTP_X_THEMIS_REALTIME_TOKEN'] ?? ''));
    if ($token !== '') {
        return $token;
    }
    $auth = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return '';
}

$expectedToken = realtime_expected_token();
if ($expectedToken === '') {
    realtime_respond(500, ['ok' => false, 'error' => 'Realtime token is not configured']);
}

$requestToken = realtime_request_token();
if ($requestToken === '' || !hash_equals($expectedToken, $requestToken)) {
    realtime_respond(401, ['ok' => false, 'error' => 'Unauthorized']);
}

$body = realtime_get_json_body();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? ($body['action'] ?? 'state'));
$action = trim($action);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $db = new DatabaseOperator();
    $db->connectToDatabase();
    $combat = new CombatService($db);

    if ($action === 'tick' && ($method === 'POST' || $method === 'GET')) {
        $result = $combat->tick();
        realtime_respond(200, $result);
    }

    $actor = realtime_normalize_uuid((string)($_POST['actor_player_uuid'] ?? $_GET['actor_player_uuid'] ?? ($body['actor_player_uuid'] ?? '')));
    if ($actor === '') {
        realtime_respond(400, ['ok' => false, 'error' => 'actor_player_uuid is required']);
    }

    if ($action === 'state' && $method === 'GET') {
        $instanceId = isset($_GET['instance_id']) ? (int)$_GET['instance_id'] : null;
        $result = $combat->getState($actor, $instanceId);
        realtime_respond(200, $result);
    }

    if ($action === 'state' && $method === 'POST') {
        $instanceId = isset($_POST['instance_id']) ? (int)$_POST['instance_id'] : (isset($body['instance_id']) ? (int)$body['instance_id'] : null);
        $result = $combat->getState($actor, $instanceId);
        realtime_respond(200, $result);
    }

    if ($action === 'targets' && $method === 'GET') {
        $result = $combat->listTargets($actor);
        realtime_respond(200, $result);
    }

    if ($action === 'pending_invites' && ($method === 'GET' || $method === 'POST')) {
        $result = $combat->listPendingInvites($actor);
        realtime_respond(200, $result);
    }

    if ($action === 'challenge' && $method === 'POST') {
        $targetUuid = realtime_normalize_uuid((string)($_POST['target_player_uuid'] ?? ($body['target_player_uuid'] ?? '')));
        if ($targetUuid === '') {
            realtime_respond(400, ['ok' => false, 'error' => 'target_player_uuid is required']);
        }
        $turnSeconds = (int)($_POST['turn_seconds'] ?? ($body['turn_seconds'] ?? 90));
        $result = $combat->challenge($actor, $targetUuid, $turnSeconds);
        realtime_respond($result['ok'] ? 200 : 400, $result);
    }

    if ($action === 'join' && $method === 'POST') {
        $hostUuidRaw = (string)($_POST['host_player_uuid'] ?? ($body['host_player_uuid'] ?? ''));
        $hostUuid = realtime_normalize_uuid($hostUuidRaw);
        $result = $combat->joinNearbyHost($actor, $hostUuid !== '' ? $hostUuid : null);
        realtime_respond($result['ok'] ? 200 : 400, $result);
    }

    if ($action === 'respond_invite' && $method === 'POST') {
        $instanceId = (int)($_POST['instance_id'] ?? ($body['instance_id'] ?? 0));
        if ($instanceId <= 0) {
            realtime_respond(400, ['ok' => false, 'error' => 'instance_id is required']);
        }
        $acceptRaw = strtolower(trim((string)($_POST['accept'] ?? ($body['accept'] ?? '1'))));
        $accept = in_array($acceptRaw, ['1', 'true', 'yes', 'accept'], true);
        $result = $combat->respondToInvite($actor, $instanceId, $accept);
        realtime_respond($result['ok'] ? 200 : 400, $result);
    }

    if ($action === 'radar_sync' && $method === 'POST') {
        $radar = count($body) > 0 ? $body : $_POST;
        $result = $combat->syncPresence($actor, is_array($radar) ? $radar : []);
        realtime_respond($result['ok'] ? 200 : 400, $result);
    }

    if ($action === 'submit_action' && $method === 'POST') {
        $instanceId = (int)($_POST['instance_id'] ?? ($body['instance_id'] ?? 0));
        if ($instanceId <= 0) {
            realtime_respond(400, ['ok' => false, 'error' => 'instance_id is required']);
        }

        $actionType = trim((string)($_POST['action_type'] ?? ($body['action_type'] ?? 'wait')));
        $targetUuid = realtime_normalize_uuid((string)($_POST['target_player_uuid'] ?? ($body['target_player_uuid'] ?? '')));

        $payload = $_POST['payload'] ?? ($body['payload'] ?? []);
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($payload)) {
            $payload = [];
        }

        $result = $combat->submitAction($actor, $instanceId, $actionType, $targetUuid !== '' ? $targetUuid : null, $payload);
        realtime_respond($result['ok'] ? 200 : 400, $result);
    }

    realtime_respond(400, ['ok' => false, 'error' => 'Unsupported action/method']);
} catch (Throwable $e) {
    themis_error_log('combat_realtime_api error: ' . $e->getMessage());
    realtime_respond(500, ['ok' => false, 'error' => 'Internal error']);
}
