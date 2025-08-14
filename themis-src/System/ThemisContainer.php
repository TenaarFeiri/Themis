<?php
declare(strict_types=1);
namespace Themis\System;

use Exception;

/**
 * Minimal DI Container for Themis
 */
 // Note: Investigate adding auto-wiring capabilities in the future.

final class ThemisContainer {
    /** @var array<string, callable> */
    private array $bindings = [];
    /** @var array<string, mixed> */
    private array $instances = [];
    /** @var array<string> */
    private array $resolving = [];

    /**
     * Bind a resolver to a name.
     *
     * @param string $name
     * @param callable $resolver
     * @return void
     */
    final public function set(string $name, callable $resolver) {
        // Unbind previous instance if type is identical
        if (isset($this->bindings[$name])) {
            unset($this->instances[$name]);
        }
        $this->bindings[$name] = $resolver;
    }

    /**
     * Resolve an instance by name.
     *
     * @param string $name
     * @return mixed
     * @throws ContainerException
     */
    final public function get(string $name) {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        if (!isset($this->bindings[$name])) {
            throw new Exception("No binding for {$name}");
        }
        // Circular dependency detection
        if (in_array($name, $this->resolving, true)) {
            throw new Exception("Circular dependency detected for: {$name}");
        }
        $this->resolving[] = $name;
        $instance = $this->bindings[$name]($this);
        array_pop($this->resolving);
        $this->instances[$name] = $instance;
        return $instance;
    }

    /**
     * Check if a name is bound in the container.
     *
     * @param string $name
     * @return bool
     */
    final public function has(string $name): bool {
        return isset($this->bindings[$name]) || isset($this->instances[$name]);
    }

    /**
     * Clear all bindings and instances.
     *
     * @return void
     */
    final public function clear(): void {
        $this->bindings = [];
        $this->instances = [];
        $this->resolving = [];
    }

    /**
     * Unbind a name from the container.
     *
     * @param string $name
     * @return void
     */
    final public function unbind(string $name): void {
        unset($this->bindings[$name], $this->instances[$name]);
        $this->resolving = array_filter($this->resolving, fn($item) => $item !== $name);
    }
}

class ContainerException extends Exception {}
