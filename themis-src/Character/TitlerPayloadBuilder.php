<?php
declare(strict_types=1);

namespace Themis\Character;

use JsonException;

final class TitlerPayloadBuilder
{
	public const PANEL_COUNT = 4;
	public const PANEL_MAX_BYTES = 255;

	/**
	 * @param array<string,mixed> $characterRow
	 * @return array<string,mixed>
	 */
	public function build(array $characterRow): array
	{
		$titler = $this->decodeJsonObject((string)($characterRow['character_titler'] ?? '{}'));
		$options = $this->decodeJsonObject((string)($characterRow['character_options'] ?? '{}'));
		$stats = $this->decodeJsonObject((string)($characterRow['character_stats'] ?? '{}'));

		$storedMode = isset($characterRow['character_mode']) ? (string)$characterRow['character_mode'] : '';
		$mode = $storedMode !== ''
			? TitlerMode::normalize($storedMode)
			: TitlerMode::fromLegacyAfkOoc($options['afk-ooc'] ?? 0);

		$fullText = $this->buildModeText($mode, $titler, $options, $stats);
		$panels = $this->distributeTextAcrossPanels($fullText, self::PANEL_COUNT, self::PANEL_MAX_BYTES);
		$chatterNames = $this->buildChatterNames(
			(string)($characterRow['character_name'] ?? ''),
			$titler
		);

		return [
			'template' => 'titler_layout_v1',
			'mode' => $mode,
			'layout' => [
				'panels' => $panels,
				'panel_count' => self::PANEL_COUNT,
				'max_panel_bytes' => self::PANEL_MAX_BYTES,
			],
			'style' => [
				'color' => (string)($options['color'] ?? '255,255,255'),
				'opacity' => (string)($options['opacity'] ?? '1.0'),
			],
			'meta' => [
				'character_id' => (int)($characterRow['character_id'] ?? 0),
				'character_name' => (string)($characterRow['character_name'] ?? ''),
			],
			'chatter' => $chatterNames,
		];
	}

	/**
	 * @param array<string,mixed> $titler
	 * @return array<string,mixed>
	 */
	private function buildChatterNames(string $characterName, array $titler): array
	{
		$candidates = [];
		$this->appendCandidate($candidates, $characterName);
		$this->appendCandidate($candidates, (string)($titler['@invis@'] ?? ''));
		$this->appendCandidate($candidates, (string)($titler['0'] ?? ''));

		$aliases = [];
		foreach ($candidates as $candidate) {
			$parts = preg_split('/[\n\r,;|\/]+/u', $candidate);
			if (!is_array($parts)) {
				continue;
			}
			foreach ($parts as $part) {
				$clean = trim($part);
				if ($clean === '') {
					continue;
				}
				if ($clean[0] === '<' || $clean[0] === '[') {
					continue;
				}
				if (!in_array($clean, $aliases, true)) {
					$aliases[] = $clean;
				}
			}
		}

		$primary = $aliases[0] ?? ($characterName !== '' ? $characterName : 'Unknown');
		$tokens = $this->tokenizeAliases($aliases);

		return [
			'primary_name' => $primary,
			'aliases' => $aliases,
			'tokens' => $tokens,
		];
	}

	/**
	 * @param array<int,string> $targets
	 */
	private function appendCandidate(array &$targets, string $value): void
	{
		$clean = trim($value);
		if ($clean === '') {
			return;
		}
		if (!in_array($clean, $targets, true)) {
			$targets[] = $clean;
		}
	}

	/**
	 * @param array<int,string> $aliases
	 * @return array<int,string>
	 */
	private function tokenizeAliases(array $aliases): array
	{
		$tokens = [];
		foreach ($aliases as $alias) {
			$parts = preg_split('/[^\p{L}\p{N}\'-]+/u', $alias);
			if (!is_array($parts)) {
				continue;
			}
			foreach ($parts as $part) {
				$token = strtolower(trim($part));
				if ($token === '' || strlen($token) < 2) {
					continue;
				}
				if (!in_array($token, $tokens, true)) {
					$tokens[] = $token;
				}
			}
		}
		return $tokens;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeJsonObject(string $json): array
	{
		if ($json === '') {
			return [];
		}

		try {
			$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
			return is_array($decoded) ? $decoded : [];
		} catch (JsonException) {
			return [];
		}
	}

	/**
	 * @param array<string,mixed> $titler
	 * @param array<string,mixed> $options
	 * @param array<string,mixed> $stats
	 */
	private function buildModeText(string $mode, array $titler, array $options, array $stats): string
	{
		$title = $this->extractTitle($titler);

		if ($mode === TitlerMode::OOC) {
			$message = trim((string)($options['ooc-msg'] ?? ''));
			if ($message === '') {
				$message = 'Out of character';
			}
			return $title !== '' ? ($title . "\n[OOC] " . $message) : ('[OOC] ' . $message);
		}

		if ($mode === TitlerMode::AFK) {
			$message = trim((string)($options['afk-msg'] ?? ''));
			if ($message === '') {
				$message = 'Away from keyboard';
			}
			return $title !== '' ? ($title . "\n[AFK] " . $message) : ('[AFK] ' . $message);
		}

		if ($mode === TitlerMode::COMBAT) {
			$lines = [
				'[COMBAT]',
				'HP: ' . (string)($stats['health'] ?? '?'),
				'STR: ' . (string)($stats['strength'] ?? '?') . '  DEX: ' . (string)($stats['dexterity'] ?? '?'),
				'CON: ' . (string)($stats['constitution'] ?? '?') . '  MAG: ' . (string)($stats['magic'] ?? '?'),
			];
			$combatBody = implode("\n", $lines);
			return $title !== '' ? ($title . "\n" . $combatBody) : $combatBody;
		}

		$normalLines = [];
		foreach ($titler as $key => $value) {
			if (!is_string($key)) {
				continue;
			}
			if ($key === 'template' || $key === '@invis@' || $key === '0') {
				continue;
			}

			$label = trim($key);
			$text = trim((string)$value);
			if ($label === '') {
				continue;
			}

			$normalLines[] = $label . ' ' . $text;
		}

		$body = implode("\n", $normalLines);
		if ($title !== '' && $body !== '') {
			return $title . "\n" . $body;
		}
		if ($title !== '') {
			return $title;
		}
		return $body;
	}

	/**
	 * @param array<string,mixed> $titler
	 */
	private function extractTitle(array $titler): string
	{
		$title = (string)($titler['@invis@'] ?? '');
		if ($title !== '') {
			return $title;
		}
		return (string)($titler['0'] ?? '');
	}

	/**
	 * @return array<int,string>
	 */
	private function distributeTextAcrossPanels(string $text, int $panelCount, int $byteLimit): array
	{
		$panels = array_fill(0, $panelCount, '');
		$lines = explode("\n", $text);
		$panel = 0;
		$lineCount = count($lines);

		for ($i = 0; $i < $lineCount && $panel < $panelCount; $i++) {
			$segment = $lines[$i];
			if ($i < $lineCount - 1) {
				$segment .= "\n";
			}

			while ($segment !== '' && $panel < $panelCount) {
				$room = $byteLimit - strlen($panels[$panel]);
				if ($room <= 0) {
					$panel++;
					continue;
				}

				$piece = $this->fitPrefixByBytes($segment, $room);
				if ($piece === '') {
					$panel++;
					continue;
				}

				$panels[$panel] .= $piece;
				$segment = (string)substr($segment, strlen($piece));
				if ($segment !== '') {
					$panel++;
				}
			}
		}

		if ($panel >= $panelCount && $lineCount > 0) {
			$last = $panelCount - 1;
			$marker = "\n[...]";
			while ($panels[$last] !== '' && strlen($panels[$last] . $marker) > $byteLimit) {
				$panels[$last] = (string)substr($panels[$last], 0, -1);
			}
			if (strlen($panels[$last] . $marker) <= $byteLimit) {
				$panels[$last] .= $marker;
			}
		}

		return $panels;
	}

	private function fitPrefixByBytes(string $text, int $maxBytes): string
	{
		if ($maxBytes <= 0) {
			return '';
		}

		$chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
		if (!is_array($chars)) {
			return '';
		}

		$len = count($chars);
		$out = '';
		for ($i = 0; $i < $len; $i++) {
			$char = $chars[$i];
			$test = $out . $char;
			if (strlen($test) > $maxBytes) {
				return $out;
			}
			$out = $test;
		}

		return $out;
	}
}
