<?php
    namespace Themis\Interface;

    interface SystemDataStorageInterface
    {
        public function __construct(bool $inDebugMode = false);
        public function storeData($key, $value): void;
        public function getAllData(): array;
        public function readData($key) : mixed;
        public function inDebugMode() : bool;
    }