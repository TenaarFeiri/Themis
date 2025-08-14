<?php
namespace Themis\System;
use Themis\Init;

class DataContainer {
    private array $data = [];

    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    public function get(string $key): mixed {
        return $this->data[$key] ?? null;
    }
}
