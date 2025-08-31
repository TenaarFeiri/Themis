<?php
declare(strict_types=1);
namespace Themis\System;

use Exception;

/**
 * Class ThemisContainer
 *
 * Minimal dependency injection (DI) container for Themis.
 * Supports binding resolvers, resolving instances, circular dependency detection,
 * and management of bindings and instances. Future: consider auto-wiring.
 */

final class ThemisContainer {
    /**
     * @var array<string, callable> Bindings of names to resolver callables.
     */
    private array $bindings = [];
    /**
     * @var array<string, mixed> Instantiated objects by name.
     */
    private array $instances = [];
    /**
     * @var array<string> Names currently being resolved (for circular dependency detection).
     */
    private array $resolving = [];

    /**
     * Binds a resolver callable to a name in the container.
     *
     * Unbinds previous instance if type is identical.
     *
     * @param string $name Name to bind.
     * @param callable $resolver Resolver function accepting the container.
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
     * Resolves and returns an instance by name.
     *
     * If already instantiated, returns the instance. Otherwise, calls the resolver.
     * Detects and prevents circular dependencies.
     *
     * @param string $name Name to resolve.
     * @return mixed      The resolved instance.
     * @throws Exception  If no binding exists or circular dependency detected.
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
     * Checks if a name is bound or instantiated in the container.
     *
     * @param string $name Name to check.
     * @return bool       True if bound or instantiated, false otherwise.
     */
    final public function has(string $name): bool {
        return isset($this->bindings[$name]) || isset($this->instances[$name]);
    }

    /**
     * Clears all bindings, instances, and resolving state from the container.
     *
     * @return void
     */
    final public function clear(): void {
        $this->bindings = [];
        $this->instances = [];
        $this->resolving = [];
    }

    /**
     * Unbinds a name from the container and removes its instance.
     *
     * @param string $name Name to unbind.
     * @return void
     */
    final public function unbind(string $name): void {
        unset($this->bindings[$name], $this->instances[$name]);
        $this->resolving = array_filter($this->resolving, fn($item) => $item !== $name);
    }
}

/**
 * Exception thrown for DI container errors.
 */
class ContainerException extends Exception {}
