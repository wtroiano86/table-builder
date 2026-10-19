<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Providers;

use Illuminate\Support\ServiceProvider;
use Wtroiano\TableBuilder\Commands\MakeTableCommand;
use Wtroiano\TableBuilder\Pagination\TablePaginationBuilder;
use Wtroiano\TableBuilder\Pipelines\ActionChunk\ActionChunkPipeline;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\ActionPreparationPipeline;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\ExportExecutionPipeline;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\ExportQueryBuilder;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\RowSerializationPipeline;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Query\TableQueryMetadataResolver;
use Wtroiano\TableBuilder\Tables\TablePayloadBuilder;
use Wtroiano\TableBuilder\Tables\TableRegistryStore;

final class TableBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 2).'/config/table-builder.php',
            'table-builder',
        );

        $this->app->singleton(TableQueryMetadataResolver::class);
        $this->app->singleton(TableQueryBuilder::class);
        $this->app->singleton(ExportQueryBuilder::class);
        $this->app->singleton(TablePaginationBuilder::class);
        $this->app->singleton(TablePayloadBuilder::class);
        $this->app->singleton(ActionChunkPipeline::class);
        $this->app->singleton(ActionPreparationPipeline::class);
        $this->app->singleton(ExportExecutionPipeline::class);
        $this->app->singleton(RowSerializationPipeline::class);
        $this->app->singleton(TableRegistryStore::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__, 2).'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeTableCommand::class,
            ]);
        }

        $this->publishes([
            dirname(__DIR__, 2).'/config/table-builder.php' => config_path('table-builder.php'),
        ], 'table-builder-config');

        $this->publishes([
            dirname(__DIR__, 2).'/resources/react' => resource_path('js/vendor/table-builder'),
        ], 'table-builder-react');

        $this->publishes([
            dirname(__DIR__, 2).'/resources/stubs/table-builder-page.tsx.stub' => resource_path('js/pages/vendor/table-builder.tsx'),
        ], 'table-builder-react-page');
    }
}
