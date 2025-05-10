<?php
    namespace Themis\Data;

    use InvalidArgumentException;
    class ArrayProcessor
    {
        // -- Properties --
        private bool $inDebugMode = false;
        // -- End Properties --

        // -- Constants --
        private const ERROR_WHITELIST_MISMATCH = "Error: Key '%s' is not in the whitelist.";
        private const ERROR_WHITELIST_MISMATCH_COUNT = "Error: Key count mismatch between options and whitelist.";
        // -- End Constants --
        public function __construct(bool $inDebugMode)
        {
            $this->inDebugMode = $inDebugMode;
            if ($inDebugMode) {
                echo "ArrayProcessor initialized.", PHP_EOL;
            }
        }

        public function generateWildcards(array $array, string $wildcard = "?") : string
        {
            $wildcardArray = [];
            $count = count($array);
            if ($count === 0) {
                return [];
            }
            return implode(",", array_fill(0, $count, $wildcard));
        }
        
    }