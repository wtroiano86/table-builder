<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Demo;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\TableRegistry;

final class DemoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $directory = (string) getenv('TABLE_DEMO_STORAGE');
        $this->app->make(Repository::class)->set([
            'app.env' => 'local',
            'app.debug' => true,
            'app.key' => 'base64:'.base64_encode(str_repeat('demo', 8)),
            'database.default' => 'demo',
            'database.connections.demo' => ['driver' => 'sqlite', 'database' => $directory.'/database.sqlite', 'foreign_key_constraints' => true],
            'session.driver' => 'file',
            'session.files' => $directory.'/storage/framework/sessions',
            'session.cookie' => 'table_builder_demo',
            'cache.default' => 'array',
            'table-builder.middleware' => ['web'],
        ]);
    }

    public function boot(): void
    {
        DemoData::prepare();
        DemoTables::register();

        Route::middleware('web')->group(function (): void {
            Route::get('/{demo}', function (Request $request, string $demo): JsonResponse {
                $table = TableRegistry::get('demo-'.$demo);
                abort_unless($table->authorize($request), 403);

                return response()->json([
                    'component' => 'Demo',
                    'props' => [
                        'table' => $table->results(request: TableRequest::fromRequest($request)),
                        'demo' => $demo,
                        'title' => ucfirst($demo),
                        'description' => $demo === 'customers'
                            ? 'A little clarity for every customer relationship.'
                            : 'From the first invoice to the final payment.',
                    ],
                    'url' => $request->getRequestUri(),
                    'version' => 'demo',
                    'clearHistory' => false,
                    'encryptHistory' => false,
                ])->header('X-Inertia', 'true')->header('Vary', 'X-Inertia');
            })->whereIn('demo', ['customers', 'invoices']);

            Route::post('/demo/reset', function (): JsonResponse {
                DemoData::reset();

                return response()->json(['ok' => true]);
            });
        });
    }
}
