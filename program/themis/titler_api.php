<?php
declare(strict_types=1);

namespace Themis;

require_once __DIR__ . '/StrictErrorHandler.php';
require_once __DIR__ . '/Autoloader.php';

use Throwable;
use Themis\Character\Charactermancer;
use Themis\Character\TitlerMode;
use Themis\System\DatabaseOperator;
use Themis\System\SrvToSLComms;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (session_status() !== PHP_SESSION_ACTIVE) {
	if (!session_start()) {
		http_response_code(500);
		echo json_encode(['ok' => false, 'error' => 'Session start failed']);
		exit;
	}
}

function respond(int $status, array $data): void
{
	http_response_code($status);
	echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	exit;
}

function currentCharacterId(): int
{
	if (!isset($_SESSION['player']) || !is_array($_SESSION['player'])) {
		return 0;
	}
	$raw = $_SESSION['player']['player_current_character'] ?? 0;
	return (int)$raw;
}

function currentPlayerUuid(): string
{
	if (!isset($_SESSION['player']) || !is_array($_SESSION['player'])) {
		return '';
	}
	return (string)($_SESSION['player']['player_uuid'] ?? '');
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'payload');

$characterId = currentCharacterId();
$playerUuid = currentPlayerUuid();
if ($characterId <= 0 || $playerUuid === '') {
	respond(401, ['ok' => false, 'error' => 'No active authenticated character context']);
}

try {
	$charactermancer = new Charactermancer();

	if ($action === 'payload' && $method === 'GET') {
		$payload = $charactermancer->buildTitlerPayload($characterId);
		respond(200, [
			'ok' => true,
			'action' => 'payload',
			'character_id' => $characterId,
			'payload' => $payload,
		]);
	}

	if ($action === 'names' && $method === 'GET') {
		$payload = $charactermancer->buildTitlerPayload($characterId);
		$chatter = $payload['chatter'] ?? [];
		respond(200, [
			'ok' => true,
			'action' => 'names',
			'character_id' => $characterId,
			'chatter' => $chatter,
		]);
	}

	if ($action === 'set_mode' && $method === 'POST') {
		$mode = TitlerMode::normalize((string)($_POST['mode'] ?? TitlerMode::NORMAL));
		$setOk = $charactermancer->setCharacterMode($characterId, $mode);
		if ($setOk !== true) {
			respond(500, ['ok' => false, 'error' => 'Failed to set mode']);
		}

		$payload = $charactermancer->buildTitlerPayload($characterId);
		$shouldPush = (string)($_POST['push'] ?? '1') !== '0';
		$pushed = false;
		if ($shouldPush) {
			$db = new DatabaseOperator();
			$db->connectToDatabase();
			$comms = new SrvToSLComms($db);
			$pushed = $comms->sendToPlayer($playerUuid, $payload, [
				'mode' => 'titler',
				'chunk' => true,
				'payload_type' => 'titler',
				'budget' => 1800,
			]);
		}

		respond(200, [
			'ok' => true,
			'action' => 'set_mode',
			'character_id' => $characterId,
			'mode' => $mode,
			'pushed' => $pushed,
			'payload' => $payload,
		]);
	}

	if ($action === 'push' && ($method === 'POST' || $method === 'GET')) {
		$payload = $charactermancer->buildTitlerPayload($characterId);
		$db = new DatabaseOperator();
		$db->connectToDatabase();
		$comms = new SrvToSLComms($db);
		$pushed = $comms->sendToPlayer($playerUuid, $payload, [
			'mode' => 'titler',
			'chunk' => true,
			'payload_type' => 'titler',
			'budget' => 1800,
		]);

		respond(200, [
			'ok' => $pushed,
			'action' => 'push',
			'character_id' => $characterId,
			'pushed' => $pushed,
		]);
	}

	respond(400, ['ok' => false, 'error' => 'Unsupported action/method']);
} catch (Throwable $e) {
	themis_error_log('titler_api error: ' . $e->getMessage());
	respond(500, ['ok' => false, 'error' => 'Internal error']);
}
