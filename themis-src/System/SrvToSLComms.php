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

	public function __construct(DatabaseOperator $dbOperator) {
		$this->dbOperator = $dbOperator;
	}

	/**
	 * Send data to a player's titler or HUD URL.
	 *
	 * @param string $playerUuid Player UUID to lookup.
	 * @param array $payload Data to send (will be JSON-encoded).
	 * @param array $opts Optional params:
	 *                    - mode: 'hud'|'titler' (default 'hud')
	 *                    - expect: array of strings to look for in response body (any match = success)
	 * @return bool True if delivered (met HTTP 200 and keyword checks), false otherwise.
	 */
	public function sendToPlayer(string $playerUuid, array $payload, array $opts = []): bool {
		// Lookup player URLs
		try {
			$rows = $this->dbOperator->select(
				select: ['titler_url', 'hud_url'],
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
		$urlKey = $mode === 'titler' ? 'titler_url' : 'hud_url';
		$url = $row[$urlKey] ?? null;

		if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
			themis_error_log("SrvToSLComms: invalid or missing {$urlKey} for player {$playerUuid}");
			return false;
		}

		$expect = $opts['expect'] ?? [];
		if (!is_array($expect)) {
			$expect = [$expect];
		}

		$json = json_encode($payload);
		if ($json === false) {
			themis_error_log('SrvToSLComms: failed to json_encode payload');
			return false;
		}

		$attempts = 2;
		for ($i = 0; $i < $attempts; $i++) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
			curl_setopt($ch, CURLOPT_TIMEOUT, 3);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

			$response = curl_exec($ch);
			$errno = curl_errno($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			curl_close($ch);

			if ($errno !== 0) {
				themis_error_log("SrvToSLComms: curl error (attempt " . ($i+1) . ") for player {$playerUuid}: {$errno}");
				// retry once
				continue;
			}

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
}

