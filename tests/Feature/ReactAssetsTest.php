<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\BadgeColumn;
use Wtroiano\TableBuilder\Columns\BooleanColumn;
use Wtroiano\TableBuilder\Columns\DateColumn;
use Wtroiano\TableBuilder\Columns\NumericColumn;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Providers\TableBuilderServiceProvider;
use Wtroiano\TableBuilder\Support\Image;
use Wtroiano\TableBuilder\TableBuilder;
use Wtroiano\TableBuilder\TableRegistry;
use Wtroiano\TableBuilder\Tables\TableEmptyState;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

it('publishes usable React components and an optional Inertia page without overwriting application files', function (): void {
    $sources = ServiceProvider::pathsToPublish(TableBuilderServiceProvider::class, 'table-builder-react');
    $pages = ServiceProvider::pathsToPublish(TableBuilderServiceProvider::class, 'table-builder-react-page');
    expect($sources)->toBe([dirname(__DIR__, 2).'/resources/react' => resource_path('js/vendor/table-builder')])
        ->and($pages)->toBe([dirname(__DIR__, 2).'/resources/stubs/table-builder-page.tsx.stub' => resource_path('js/pages/vendor/table-builder.tsx')]);

    $directory = resource_path('js/vendor/table-builder');
    $page = resource_path('js/pages/vendor/table-builder.tsx');
    try {
        Artisan::call('vendor:publish', ['--tag' => 'table-builder-react']);
        Artisan::call('vendor:publish', ['--tag' => 'table-builder-react-page']);
        expect(file_get_contents($directory.'/Table.tsx'))->toContain('export function Table')
            ->and(file_get_contents($directory.'/table.css'))->toContain('--tb-surface')
            ->and(file_get_contents($page))->toContain("from '../../vendor/table-builder'");
        file_put_contents($page, '// Application customization');
        Artisan::call('vendor:publish', ['--tag' => 'table-builder-react-page']);
        expect(file_get_contents($page))->toBe('// Application customization');
    } finally {
        resolve(Filesystem::class)->deleteDirectory($directory);
        resolve(Filesystem::class)->delete($page);
    }
});

it('keeps the shared React payload fixture aligned with real backend serialization', function (): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'name' => 'Ada Lovelace', 'team' => 'Engineering', 'code' => 'acct-001'],
        ['id' => 2, 'name' => 'Grace Hopper', 'team' => 'Operations', 'code' => 'acct-002'],
        ['id' => 3, 'name' => 'Linus Torvalds', 'team' => 'Engineering', 'code' => 'acct-003'],
    ]);
    app()->instance('request', Request::create('http://localhost/records?tab=people'));
    TableRegistry::define('react-records', static fn (): TableBuilder => TableBuilder::make('people')
        ->resource(PipelineRecord::class)->authorize(true)->defaultSort('name')->perPage(2)->perPageOptions([2, 10])
        ->rowSelectionKey('code')->stickyHeader()->selectableUsing(static fn (Model $model): bool => $model->getKey() !== 2)
        ->columns([
            TextColumn::make('name', label: 'Name', sortable: true, searchable: true)->image(static fn (Model $model, Image $image): Image => $image->url('/avatar.svg')->alt($model->getAttribute('name'))->rounded()),
            TextColumn::make('code', label: 'Account', mapAs: static fn (mixed $value): string => 'DISPLAY-'.mb_strtoupper($value), visible: false),
            BadgeColumn::make('team')->colors(['Engineering' => 'blue', 'Operations' => 'green']),
            BooleanColumn::make('active', mapAs: static fn (mixed $value, Model $model): bool => $model->getKey() === 1)->trueLabel('Active')->falseLabel('Inactive'),
            NumericColumn::make('balance', mapAs: static fn (): float => 3475.5)->precision(2)->prefix('$'),
            DateColumn::make('joined', mapAs: static fn (): string => '2026-01-02')->format('M j, Y'),
        ])
        ->actions([
            Action::make('Edit')->key('edit')->url(static fn (Model $model): string => '/records/'.$model->getKey()),
            Action::make('Archive')->key('archive')->asBulkAction()->confirm('Archive records?', 'This will archive the selected records.', 'Archive', 'Keep records')->handle(static fn (): null => null),
        ])->exports([
            Export::make('Download records', 'records.xlsx')->key('records')->limitToFilteredRows(),
            Export::make('Download selected', 'selected.xlsx')->key('selected')->limitToFilteredRows()->limitToSelectedRows(),
            Export::make('Queue export', 'records.xlsx')->key('background')->queue(),
        ])->emptyState(TableEmptyState::make('No matching people', 'Try a different name or clear your search.')));

    $payload = TableRegistry::get('react-records')->results();
    $fixture = __DIR__.'/../react/fixtures/table.json';
    if (getenv('UPDATE_REACT_CONTRACT') === '1') {
        file_put_contents($fixture, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    expect($payload)->toEqual(json_decode(file_get_contents($fixture), true, flags: JSON_THROW_ON_ERROR));
});

it('round trips visibility defaults so the column picker can reveal initially hidden columns', function (): void {
    DatabaseFixtures::pipelineRecords([['id' => 1, 'name' => 'Ada', 'team' => 'Engineering', 'code' => 'raw']]);
    $table = TableBuilder::make('people')->resource(PipelineRecord::class)->columns([
        TextColumn::make('name'), TextColumn::make('code', visible: false),
    ])->build();
    expect($table->state()->columns())->toBe(['code'])
        ->and($table->results()['state']['columns'])->toBe(['name' => true, 'code' => false]);

    $shown = $table->results(request: TableRequest::fromArray(['people' => ['columns' => '']]));
    expect($shown['state']['columns'])->toBe(['name' => true, 'code' => true])
        ->and($table->results(request: TableRequest::fromArray(['people' => ['columns' => ['name']]]))['state']['columns'])
        ->toBe(['name' => false, 'code' => true]);
});
