<?php
declare(strict_types=1);

namespace Themis\System;

final class PayloadBudgetEstimator
{
	public const HARD_LIMIT_BYTES = 2048;
	public const DEFAULT_OPERATIONAL_LIMIT_BYTES = 1800;

	public function toJson(array $payload): ?string
	{
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			return null;
		}

		return $json;
	}

	public function bytes(string $json): int
	{
		return strlen($json);
	}

	/**
	 * @return array{ok:bool,bytes:int,limit:int,hardLimit:int,error:?string}
	 */
	public function evaluate(string $json, int $limit = self::DEFAULT_OPERATIONAL_LIMIT_BYTES): array
	{
		$bytes = $this->bytes($json);
		if ($limit < 1 || $limit > self::HARD_LIMIT_BYTES) {
			$limit = self::DEFAULT_OPERATIONAL_LIMIT_BYTES;
		}

		if ($bytes > self::HARD_LIMIT_BYTES) {
			return [
				'ok' => false,
				'bytes' => $bytes,
				'limit' => $limit,
				'hardLimit' => self::HARD_LIMIT_BYTES,
				'error' => 'Payload exceeds hard SL limit.',
			];
		}

		if ($bytes > $limit) {
			return [
				'ok' => false,
				'bytes' => $bytes,
				'limit' => $limit,
				'hardLimit' => self::HARD_LIMIT_BYTES,
				'error' => 'Payload exceeds operational budget.',
			];
		}

		return [
			'ok' => true,
			'bytes' => $bytes,
			'limit' => $limit,
			'hardLimit' => self::HARD_LIMIT_BYTES,
			'error' => null,
		];
	}
}
