<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder;

use Wtroiano\TableBuilder\Tables\TableRegistryStore;

final class TableRegistry
{
    public static function define(string $key, callable $factory): void
    {
        resolve(TableRegistryStore::class)->define($key, $factory);
    }

    public static function get(string $key): Table
    {
        return resolve(TableRegistryStore::class)->get($key);
    }

    public static function has(string $key): bool
    {
        return resolve(TableRegistryStore::class)->has($key);
    }

    public static function forget(string $key): void
    {
        resolve(TableRegistryStore::class)->forget($key);
    }
}
