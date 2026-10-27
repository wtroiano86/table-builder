<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Exceptions\TableBuilderException;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Support\Url;
use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\TableBuilder;
use Wtroiano\TableBuilder\Tables\TableEmptyState;
use Wtroiano\TableBuilder\Tables\TableState;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'name' => 'Ada', 'team' => 'red', 'code' => 'first'],
        ['id' => 2, 'name' => 'Grace', 'team' => 'blue', 'code' => 'second'],
        ['id' => 3, 'name' => 'Linus', 'team' => 'red', 'code' => 'third'],
    ]);
});

it('renders inline fluent tables through existing search sort and pagination pipelines', function (): void {
    $table = TableBuilder::make('people')->resource(PipelineRecord::class)
        ->columns([TextColumn::make('name')->sortable()->searchable()])
        ->defaultSort('-name')->perPage(1)->perPageOptions([1, 2])->build();

    $first = $table->results();
    expect($first['results']['data'][0]['name'])->toBe('Linus')
        ->and($first['results']['total'])->toBe(3)
        ->and($first['meta'])->not->toHaveKeys(['table', 'actionEndpoint', 'exportEndpoint'])
        ->and($first['meta']['queryString']['namespace'])->toBe('people')
        ->and($table->reference())->toBeNull();

    $input = TableRequest::fromArray(['people' => ['search' => 'Ada', 'sort' => 'name']]);
    expect($table->results(request: $input)['results']['data'][0]['name'])->toBe('Ada')
        ->and($table->apply(request: $input)->get()->modelKeys())->toBe([1]);
    app()->instance('request', Request::create('/?people[search]=Grace'));
    expect($table->toArray()['results']['data'][0]['name'])->toBe('Grace');
});

it('keeps built tables independent of builder prototypes and source queries', function (): void {
    $source = PipelineRecord::query()->where('team', 'red');
    $column = TextColumn::make('name')->label('Original');
    $emptyState = TableEmptyState::make('Original');
    $builder = TableBuilder::make('people')->resource($source)->columns([$column])->emptyState($emptyState);
    $first = $builder->build();
    $column->label('Changed');
    $emptyState->title('Changed');
    $source->whereKey(2);
    $copy = clone $builder;
    $copy->resource(PipelineRecord::class)->columns([TextColumn::make('team')]);

    expect($first->query()->get()->modelKeys())->toBe([1, 3])
        ->and($first->columnsDefinition()[0]->resolvedLabel())->toBe('Original')
        ->and($first->emptyState()->toArray()['title'])->toBe('Original')
        ->and($builder->build()->columnsDefinition()[0]->resolvedLabel())->toBe('Changed')
        ->and($copy->build()->columnsDefinition()[0]->name())->toBe('team');
});

it('applies fluent state presentation and row hooks', function (): void {
    $table = TableBuilder::make('people')->resource(static fn (): Builder => PipelineRecord::query())
        ->columns([TextColumn::make('name')->sortable()])
        ->searchable(['team'])->defaultSort('-name')->perPage(2)->perPageOptions([1, 2])
        ->pagination(true, PaginationType::Cursor)->scrollToTop(false)->stickyHeader()
        ->rowSelectionKey('code')->selectable(false)->persistRowSelectionAcrossPages()
        ->emptyState(TableEmptyState::make('Nobody here'))
        ->defaultState(['search' => 'red', 'sort' => 'name', 'direction' => 'desc', 'columns' => ['name'], 'sticky' => []])
        ->selectableUsing(static fn (Model $model): bool => $model->getAttribute('team') === 'red')
        ->rowUrl(static fn (Model $model, Url $url): Url => $url->to('/people/'.$model->getKey()))
        ->transformModel(static fn (Model $model, array $row): array => [...$row, 'display' => mb_strtoupper($row['name'])])
        ->withQueryBuilder(static fn (TableQueryBuilder $builder): TableQueryBuilder => $builder->searchUsing(
            static fn (Builder $query): Builder => $query->where('team', 'red'),
        ))->build();

    $payload = $table->results();
    expect($payload['results']['data'][0]['display'])->toBe('LINUS')
        ->and($payload['results']['data'][0]['_selection_key'])->toBe('third')
        ->and($payload['results']['data'][0]['_url']['url'])->toBe('/people/3')
        ->and($payload['state']['columns']['name'])->toBeFalse()
        ->and($payload['state']['sticky'])->toBe([])
        ->and($payload['paginationType'])->toBe('cursor')
        ->and($payload['scrollPositionAfterPageChange'])->toBe('preserve')
        ->and($payload['stickyHeader'])->toBeTrue()
        ->and($payload['selectable'])->toBeFalse()
        ->and($payload['persistRowSelectionAcrossPages'])->toBeTrue()
        ->and($payload['emptyState']['title'])->toBe('Nobody here')
        ->and($table->searchable())->toBe(['team'])
        ->and($table->isSelectable(PipelineRecord::query()->findOrFail(2)))->toBeFalse();
});

it('supports typed default states disabled pagination and lazy definitions', function (): void {
    $calls = 0;
    $builder = TableBuilder::make('people')->resource(static fn (): string => PipelineRecord::class)
        ->columns(static function (Table $table) use (&$calls): array {
            $calls++;

            return [TextColumn::make('name')->label($table->name())];
        })->actions(static fn (): array => [Action::make('Profile')->url('/profile')])
        ->exports(static fn (): array => [])->pagination(false)->rowSelectionKey(null)
        ->defaultState(new TableState(search: 'Ada'));
    $table = $builder->build();
    expect($calls)->toBe(0);
    $payload = $table->results();
    expect($calls)->toBe(1)->and($payload['results'][0]['name'])->toBe('Ada')
        ->and($payload['rowSelectionKey'])->toBeNull()->and($payload['pagination'])->toBeFalse();
    $table->results();
    expect($calls)->toBe(2);
});

it('defaults to authenticated access and accepts explicit fluent authorization', function (): void {
    $request = Request::create('/');
    $builder = TableBuilder::make('people')->resource(PipelineRecord::class);
    expect($builder->build()->authorize($request))->toBeFalse();
    $request->setUserResolver(static fn (): GenericUser => new GenericUser(['id' => 1]));
    expect($builder->build()->authorize($request))->toBeTrue()
        ->and($builder->authorize(false)->build()->authorize($request))->toBeFalse()
        ->and($builder->authorize(true)->build()->authorize(Request::create('/')))->toBeTrue()
        ->and($builder->authorize(static fn (Request $input): bool => $input->user()?->getAuthIdentifier() === 1)->build()->authorize($request))->toBeTrue();
});

it('rejects executable inline definitions with a registration instruction', function (bool $export): void {
    $builder = TableBuilder::make('people')->resource(PipelineRecord::class);
    if ($export) {
        $builder->exports([Export::make('Records')]);
    } else {
        $builder->actions([Action::make('Run')->handle(static fn (): null => null)]);
    }

    expect(fn (): array => $builder->build()->payload())->toThrow(LogicException::class, 'Register fluent tables');
})->with([false, true]);

it('requires a registered reference before constructing a fluent spreadsheet export', function (): void {
    $table = TableBuilder::make('people')->resource(PipelineRecord::class)->build();
    expect(fn (): TableExcelExport => TableExcelExport::fromRequest($table, Export::make(), ExportRequest::fromRequest(Request::create('/'))))
        ->toThrow(RuntimeException::class, 'Register fluent tables');
});

it('validates fluent names sources and page sizes', function (): void {
    expect(fn (): TableBuilder => TableBuilder::make(' '))->toThrow(TableBuilderException::class)
        ->and(fn (): TableBuilder => TableBuilder::make('people')->resource(stdClass::class))->toThrow(TableBuilderException::class)
        ->and(fn (): TableBuilder => TableBuilder::make('people')->perPage(0))->toThrow(TableBuilderException::class);
});
