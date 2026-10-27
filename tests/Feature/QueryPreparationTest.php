<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\ExportQueryBuilder;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Query\TableQueryMetadataResolver;
use Wtroiano\TableBuilder\Query\TableQueryParameters;
use Wtroiano\TableBuilder\Query\TableSearchBuilder;
use Wtroiano\TableBuilder\Query\TableSortBuilder;
use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\Tables\TableState;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineTable;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {

    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'name' => 'Ada', 'team' => 'red', 'code' => 'first'],
        ['id' => 2, 'name' => 'Grace', 'team' => 'blue', 'code' => 'second'],
        ['id' => 3, 'name' => 'Ada', 'team' => 'blue', 'code' => 'third'],
        ['id' => 4, 'name' => 'Alan', 'team' => 'red', 'code' => 'fourth'],
    ]);
});

it('applies search and sort with every supported parameter representation', function (string $representation): void {
    $input = ['search' => 'A', 'sort' => 'name', 'direction' => 'desc'];
    $parameters = match ($representation) {
        'state' => TableState::fromArray($input),
        'parameters' => TableQueryParameters::fromArray($input),
        default => $input,
    };
    $query = PipelineRecord::query();

    expect(resolve(TableQueryBuilder::class)->apply($query, PipelineTable::make(), $parameters))->toBe($query)
        ->and($query->pluck('name')->all())->toBe($query->getConnection()->getDriverName() === 'pgsql' ? ['Alan', 'Ada', 'Ada'] : ['Grace', 'Alan', 'Ada', 'Ada']);
})->with(['array', 'state', 'parameters']);

it('leaves the query unchanged when no search or sort was requested', function (): void {
    $query = PipelineRecord::query()->where('team', 'red');
    $sql = $query->toSql();
    $bindings = $query->getBindings();

    resolve(TableQueryBuilder::class)->apply($query, PipelineTable::make());

    expect($query->toSql())->toBe($sql)->and($query->getBindings())->toBe($bindings);
});

it('runs custom search before sort and keeps callbacks isolated from the shared builder', function (): void {
    $table = new class extends Table
    {
        public array $events = [];

        protected ?string $resource = PipelineRecord::class;

        public function columns(): array
        {
            return [TextColumn::make('name')->sortable()->sortUsing(function (Builder $query): void {
                $this->events[] = 'sort';
                $query->orderBy('id', 'desc');
            })];
        }
    };
    $shared = resolve(TableQueryBuilder::class);
    $customized = $shared->searchUsing(function (Builder $query) use ($table): void {
        $table->events[] = 'search';
        $query->orWhere('name', 'Ada');
    });
    $query = PipelineRecord::query()->where('team', 'red');
    $customized->apply($query, $table, ['search' => 'anything', 'sort' => 'name']);

    expect($table->events)->toBe(['search', 'sort'])
        ->and($query->pluck('id')->all())->toBe([1]);

    $untouched = PipelineRecord::query();
    $shared->apply($untouched, PipelineTable::make(), ['search' => 'Grace']);
    expect($untouched->pluck('id')->all())->toBe([2]);
});

it('prepares matching synchronous and reconstructed export queries', function (): void {
    $input = [
        'table' => PipelineTable::class,
        'export' => 'records',
        'state' => ['search' => 'Ada', 'sort' => 'name', 'direction' => 'desc'],
        'keys' => [1, 2, 3],
    ];
    $this->postJson(route('table-builder.exports.execute'), $input)->assertOk()->assertExactJson(['ids' => [1, 3]]);

    $table = PipelineTable::make();
    $request = ExportRequest::fromRequest(Request::create('/', 'POST', $input));
    $export = TableExcelExport::fromRequest($table, $table->exportsDefinition()[0], $request);
    $restored = unserialize(serialize($export));

    expect($restored->query()->pluck('id')->all())->toBe([1, 3])
        ->and($restored->query()->getQuery()->orders)->toBe([
            ['column' => 'pipeline_records.name', 'direction' => 'desc'],
            ['column' => 'pipeline_records.id', 'direction' => 'asc'],
        ]);
});

it('returns no rows for an empty selected-row export', function (): void {
    $input = ['table' => PipelineTable::class, 'export' => 'records', 'keys' => []];
    $this->postJson(route('table-builder.exports.execute'), $input)->assertOk()->assertExactJson(['ids' => []]);

    $table = PipelineTable::make();
    $request = ExportRequest::fromRequest(Request::create('/', 'POST', $input));
    expect(TableExcelExport::fromRequest($table, $table->exportsDefinition()[0], $request)->query()->count())->toBe(0);
});

it('allows unrestricted exports to ignore selection and table-state input', function (): void {
    $query = resolve(ExportQueryBuilder::class)->apply(
        PipelineTable::make(),
        Export::make('All'),
        TableRequest::fromArray(['search' => 'no match']),
        [],
    );

    expect($query->pluck('id')->all())->toBe([1, 2, 3, 4]);
});

it('restricts selected rows using the tables configured selection key', function (): void {
    $table = new class extends Table
    {
        protected ?string $resource = PipelineRecord::class;

        protected ?string $rowSelectionKey = 'code';

        public function columns(): array
        {
            return [];
        }
    };
    $query = resolve(ExportQueryBuilder::class)->apply(
        $table,
        Export::make('Selected')->limitToSelectedRows(),
        TableRequest::fromArray([]),
        ['third', 'first'],
    );

    expect($query->pluck('id')->all())->toBe([1, 3]);
});

it('retains existing primary-key ordering without appending another order', function (bool $qualified): void {
    $table = new class extends Table
    {
        public bool $qualified = false;

        protected ?string $resource = PipelineRecord::class;

        public function columns(): array
        {
            return [];
        }

        public function query(): Builder
        {
            return parent::query()->orderBy($this->qualified ? 'pipeline_records.id' : 'id', 'desc');
        }
    };
    $table->qualified = $qualified;

    $builder = resolve(ExportQueryBuilder::class);
    $export = Export::make('All');
    $request = TableRequest::fromArray([]);
    $first = $builder->apply($table, $export, $request);
    $second = $builder->apply($table, $export, $request);

    expect($first->pluck('id')->all())->toBe([4, 3, 2, 1])
        ->and($first->getQuery()->orders)->toHaveCount(1)
        ->and($second->toSql())->toBe($first->toSql());
})->with([false, true]);

it('keeps the deprecated search and sort adapters compatible', function (): void {
    $query = PipelineRecord::query();
    $metadata = resolve(TableQueryMetadataResolver::class)->resolve(PipelineTable::make());
    resolve(TableSearchBuilder::class)->apply($query, $metadata, 'Ada');
    resolve(TableSortBuilder::class)->apply($query, $metadata, 'name', 'asc');

    expect($query->pluck('id')->all())->toBe([1, 3]);
});
