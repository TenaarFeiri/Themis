<?php
    namespace Themis\Interface;

    interface SystemDataStorageInterface
    {
        public function __construct();
        public function storeData($key, $value): void;
        public function getAllData(): array;
        public function readData($key) : mixed;
    }