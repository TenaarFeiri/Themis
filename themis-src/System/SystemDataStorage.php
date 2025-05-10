<?php
    namespace Themis\System;

    use Themis\Interface\SystemDataStorageInterface;
    class SystemDataStorage implements SystemDataStorageInterface
    {
        private array $data = []; // Contains other data points with relevant data. (any type, remember typechecking)
        private bool $inDebugMode = false;
        public function __construct(bool $inDebugMode = false)
        {
            $this->inDebugMode = $inDebugMode;
            if ($inDebugMode) {
                echo "SystemDataStorage initialized.", PHP_EOL;
            }
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

        public function inDebugMode() : bool
        {
            return $this->inDebugMode;
        }
    }
