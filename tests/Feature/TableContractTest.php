<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exceptions\TableBuilderException;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pagination\PaginationConfiguration;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Query\QuerySource;
use Wtroiano\TableBuilder\Query\RelationFieldResolver;
use Wtroiano\TableBuilder\Query\TableQueryMetadataResolver;
use Wtroiano\TableBuilder\Query\TableQueryParameters;
use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\Tables\TableEmptyState;
use Wtroiano\TableBuilder\Tables\TablePayloadBuilder;
use Wtroiano\TableBuilder\Tables\TableState;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineTable;
use Wtroiano\TableBuilder\Tests\Fixtures\QualityReviewTable;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::pipelineRecords([['id' => 1, 'name' => 'Ada', 'team' => 'red'], ['id' => 2, 'name' => 'Grace', 'team' => 'blue']]);
    $this->table = new class extends Table
    {
        public Builder|string|null $source = PipelineRecord::class;

        public array $definitions = [];

        public function columns(): array
        {
            return $this->definitions;
        }

        public function resource(): Builder|string|null
        {
            return $this->source;
        }

        public function configure(array $options): self
        {
            foreach ($options as $key => $value) {
                $this->{$key} = $value;
            }

            return $this;
        }
    };
    $this->table->definitions = [TextColumn::make('name')->sortable()->searchable()->sticky()];
});

it('builds definitions without querying and serializes results with request state', function (): void {
    $table = $this->table->as('people');
    expect($table->id())->toBe('people')->and($this->table->isNamed())->toBeFalse()
        ->and($table->definition()['rowSelectionKey'])->toBe('_selection_key')
        ->and($table->payload(TableRequest::fromArray(['people' => ['search' => 'Ada']]))['state']['search'])->toBe('Ada');
    app()->instance('request', Request::create('/?people[search]=Grace'));
    expect($table->jsonSerialize()['results']['data'][0]['name'])->toBe('Grace');
});

it('clones builder resources so callers cannot mutate future queries', function (): void {
    $this->table->source = PipelineRecord::query()->where('team', 'red');
    $this->table->query()->whereKey(2);
    expect($this->table->query()->get()->modelKeys())->toBe([1]);
});

it('rejects missing resources invalid resources invalid definitions and empty names', function (): void {
    $this->table->source = null;
    expect(fn () => $this->table->query())->toThrow(TableBuilderException::class, 'does not define a resource');
    $this->table->source = stdClass::class;
    expect(fn () => $this->table->query())->toThrow(TableBuilderException::class, 'resource must be an Eloquent builder');
    $this->table->definitions = [new stdClass()];
    expect(fn () => $this->table->columnsDefinition())->toThrow(TableBuilderException::class, 'Invalid entry at index 0')
        ->and(fn () => $this->table->named(' '))->toThrow(TableBuilderException::class, 'Table name cannot be empty.');
});

it('normalizes default sort syntax and rejects malformed values safely', function (?string $sort, ?string $expected, string $direction): void {
    $this->table->configure(['defaultSort' => $sort]);
    expect($this->table->state()->sort())->toBe($expected)->and($this->table->state()->direction())->toBe($direction);
})->with([['', null, 'asc'], [' ', null, 'asc'], ['-', null, 'asc'], [' -name ', 'name', 'desc'], ['name', 'name', 'asc']]);

it('normalizes additional searchable fields and retains custom search predicates', function (): void {
    $this->table->configure(['search' => [' team ', '', null, 'team']]);
    expect($this->table->searchable())->toBe(['team']);
    $metadata = resolve(TableQueryMetadataResolver::class)->resolve($this->table);
    expect($metadata->columns())->toHaveCount(2)->and($metadata->columns()[1]->toArray())->toBe([
        'key' => 'team', 'field' => 'team', 'searchable' => true, 'sortable' => false, 'hasCustomSearchCallback' => false, 'hasCustomSortCallback' => false,
    ])->and($metadata->sortableColumn('team'))->toBeNull();
    $this->table->configure(['search' => 'team']);
    expect($this->table->apply(request: TableRequest::fromArray(['search' => 'blue']))->get()->modelKeys())->toBe([2]);
    $this->table->definitions = [TextColumn::make('name')->searchable()->searchUsing(static fn (Builder $query, string $term): Builder => $query->where('name', $term))];
    expect($this->table->apply(request: TableRequest::fromArray(['search' => 'Grace']))->get()->modelKeys())->toBe([2]);
    $builder = resolve(TableQueryBuilder::class)->searchUsing(null)->searchUsing(static fn (Builder $query): Builder => $query->where('team', 'red'));
    expect($builder->apply(PipelineRecord::query(), $this->table, ['search' => 'anything'])->get()->modelKeys())->toBe([1]);
    $this->table->definitions = [];
    $this->table->configure(['search' => null]);

    expect($this->table->apply(request: TableRequest::fromArray(['search' => 'anything']))->count())->toBe(2);
});

it('serializes empty states and visibility defaults without fetching records', function (): void {
    $this->table->definitions = [TextColumn::make('name')->sticky()];
    $builder = resolve(TablePayloadBuilder::class);
    $this->table->configure(['emptyState' => TableEmptyState::make('Empty', 'Add records', 'inbox')]);
    $payload = $builder->buildResults($this->table, new TableState(columns: ['name']), collect());
    expect($payload['state']['columns'])->toBe(['name' => false])->and($payload['state']['sticky'])->toBe(['name'])
        ->and($payload['emptyState']['title'])->toBe('Empty');
    $this->table->configure(['emptyState' => ['title' => 'Custom'], 'rowSelectionKey' => null]);
    expect($builder->buildResults($this->table, new TableState(), [])['emptyState'])->toBe(['title' => 'Custom'])
        ->and($this->table->payload()['rowSelectionKey'])->toBeNull()
        ->and(TableEmptyState::create()->jsonSerialize()['title'])->toBeNull();
});

it('adds configured default page sizes to global options and normalizes invalid client sizes', function (): void {
    config()->set('table-builder.pagination.default_per_page', '25');
    $configuration = $this->table->pagination();
    expect($configuration->perPageOptions())->toBe([15, 25, 30, 50, 100])
        ->and($configuration->normalizePage('invalid'))->toBe(1)->and($configuration->normalizePerPage('invalid'))->toBe(25);
});

it('rejects invalid pagination configuration at the public boundaries', function (Closure $make, string $message): void {
    expect($make)->toThrow(TableBuilderException::class, $message);
})->with([
    [static fn (): TableState => new TableState(page: 0), 'page must be greater'],
    [static fn (): TableState => new TableState(perPage: 0), 'perPage must be greater'],
    [static fn (): PaginationConfiguration => new PaginationConfiguration(defaultPerPage: 0), 'perPage must be greater'],
    [static fn (): PaginationConfiguration => new PaginationConfiguration(perPageOptions: [0]), 'options must contain positive integers'],
    [static fn (): PaginationConfiguration => PaginationConfiguration::make(perPageOptions: ['bad']), 'options must contain positive integers'],
    [static fn (): PaginationConfiguration => PaginationConfiguration::make(perPageOptions: []), 'options cannot be empty'],
    [static fn (): PaginationConfiguration => PaginationConfiguration::make(defaultPerPage: 7), 'must be present in perPageOptions'],
]);

it('handles malformed export selection and optional data without treating them as valid input', function (): void {
    $input = ExportRequest::fromRequest(Request::create('/', 'POST', ['keys' => 'bad', 'data' => ['format' => 'short']]));
    expect($input->selectedKeys())->toBe([])->and($input->data())->toBe(['format' => 'short'])
        ->and(ExportRequest::fromRequest(Request::create('/'))->data())->toBe([]);
});

it('keeps explicitly sticky columns in payload state', function (): void {
    $this->table->definitions = [TextColumn::make('name')->sticky(), TextColumn::make('team')->sticky()];
    $payload = resolve(TablePayloadBuilder::class)->buildResults($this->table, new TableState(), []);
    expect($payload['state']['sticky'])->toBe(['name', 'team']);
    $override = resolve(TablePayloadBuilder::class)->buildResults($this->table, new TableState(sticky: [], hasStickyOverride: true), []);
    expect($override['state']['sticky'])->toBe([]);
    $this->table->configure(['search' => ['name']]);
    expect(resolve(TableQueryMetadataResolver::class)->resolve($this->table)->searchableColumns())->toHaveCount(1);
    expect(TableQueryParameters::fromArray(['direction' => []])->direction())->toBe('asc');
});

it('distinguishes malformed relation paths and expression query sources without corrupting SQL metadata', function (): void {
    $query = PipelineRecord::query()->from(new Expression(123));
    expect(QuerySource::table($query))->toBe('pipeline_records');
    $resolver = new RelationFieldResolver();
    expect($resolver->resolve('.name'))->toBeNull();
    $query = PipelineRecord::query()->join(new Expression('(select 1) as other'), 'other.id', '=', 'pipeline_records.id');
    expect($resolver->resolve('other.name', $query))->toBe(['relation' => 'other', 'column' => 'name']);
});

it('ignores unsupported relation sort definitions without generating an invalid subquery', function (string $field): void {
    $table = QualityReviewTable::make();
    $table->field = $field;

    $query = $table->apply(request: TableRequest::fromArray(['sort' => $field]));
    expect($query->getQuery()->orders)->toBeNull();
})->with(['children.invalid-column', 'unknown.label', 'getKey.name', 'children.unknown.label']);

it('rejects stale serialized export definitions and maps configured spreadsheet formats', function (): void {
    $this->table->definitions = [TextColumn::make('name')->exportFormat('@'), TextColumn::make('team')];
    $request = ExportRequest::fromRequest(Request::create('/'));
    $export = TableExcelExport::fromRequest($this->table, Export::make(), $request);
    expect($export->columnFormats())->toBe(['A' => '@']);
    $stale = new TableExcelExport(PipelineTable::class, 'missing');
    expect(fn (): array => $stale->registerEvents())->toThrow(RuntimeException::class, 'could not be resolved');
});

it('retains resource ordering when a configured relationship sort cannot be applied', function (): void {
    $table = QualityReviewTable::make();
    $table->field = 'unknown.label';

    $query = $table->apply(PipelineRecord::query()->orderBy('id', 'desc'), TableRequest::fromArray(['sort' => 'unknown.label']));
    expect($query->get()->modelKeys())->toBe([2, 1]);
});
