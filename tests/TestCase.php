<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Wtroiano\TableBuilder\Providers\TableBuilderServiceProvider;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(new GenericUser(['id' => 1, 'remember_token' => null]));
    }

    protected function tearDown(): void
    {
        try {
            DatabaseFixtures::cleanUp();
            DB::purge('testing');
        } finally {
            parent::tearDown();
        }
    }

    protected function getPackageProviders(mixed $app): array
    {
        return [TableBuilderServiceProvider::class];
    }

    protected function defineEnvironment(mixed $app): void
    {
        $driver = env('TABLE_TEST_DB_DRIVER', 'sqlite');
        $app->make(Repository::class)->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app->make(Repository::class)->set('database.connections.testing', [
            'driver' => $driver,
            'host' => env('TABLE_TEST_DB_HOST', '127.0.0.1'),
            'port' => env('TABLE_TEST_DB_PORT', $driver === 'pgsql' ? 5432 : 3306),
            'database' => env('TABLE_TEST_DB_DATABASE', $driver === 'sqlite' ? ':memory:' : 'table_builder_test'),
            'username' => env('TABLE_TEST_DB_USERNAME', 'root'),
            'password' => env('TABLE_TEST_DB_PASSWORD', ''),
            'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
    }
}
