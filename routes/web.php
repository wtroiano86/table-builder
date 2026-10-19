<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Wtroiano\TableBuilder\Http\Controllers\ActionController;
use Wtroiano\TableBuilder\Http\Controllers\ExportController;

Route::middleware(config('table-builder.middleware', ['web', 'auth']))
    ->prefix('table-builder')
    ->group(function (): void {
        Route::post('/actions', ActionController::class)
            ->name('table-builder.actions.execute');

        Route::post('/exports', ExportController::class)
            ->name('table-builder.exports.execute');
    });
