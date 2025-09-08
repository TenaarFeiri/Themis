<?php
declare(strict_types=1);
namespace Themis\Utils;

use JsonException;

class JsonUtils {

    private const MAX_DEPTH = 512;

    public static function isValidJson(string $string): bool {
        try {
            json_decode($string, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException $e) {
            return false;
        }
    }

    public static function decode(string $json, bool $assoc = true, int $options = 0, int $depth = self::MAX_DEPTH): mixed {
        if ($depth > self::MAX_DEPTH) {
            throw new JsonException("Depth exceeds maximum of " . self::MAX_DEPTH);
        }
        return json_decode(json: $json, associative: $assoc, flags: $options | JSON_THROW_ON_ERROR, depth: $depth);
    }

    public static function encode(mixed $data, int $options = 0, int $depth = self::MAX_DEPTH): string {
        if ($depth > self::MAX_DEPTH) {
            throw new JsonException("Depth exceeds maximum of " . self::MAX_DEPTH);
        }
        return json_encode(value: $data, flags: $options | JSON_THROW_ON_ERROR, depth: $depth);
    }

}