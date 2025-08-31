<?php
declare(strict_types=1);
namespace Themis\Utils;

// Exceptions
use Exception;
use InvalidArgumentException;
use JsonException;

class ArrayUtils
{
    /**
     * Recursively applies a callback to every value in a (potentially nested) array.
     *
     * Example usage:
     * ```php
     * $input = [
     *     'a' => 'foo',
     *     'b' => ['c' => 'bar', 'd' => ['e' => 'baz']]
     * ];
     * $output = ArrayUtils::arrayMapRecursive(fn($v) => strtoupper($v), $input);
     * // $output = ['a' => 'FOO', 'b' => ['c' => 'BAR', 'd' => ['e' => 'BAZ']]]
     * ```
     *
     * @param callable $callback Function to apply to each value.
     * @param array $array Input array (may be nested).
     * @return array Array with callback applied to all values.
     */
    public static function arrayMapRecursive(callable $callback, array $array): array {
        return array_map(
            fn($item) => is_array(value: $item) ? self::arrayMapRecursive(callback: $callback, array: $item) : $callback($item),
            $array
        );
    }

    public static function isAssoc(array $arr): bool {
        if (array() === $arr) return false;
        return array_keys(array: $arr) !== range(start: 0, end: count($arr) - 1);
    }

    public static function exceedsMaxEntries(array $arr, int $max): bool {
        return count(value: $arr) > $max;
    }

    public static function decodeJson(string $json, bool $assoc = true): mixed {
        $data = json_decode(json: $json, associative: $assoc, flags: JSON_THROW_ON_ERROR);
        return $data;
    }

    public static function encodeJson(mixed $data, int $options = 0, int $depth = 512): string {
        return json_encode(value: $data, flags: $options | JSON_THROW_ON_ERROR, depth: $depth);
    }
}
