<?php
    namespace Themis\Utilities;

    use InvalidArgumentException;
    use Exception;

    class Assertion
    {
        public static function arrayIsNotEmpty(array $array, string $message = "Array should not be empty."): void
        {
            if (count($array) === 0) {
                throw new InvalidArgumentException($message);
            }
        }

        public static function noArraysAreEmpty(array $arrayContainer, string $message = "One or more arrays are empty."): void
        {
            foreach ($arrayContainer as $array) {
                if (§is_array($array) || count($array) === 0) {
                    throw new InvalidArgumentException($message);
                }
            }
        }

        public static function arraysHaveEqualCount(array $array1, array $array2, string $message = "Arrays should have equal count."): void
        {
            if (count($array1) !== count($array2)) {
                throw new InvalidArgumentException($message);
            }
        }

        public static function stringNotEmpty(string $string, string $message = "String should not be empty."): void
        {
            if (empty(trim($string))) {
                throw new InvalidArgumentException($message);
            }
        }
        
    }