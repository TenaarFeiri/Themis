<?php
    namespace Themis\System;

    use Themis\Interface\SystemDataStorageInterface;
    class SystemDataStorage implements SystemDataStorageInterface
    {
        private array $data = []; // Contains other data points with relevant data. (any type, remember typechecking)
        public bool $inDebugMode = false;
        public function __construct()
        {
            // We'll remove the constructor if we don't need it later.
        }

        public function storeData($key, $value): void
        {
            $this->data[$key] = $value;
        }

        public function getAllData(): array
        {
            if ($this->inDebugMode) {
                echo "Getting all data.", PHP_EOL;
            }
            return $this->data;
        }

        public function readData($key) : mixed
        {
            if ($this->inDebugMode) {
                echo "Reading data: $key", PHP_EOL;
            }
            return $this->data[$key] ?? null; // Return null if key doesn't exist
        }
    }
