<?php
declare(strict_types=1);

namespace Themis\System;

final class PayloadChunker
{
	public function __construct(private readonly PayloadBudgetEstimator $budgetEstimator)
	{
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function chunkJson(
		string $json,
		string $payloadType,
		int $budget = PayloadBudgetEstimator::DEFAULT_OPERATIONAL_LIMIT_BYTES,
		?string $messageId = null
	): array {
		if ($budget < 1 || $budget > PayloadBudgetEstimator::HARD_LIMIT_BYTES) {
			$budget = PayloadBudgetEstimator::DEFAULT_OPERATIONAL_LIMIT_BYTES;
		}

		$id = $messageId !== null && $messageId !== '' ? $messageId : bin2hex(random_bytes(6));
		$jsonBytes = strlen($json);
		$offset = 0;
		$index = 1;
		$rawChunkSize = $this->maxRawChunkSize($id, $payloadType, $budget);
		if ($rawChunkSize < 1) {
			return [];
		}

		$chunks = [];
		while ($offset < $jsonBytes) {
			$slice = substr($json, $offset, $rawChunkSize);
			if ($slice === '') {
				break;
			}

			$chunks[] = [
				't' => 'chunk',
				'id' => $id,
				'pt' => $payloadType,
				'i' => $index,
				'n' => 0,
				'ack' => 1,
				'enc' => 'b64',
				'd' => base64_encode($slice),
			];

			$offset += strlen($slice);
			$index++;
		}

		$total = count($chunks);
		for ($i = 0; $i < $total; $i++) {
			$chunks[$i]['n'] = $total;
		}

		return $chunks;
	}

	private function maxRawChunkSize(string $id, string $payloadType, int $budget): int
	{
		$low = 1;
		$high = max(1, $budget);
		$best = 0;

		while ($low <= $high) {
			$mid = intdiv($low + $high, 2);
			$probeData = base64_encode(str_repeat('a', $mid));
			$envelope = [
				't' => 'chunk',
				'id' => $id,
				'pt' => $payloadType,
				'i' => 1,
				'n' => 999,
				'ack' => 1,
				'enc' => 'b64',
				'd' => $probeData,
			];

			$json = $this->budgetEstimator->toJson($envelope);
			if ($json === null) {
				$high = $mid - 1;
				continue;
			}

			$report = $this->budgetEstimator->evaluate($json, $budget);
			if ($report['ok']) {
				$best = $mid;
				$low = $mid + 1;
			} else {
				$high = $mid - 1;
			}
		}

		return $best;
	}
}
