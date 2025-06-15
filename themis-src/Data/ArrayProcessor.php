<?php
    namespace Themis\Data;

    use Themis\System\SystemDataStorage;
    use InvalidArgumentException;
    class ArrayProcessor
    {
        // -- Properties --
        private SystemDataStorage $systemData;
        // -- End Properties --

        // -- Constants --
        private const ERROR_WHITELIST_MISMATCH = "Error: Key '%s' is not in the whitelist.";
        private const ERROR_WHITELIST_MISMATCH_COUNT = "Error: Key count mismatch between options and whitelist.";
        // -- End Constants --
        public function __construct(SystemDataStorage $sysData)
        {
            $this->systemData = $sysData;
            if ($this->systemData->inDebugMode) {
                echo "ArrayProcessor initialized.", PHP_EOL;
            }
        }

        public function generateWildcards(array $array, string $wildcard = "?") : array
        {
            $wildcardArray = [];
            $count = count($array);
            if ($count === 0) {
                return [];
            }
            return array_fill(0, $count, $wildcard);
        }

        public function isAssociative(array $arr): bool 
        {
            return array_keys($arr) !== range(0, count($arr) - 1);
        }

        public function isInArray(array $array, $value) : bool
        {
            $index = array_search($value, $array, true);
            if ($index === false) {
                return false;
            }
            return true;
        }
        
    }

