<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;
use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\TableBuilder;

/** @internal Application-owned factory definitions; table instances are never cached. */
final class TableRegistryStore
{
    /** @var array<string, Closure> */
    private array $factories = [];

    /** @var array<string, true> */
    private array $resolving = [];

    public function __construct(private readonly Container $container) {}

    public function define(string $key, callable $factory): void
    {
        throw_if(preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $key) !== 1, InvalidArgumentException::class, 'Table registry keys must start with a letter or digit and contain only letters, digits, dots, underscores, or hyphens.');

        throw_if($this->has($key), InvalidArgumentException::class, "Table [{$key}] is already registered.");

        $this->factories[$key] = Closure::fromCallable($factory);
    }

    public function get(string $key): Table
    {
        throw_unless($this->has($key), InvalidArgumentException::class, "Table [{$key}] is not registered.");

        throw_if(isset($this->resolving[$key]), LogicException::class, "Circular table factory resolution for [{$key}].");

        $this->resolving[$key] = true;
        try {
            $definition = $this->container->call($this->factories[$key]);
            $table = $definition instanceof TableBuilder ? $definition->build() : $definition;
            throw_unless($table instanceof Table, InvalidArgumentException::class, "Factory for table [{$key}] must return a Table or TableBuilder.");

            return $table->registeredAs($key);
        } finally {
            unset($this->resolving[$key]);
        }
    }

    public function has(string $key): bool
    {
        return isset($this->factories[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->factories[$key]);
    }
}
