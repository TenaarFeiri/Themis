<?php
declare(strict_types=1);

namespace Themis\Character;

final class TitlerMode
{
	public const NORMAL = 'normal';
	public const OOC = 'ooc';
	public const AFK = 'afk';
	public const COMBAT = 'combat';

	/**
	 * @return array<int,string>
	 */
	public static function all(): array
	{
		return [self::NORMAL, self::OOC, self::AFK, self::COMBAT];
	}

	public static function normalize(string $mode): string
	{
		$mode = strtolower(trim($mode));
		if (in_array($mode, self::all(), true)) {
			return $mode;
		}
		return self::NORMAL;
	}

	/**
	 * Legacy fallback from character_options["afk-ooc"]:
	 * 0 => normal, 1 => ooc, 2 => afk.
	 */
	public static function fromLegacyAfkOoc(mixed $value): string
	{
		$code = is_numeric($value) ? (int)$value : 0;
		if ($code === 1) {
			return self::OOC;
		}
		if ($code === 2) {
			return self::AFK;
		}
		return self::NORMAL;
	}
}
