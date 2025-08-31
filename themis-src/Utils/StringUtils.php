<?php 
declare(strict_types=1);
namespace Themis\Utils;

use InvalidArgumentException;
use Normalizer;
use Throwable;

/**
 * Class StringUtils
 * @package Themis\Utils
 * 
 * Utility methods for string parsing.
 */
class StringUtils
{

    public static function trim(string $input, ?int $start = null, int $length = -1, ?string $characters = null, bool $trimWhitespace = true): string
    {
        if ($characters === null) {
            $characters = "\t\n\r\0\x0B";
        } else {
            $characters = $characters . "\t\n\r\0\x0B";
        }
        switch ($trimWhitespace) {
            case true:
                $characters = $characters . " ";
                break;
        }
        if ($start !== null) {
            return mb_substr(trim($input, $characters), $start, $length);
        }
        return trim($input, $characters);
    }

    public static function changeCase(bool $toUpper, string $input, int $changeRate = 0, int $start = 0, int $end = -1): string
    {
        if ($changeRate < 0) {
            throw new InvalidArgumentException("Change rate must be a non-negative integer.");
        }

        $length = mb_strlen($input);
        if ($start < 0 || $start > $length) {
            throw new InvalidArgumentException("Start index out of range.");
        }
        if ($end !== -1 && ($end < $start || $end >= $length)) {
            throw new InvalidArgumentException("End index out of range or less than start.");
        }

        // compute selected length
        $selectedLength = ($end === -1) ? ($length - $start) : ($end - $start + 1);
        if ($selectedLength <= 0) {
            return $input; // nothing to do
        }

        $prefix = ($start > 0) ? mb_substr($input, 0, $start) : '';
        $segment = mb_substr($input, $start, $selectedLength);
        $suffix = mb_substr($input, $start + $selectedLength);

        // change entire segment
        if ($changeRate === 0) {
            $segment = $toUpper ? mb_strtoupper($segment) : mb_strtolower($segment);
            return $prefix . $segment . $suffix;
        }

        // split segment into words but keep delimiters (spaces, tabs, newlines) so we preserve original spacing
        $tokens = preg_split('/(\s+)/u', $segment, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($tokens as $tIndex => $token) {
            // whitespace tokens are left as-is
            if ($token === '' || preg_match('/^\s+$/u', $token)) {
                continue;
            }

            $word = $token;
            $newWord = '';

            // If the word contains combining marks or ZWJ, use grapheme clusters so
            // combined characters (accents, emoji sequences) are treated as a unit.
            $hasGrapheme = (bool) preg_match('/\p{M}|\x{200D}/u', $word);
            if ($hasGrapheme) {
                $chars = [];
                // preg_match_all with \X reliably returns grapheme clusters in $matches[0]
                $matches = [];
                $pm = preg_match_all('/\X/u', $word, $matches);
                if ($pm === false || !isset($matches[0]) || count($matches[0]) === 0) {
                    // fallback to per-codepoint handling if preg_match_all failed or returned nothing
                    $wlen = mb_strlen($word);
                    for ($k = 0; $k < $wlen; $k++) {
                        $chars[] = mb_substr($word, $k, 1);
                    }
                } else {
                    $chars = $matches[0];
                }

                $letterIndex = 0;
                foreach ($chars as $ch) {
                    $isLetter = (bool) preg_match('/\p{L}/u', $ch);
                    $shouldChange = false;
                    if ($isLetter) {
                        if ($changeRate === 1) {
                            $shouldChange = ($letterIndex === 0);
                        } else {
                            $shouldChange = ($letterIndex % $changeRate === 0);
                        }
                    }

                    $newWord .= $shouldChange ? ($toUpper ? mb_strtoupper($ch) : mb_strtolower($ch)) : $ch;
                    if ($isLetter) {
                        $letterIndex++;
                    }
                }
            } else {
                $wordLen = mb_strlen($word);
                $letterIndex = 0; // zero-based count of letters seen in this word
                for ($i = 0; $i < $wordLen; $i++) {
                    $ch = mb_substr($word, $i, 1);

                    // is this character a Unicode letter?
                    $isLetter = (bool) preg_match('/\p{L}/u', $ch);

                    $shouldChange = false;
                    if ($isLetter) {
                        if ($changeRate === 1) {
                            // change only the first letter in the word
                            $shouldChange = ($letterIndex === 0);
                        } else {
                            // change every Nth letter, counting letters from zero
                            $shouldChange = ($letterIndex % $changeRate === 0);
                        }
                    }

                    if ($shouldChange) {
                        $newWord .= ($toUpper ? mb_strtoupper($ch) : mb_strtolower($ch));
                    } else {
                        $newWord .= $ch;
                    }

                    if ($isLetter) {
                        $letterIndex++;
                    }
                }
            }

            $tokens[$tIndex] = $newWord;
        }

        $segment = implode('', $tokens);
        $result = $prefix . $segment . $suffix;

        // Try to produce NFC (precomposed) output when possible.
        if (class_exists('Normalizer')) {
            try {
                $norm = Normalizer::normalize($result, Normalizer::FORM_C);
                if ($norm !== false && $norm !== null) {
                    $result = $norm;
                }
            } catch (Throwable $e) {
                // If Normalizer exists but normalization fails for any reason, ignore and return as-is.
            }
        } else {
            // Best-effort precompose common Latin letters if ext-intl is not available.
            $precompose = [
                "\u{0041}\u{0301}" => "\u{00C1}", // A acute
                "\u{0061}\u{0301}" => "\u{00E1}", // a acute
                "\u{0041}\u{0300}" => "\u{00C0}", // A grave
                "\u{0061}\u{0300}" => "\u{00E0}", // a grave
                "\u{0041}\u{0302}" => "\u{00C2}", // A circumflex
                "\u{0061}\u{0302}" => "\u{00E2}", // a circumflex
                "\u{0041}\u{0303}" => "\u{00C3}", // A tilde
                "\u{0061}\u{0303}" => "\u{00E3}", // a tilde
                "\u{0041}\u{0308}" => "\u{00C4}", // A diaeresis
                "\u{0061}\u{0308}" => "\u{00E4}", // a diaeresis
                "\u{0041}\u{030A}" => "\u{00C5}", // A ring
                "\u{0061}\u{030A}" => "\u{00E5}", // a ring

                "\u{0043}\u{0327}" => "\u{00C7}", // C cedilla
                "\u{0063}\u{0327}" => "\u{00E7}", // c cedilla

                "\u{0045}\u{0301}" => "\u{00C9}", // E acute
                "\u{0065}\u{0301}" => "\u{00E9}", // e acute
                "\u{0045}\u{0300}" => "\u{00C8}", // E grave
                "\u{0065}\u{0300}" => "\u{00E8}", // e grave
                "\u{0045}\u{0302}" => "\u{00CA}", // E circumflex
                "\u{0065}\u{0302}" => "\u{00EA}", // e circumflex
                "\u{0045}\u{0308}" => "\u{00CB}", // E diaeresis
                "\u{0065}\u{0308}" => "\u{00EB}", // e diaeresis

                "\u{0049}\u{0301}" => "\u{00CD}", // I acute
                "\u{0069}\u{0301}" => "\u{00ED}", // i acute
                "\u{004F}\u{0301}" => "\u{00D3}", // O acute
                "\u{006F}\u{0301}" => "\u{00F3}", // o acute
                "\u{004F}\u{0302}" => "\u{00D4}", // O circumflex
                "\u{006F}\u{0302}" => "\u{00F4}", // o circumflex
                "\u{004F}\u{0303}" => "\u{00D5}", // O tilde
                "\u{006F}\u{0303}" => "\u{00F5}", // o tilde
                "\u{004F}\u{0308}" => "\u{00D6}", // O diaeresis
                "\u{006F}\u{0308}" => "\u{00F6}", // o diaeresis

                "\u{0055}\u{0301}" => "\u{00DA}", // U acute
                "\u{0075}\u{0301}" => "\u{00FA}", // u acute
                "\u{0055}\u{0300}" => "\u{00D9}", // U grave
                "\u{0075}\u{0300}" => "\u{00F9}", // u grave
                "\u{0055}\u{0302}" => "\u{00DB}", // U circumflex
                "\u{0075}\u{0302}" => "\u{00FB}", // u circumflex
                "\u{0055}\u{0308}" => "\u{00DC}", // U diaeresis
                "\u{0075}\u{0308}" => "\u{00FC}", // u diaeresis

                "\u{004E}\u{0303}" => "\u{00D1}", // N tilde
                "\u{006E}\u{0303}" => "\u{00F1}", // n tilde

                "\u{0059}\u{0301}" => "\u{00DD}", // Y acute
                "\u{0079}\u{0301}" => "\u{00FD}", // y acute
            ];

            // Apply replacements. strtr is byte-safe for UTF-8 sequences here.
            $result = strtr($result, $precompose);
        }

        return $result;
    }

}
