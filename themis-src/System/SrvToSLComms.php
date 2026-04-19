<?php
declare(strict_types=1);

namespace Themis\System;
require_once __DIR__ . '/../../html/themis/StrictErrorHandler.php';
require_once __DIR__ . '/../../html/themis/Autoloader.php';

use Themis\System\DatabaseOperator;
use Exception;
use Throwable;

/**
 * Server -> SecondLife communications helper.
 *
 * Responsibilities:
 * - Lookup a player's titler/hud URL in the `players` table.
 * - POST JSON payloads to the selected URL with a short timeout and one retry.
 * - Inspect the response for expected keywords when provided.
 * - Never throw on failure; log and return false.
 */
class SrvToSLComms {
	private DatabaseOperator $dbOperator;
	private PayloadBudgetEstimator $budgetEstimator;
	private PayloadChunker $payloadChunker;

	public function __construct(DatabaseOperator $dbOperator, ?PayloadBudgetEstimator $budgetEstimator = null, ?PayloadChunker $payloadChunker = null) {
		$this->dbOperator = $dbOperator;
		$this->budgetEstimator = $budgetEstimator ?? new PayloadBudgetEstimator();
		$this->payloadChunker = $payloadChunker ?? new PayloadChunker($this->budgetEstimator);
	}

	/**
	 * Send data to a player's titler or HUD URL.
	 *
	 * @param string $playerUuid Player UUID to lookup.
	 * @param array $payload Data to send (will be JSON-encoded).
	 * @param array $opts Optional params:
	 *                    - mode: 'hud'|'titler' (default 'hud')
	 *                    - expect: array of strings to look for in response body (any match = success)
	 *                    - chunk: bool; if true, always use chunk envelopes and require per-chunk ACK
	 *                    - payload_type: string message type for chunk envelopes (default 'generic')
	 * @return bool True if delivered (met HTTP 200 and keyword checks), false otherwise.
	 */
	public function sendToPlayer(string $playerUuid, array $payload, array $opts = []): bool {
		// Lookup player URLs
		try {
			$rows = $this->dbOperator->select(
				select: ['player_titler_url', 'player_hud_url'],
				from: 'players',
				where: ['player_uuid'],
				equals: [$playerUuid]
			);
		} catch (Throwable $e) {
			themis_error_log('SrvToSLComms: DB error fetching player urls: ' . $e->getMessage());
			return false;
		}

		if (empty($rows) || !isset($rows[0])) {
			themis_error_log("SrvToSLComms: no player row for uuid {$playerUuid}");
			return false;
		}

		$row = $rows[0];

		$mode = strtolower($opts['mode'] ?? 'hud');
		$urlKey = $mode === 'titler' ? 'player_titler_url' : 'player_hud_url';
		$url = $row[$urlKey] ?? null;

		if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
			themis_error_log("SrvToSLComms: invalid or missing {$urlKey} for player {$playerUuid}");
			return false;
		}

		$expect = $opts['expect'] ?? [];
		if (!is_array($expect)) {
			$expect = [$expect];
		}

		$json = $this->budgetEstimator->toJson($payload);
		if ($json === null) {
			themis_error_log('SrvToSLComms: failed to json_encode payload');
			return false;
		}

		$timeout = (int)($opts['timeout'] ?? 3);
		$connectTimeout = (int)($opts['connect_timeout'] ?? 3);
		$chunkEnabled = (($opts['chunk'] ?? false) === true);
		$payloadType = (string)($opts['payload_type'] ?? 'generic');
		$budget = (int)($opts['budget'] ?? PayloadBudgetEstimator::DEFAULT_OPERATIONAL_LIMIT_BYTES);

		if ($chunkEnabled) {
			return $this->sendChunkedJson($url, $json, $playerUuid, $payloadType, $budget, $timeout, $connectTimeout);
		}

		$budgetReport = $this->budgetEstimator->evaluate($json, $budget);
		if ($budgetReport['ok'] !== true) {
			themis_error_log(
				sprintf(
					'SrvToSLComms: payload over budget for player %s (%d bytes, budget=%d, hard_limit=%d): %s',
					$playerUuid,
					$budgetReport['bytes'],
					$budgetReport['limit'],
					$budgetReport['hardLimit'],
					(string)$budgetReport['error']
				)
			);
			return false;
		}

		$attempts = 2;
		for ($i = 0; $i < $attempts; $i++) {
			$result = $this->postJson($url, $json, $timeout, $connectTimeout);
			if (!$result['ok']) {
				themis_error_log("SrvToSLComms: transport error (attempt " . ($i+1) . ") for player {$playerUuid}: " . $result['error']);
				// retry once
				continue;
			}

			$response = $result['response'];
			$httpCode = $result['httpCode'];

			if ($httpCode !== 200) {
				themis_error_log("SrvToSLComms: unexpected HTTP code {$httpCode} (attempt " . ($i+1) . ") for player {$playerUuid}");
				continue;
			}

			// If no expectation provided, consider any 200 with a body a success.
			if (empty($expect)) {
				return true;
			}

			if (!is_string($response)) {
				themis_error_log("SrvToSLComms: empty response body (attempt " . ($i+1) . ") for player {$playerUuid}");
				continue;
			}

			foreach ($expect as $needle) {
				if ($needle !== '' && strpos($response, (string)$needle) !== false) {
					return true;
				}
			}

			// no expected keyword found; log and retry once
			themis_error_log("SrvToSLComms: expected keywords not found in response (attempt " . ($i+1) . ") for player {$playerUuid}");
		}

		themis_error_log("SrvToSLComms: failed to deliver payload to {$url} for player {$playerUuid} after {$attempts} attempts");
		return false;
	}

	private function sendChunkedJson(string $url, string $json, string $playerUuid, string $payloadType, int $budget, int $timeout, int $connectTimeout): bool
	{
		$chunks = $this->payloadChunker->chunkJson($json, $payloadType, $budget);
		if (count($chunks) === 0) {
			themis_error_log("SrvToSLComms: chunker failed to generate chunks for player {$playerUuid}");
			return false;
		}

		$messageId = (string)$chunks[0]['id'];
		foreach ($chunks as $chunk) {
			$chunkJson = $this->budgetEstimator->toJson($chunk);
			if ($chunkJson === null) {
				themis_error_log("SrvToSLComms: failed to encode chunk for player {$playerUuid}");
				return false;
			}

			$result = $this->postJson($url, $chunkJson, $timeout, $connectTimeout);
			if (!$result['ok'] || $result['httpCode'] !== 200 || !is_string($result['response'])) {
				themis_error_log(
					sprintf(
						'SrvToSLComms: chunk send failed for player %s (id=%s i=%d n=%d http=%d error=%s)',
						$playerUuid,
						(string)$chunk['id'],
						(int)$chunk['i'],
						(int)$chunk['n'],
						(int)$result['httpCode'],
						(string)($result['error'] ?? 'none')
					)
				);
				return false;
			}

			if (!$this->isValidChunkAck($result['response'], $messageId, (int)$chunk['i'])) {
				themis_error_log(
					sprintf(
						'SrvToSLComms: invalid chunk ACK for player %s (id=%s i=%d response=%s)',
						$playerUuid,
						$messageId,
						(int)$chunk['i'],
						trim($result['response'])
					)
				);
				return false;
			}
		}

		return true;
	}

	private function isValidChunkAck(string $response, string $messageId, int $chunkIndex): bool
	{
		$trimmed = trim($response);
		if ($trimmed === '') {
			return false;
		}

		$decoded = json_decode($trimmed, true);
		if (is_array($decoded)) {
			$id = (string)($decoded['id'] ?? '');
			$i = (int)($decoded['i'] ?? -1);
			$ok = $decoded['ok'] ?? null;
			$t = strtolower((string)($decoded['t'] ?? ''));

			$okValue = ($ok === true || $ok === 1 || $ok === '1' || strtolower((string)$ok) === 'true');
			if ($id === $messageId && $i === $chunkIndex && ($t === 'ack' || isset($decoded['ack'])) && $okValue) {
				return true;
			}
		}

		if (preg_match('/^ACK\s+([A-Za-z0-9]+)\s+(\d+)$/i', $trimmed, $m) === 1) {
			return $m[1] === $messageId && (int)$m[2] === $chunkIndex;
		}

		return false;
	}

	/**
	 * @return array{ok:bool,httpCode:int,response:?string,error:?string}
	 */
	private function postJson(string $url, string $json, int $timeout, int $connectTimeout): array
	{
		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			if ($ch === false) {
				return [
					'ok' => false,
					'httpCode' => 0,
					'response' => null,
					'error' => 'curl_init failed',
				];
			}

			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $json,
				CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
				CURLOPT_TIMEOUT => $timeout,
				CURLOPT_CONNECTTIMEOUT => $connectTimeout,
			]);

			$response = curl_exec($ch);
			$errno = curl_errno($ch);
			$error = curl_error($ch);
			$httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

			// No explicit curl_close here: CurlHandle is auto-cleaned by PHP GC at request end.
			unset($ch);

			if ($errno !== 0) {
				return [
					'ok' => false,
					'httpCode' => $httpCode,
					'response' => null,
					'error' => $error !== '' ? $error : ('curl errno ' . $errno),
				];
			}

			return [
				'ok' => true,
				'httpCode' => $httpCode,
				'response' => is_string($response) ? $response : null,
				'error' => null,
			];
		}

		$headers = [
			'Content-Type: application/json',
			'Content-Length: ' . strlen($json),
		];

		$context = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => $json,
				'timeout' => max($timeout, $connectTimeout),
				'ignore_errors' => true,
			],
		]);

		$response = @file_get_contents($url, false, $context);
		$httpCode = 0;
		if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches) === 1) {
			$httpCode = (int)$matches[1];
		}

		if ($response === false) {
			$error = error_get_last();
			return [
				'ok' => false,
				'httpCode' => $httpCode,
				'response' => null,
				'error' => $error['message'] ?? 'stream transport failed',
			];
		}

		return [
			'ok' => true,
			'httpCode' => $httpCode,
			'response' => $response,
			'error' => null,
		];
	}
}

