<?php
    namespace Themis\Data;

    use Exception;
    class StringProcessor
    {
        // --- Properties ---

        // --- End Properties ---

        public function removeSpecialCharacters(string $string, bool $removeWhitespace = false, bool $allowExceptions = false, ?string $exceptions = null): string
        {
            $string = trim($string);
            if ($removeWhitespace) {
                $string = preg_replace('/\s+/', '', $string);
            }
            if ($allowExceptions && $exceptions !== null) {
                // Allow exceptions
                $string = preg_replace('/[^a-zA-Z0-9\s' . preg_quote($exceptions, '/') . ']/', '', $string);
            } else {
                // Remove all special characters
                $string = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
            }
            return $string;
        }

        public function addSqlBackticks(string $string): string
        {
            // Add backticks to the string, escaping any existing backticks
            return "`" . str_replace("`", "``", $string) . "`";
        }
    }
