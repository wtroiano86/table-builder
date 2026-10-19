<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;

it('loads package configuration and bindings', function (): void {
    expect(config('table-builder.pagination.default_per_page'))->toBe(15)
        ->and(config('table-builder.package_name'))->toBe('table-builder')
        ->and(resolve(TableQueryBuilder::class))->toBeInstanceOf(TableQueryBuilder::class);
});

it('registers package routes', function (): void {
    expect(Route::has('table-builder.actions.execute'))->toBeTrue()
        ->and(Route::has('table-builder.exports.execute'))->toBeTrue()
        ->and(route('table-builder.actions.execute', absolute: false))->toBe('/table-builder/actions')
        ->and(route('table-builder.exports.execute', absolute: false))->toBe('/table-builder/exports');
});

it('generates a table from the packaged stub', function (): void {
    $path = app_path('Tables/ExampleTable.php');

    try {
        expect(Artisan::call('make:table-builder', ['name' => 'ExampleTable']))->toBe(0)
            ->and(file_get_contents($path))->toContain('namespace App\\Tables;', 'use Wtroiano\\TableBuilder\\Table;', 'final class ExampleTable extends Table', 'declare(strict_types=1);');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});
