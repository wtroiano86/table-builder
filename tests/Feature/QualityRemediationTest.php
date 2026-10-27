<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\ExportQueryBuilder;
use Wtroiano\TableBuilder\Support\Callback;
use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\Tests\Fixtures\QualityChildRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\QualityDescendantRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\QualityParentRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\QualityReviewTable;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::relations([
        ['id' => 1, 'name' => 'Ada', 'manager_id' => null],
        ['id' => 2, 'name' => 'Grace', 'manager_id' => 1],
        ['id' => 3, 'name' => 'Alan', 'manager_id' => 2],
    ], [
        ['id' => 1, 'parent_id' => 1, 'label' => 'B', 'active' => true, 'deleted_at' => null],
        ['id' => 2, 'parent_id' => 1, 'label' => 'E', 'active' => true, 'deleted_at' => null],
        ['id' => 3, 'parent_id' => 2, 'label' => 'C', 'active' => true, 'deleted_at' => null],
        ['id' => 4, 'parent_id' => 2, 'label' => 'D', 'active' => true, 'deleted_at' => null],
        ['id' => 5, 'parent_id' => 2, 'label' => 'A', 'active' => false, 'deleted_at' => null],
        ['id' => 6, 'parent_id' => 2, 'label' => 'Z', 'active' => true, 'deleted_at' => '2026-01-01 00:00:00'],
    ], [
        ['id' => 1, 'child_id' => 1, 'label' => 'H'], ['id' => 2, 'child_id' => 2, 'label' => 'Y'],
        ['id' => 3, 'child_id' => 3, 'label' => 'I'], ['id' => 4, 'child_id' => 4, 'label' => 'X'],
        ['id' => 5, 'child_id' => 5, 'label' => 'A'], ['id' => 6, 'child_id' => 6, 'label' => 'Z'],
    ]);
    $this->table = QualityReviewTable::make();
    app()->instance(QualityReviewTable::class, $this->table);
});

it('sorts relation collections once per parent with relation constraints and soft-delete scopes', function (string $field, string $direction, array $ids, array $postgresIds): void {
    $this->table->field = $field;
    $query = $this->table->apply(request: TableRequest::fromArray(['sort' => $field, 'direction' => $direction]));

    expect($query->get()->modelKeys())->toBe($query->getConnection()->getDriverName() === 'pgsql' ? $postgresIds : $ids)
        ->and($query->getQuery()->joins)->toBeNull()
        ->and($query->count())->toBe(3);
})->with([
    ['children.label', 'asc', [3, 1, 2], [1, 2, 3]], ['children.label', 'desc', [1, 2, 3], [3, 1, 2]],
    ['children.descendants.label', 'asc', [3, 1, 2], [1, 2, 3]], ['children.descendants.label', 'desc', [1, 2, 3], [3, 1, 2]],
    ['firstChild.label', 'asc', [3, 1, 2], [1, 2, 3]],
    ['manager.name', 'asc', [1, 2, 3], [2, 3, 1]],
]);

it('supports belongs-to relation sorting', function (): void {
    $table = new class extends Table
    {
        protected ?string $resource = QualityChildRecord::class;

        public function columns(): array
        {
            return [TextColumn::make('parent.name')->sortable()];
        }
    };
    $query = $table->apply(request: TableRequest::fromArray(['sort' => 'parent.name', 'direction' => 'desc']));

    expect($query->get()->pluck('parent_id')->all())->toBe([2, 2, 2, 1, 1]);
});

it('sorts ordered has-one relations using the chosen row including null and nested values', function (string $field, string $direction, bool $emptyLatest): void {
    if ($emptyLatest) {
        QualityChildRecord::query()->whereKey(2)->update(['label' => null]);
        QualityDescendantRecord::query()->where('child_id', 2)->delete();
    }

    $this->table->field = $field;
    $query = $this->table->apply(request: TableRequest::fromArray(['sort' => $field, 'direction' => $direction]));
    $records = $query->get();
    $expected = $field === 'latestChild.label' ? [1 => 'E', 2 => 'D', 3 => null] : [1 => 'Y', 2 => 'X', 3 => null];
    if ($emptyLatest) {
        $expected[1] = null;
    }

    foreach ($records as $record) {
        expect($record->getAttribute('_inertia_relation_sort'))->toBe($expected[$record->getKey()]);
    }

    if (! $emptyLatest) {
        $ids = $direction === 'asc' ? [3, 2, 1] : [1, 2, 3];
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $ids = $direction === 'asc' ? [2, 1, 3] : [3, 1, 2];
        }

        expect($records->modelKeys())->toBe($ids);
    }
})->with(['latestChild.label', 'latestChild.descendants.label'])->with(['asc', 'desc'])->with([false, true]);

it('preserves bound has-one ordering and one-of-many constraints', function (string $field, string $expected): void {
    $this->table->field = $field;
    $record = $this->table->apply(request: TableRequest::fromArray(['sort' => $field]))->get()->firstWhere('id', 1);

    expect($record->getAttribute('_inertia_relation_sort'))->toBe($expected);
})->with([['preferredChild.label', 'B'], ['lastChild.label', 'E']]);

it('qualifies ordered self-relation subqueries against physical and aliased parent queries', function (bool $aliased): void {
    QualityParentRecord::query()->insert(['id' => 4, 'name' => 'Zed', 'manager_id' => 1]);
    $this->table->field = 'latestReport.name';
    $query = QualityParentRecord::query();
    if ($aliased) {
        $query->from('quality_parents as p')->select('p.*');
    }

    $record = $this->table->apply($query, TableRequest::fromArray(['sort' => 'latestReport.name']))->get()->firstWhere('id', 1);

    expect($record->getAttribute('_inertia_relation_sort'))->toBe('Zed');
})->with([false, true]);

it('qualifies selection keys in joined export and action queries', function (): void {
    $this->table->joined = true;
    $this->table->action = Action::make('Run')->key('run')->handle(static fn (Model $model): mixed => $model->getKey());

    $query = resolve(ExportQueryBuilder::class)->apply($this->table, Export::make('Selected')->limitToSelectedRows(), TableRequest::fromArray([]), [1]);

    expect($query->get()->modelKeys())->toBe([1]);
    $this->postJson(route('table-builder.actions.execute'), ['table' => QualityReviewTable::class, 'action' => 'run', 'keys' => [1]])
        ->assertOk()->assertJson(['processed' => 1, 'results' => [1]]);
});

it('keeps selection and filters when sorting an export by a relation', function (): void {
    $this->table->field = 'children.label';
    $query = resolve(ExportQueryBuilder::class)->apply(
        $this->table, Export::make('Selected')->limitToSelectedRows()->limitToFilteredRows(),
        TableRequest::fromArray(['sort' => 'children.label', 'direction' => 'desc']), [1, 2],
    );

    expect($query->get()->modelKeys())->toBe([1, 2]);
});

it('searches and sorts qualified base columns and joined aliases', function (string $field, bool $joined, string $search, array $ids): void {
    $this->table->field = $field;
    $this->table->joined = $joined;

    $query = $this->table->apply(request: TableRequest::fromArray(['sort' => $field, 'search' => $search]));

    expect($query->get()->modelKeys())->toBe($ids);
})->with([
    ['quality_parents.name', false, 'Ada', [1]], ['child.label', true, 'B', [1]],
]);

it('renders and exports qualified column values from hydrated model attributes', function (bool $aliased): void {
    $table = new class extends Table
    {
        public bool $aliased = false;

        protected ?string $resource = QualityParentRecord::class;

        public function columns(): array
        {
            return [TextColumn::make($this->aliased ? 'p.name' : 'quality_parents.name')->sortable()->searchable()
                ->mapAs(static fn (?string $name): ?string => $name === null ? null : mb_strtoupper($name))];
        }

        public function query(): Builder
        {
            return $this->aliased ? parent::query()->from('quality_parents as p')->select('p.*') : parent::query();
        }
    };
    $table->aliased = $aliased;

    $field = $aliased ? 'p.name' : 'quality_parents.name';
    $payload = $table->results(request: TableRequest::fromArray(['search' => 'Ada', 'sort' => $field]));
    $export = TableExcelExport::fromRequest($table, Export::make(), ExportRequest::fromRequest(Request::create('/')));

    expect($payload['results']['data'][0][$field])->toBe('ADA')
        ->and($export->map(QualityParentRecord::query()->findOrFail(1)))->toBe(['ADA']);
})->with([false, true]);

it('keeps null relationship and nested attribute values distinct from SQL aliases', function (): void {
    $model = QualityParentRecord::query()->findOrFail(1);
    $model->setAttribute('profile', ['name' => null]);
    $model->setRelation('details', null);

    expect(TextColumn::make('manager.name')->resolveValue($model))->toBeNull()
        ->and(TextColumn::make('profile.name')->resolveValue($model))->toBeNull()
        ->and(TextColumn::make('details.name')->resolveValue($model))->toBeNull()
        ->and(TextColumn::make('quality_parents.name')->resolveValue($model))->toBe('Ada');
});

it('uses one fresh definition snapshot for the complete public results operation', function (): void {
    $payload = $this->table->results();

    expect($this->table->columnCalls)->toBe(1)
        ->and($payload['hasSearch'])->toBeTrue()
        ->and($payload['hasToggleableColumns'])->toBeFalse();
    $this->table->columnSearchable = false;
    $this->table->columnToggleable = true;

    $payload = $this->table->results();
    expect($this->table->columnCalls)->toBe(2)
        ->and($payload['hasSearch'])->toBeFalse()
        ->and($payload['hasToggleableColumns'])->toBeTrue();
});

it('releases the definition snapshot when query execution throws', function (): void {
    expect(fn (): array => $this->table->results(query: QualityParentRecord::query()->where('quality_parents.missing_column', 1)))
        ->toThrow(QueryException::class);
    $this->table->results();

    expect($this->table->columnCalls)->toBe(2);
});

it('ignores empty nested paths when choosing the collection sort value', function (): void {
    QualityChildRecord::query()->insert(['id' => 7, 'parent_id' => 1, 'label' => 'Empty', 'active' => true]);
    $this->table->field = 'children.descendants.label';
    $query = $this->table->apply(request: TableRequest::fromArray(['sort' => $this->table->field]));

    expect($query->get()->modelKeys())->toBe($query->getConnection()->getDriverName() === 'pgsql' ? [1, 2, 3] : [3, 1, 2]);
});

it('rejects malformed action selections before running the handler', function (array $selection): void {
    $this->table->action = Action::make('Run')->key('run')->asBulkAction()->handle(static fn (): never => throw new LogicException('Must not execute'));
    $this->postJson(route('table-builder.actions.execute'), ['table' => QualityReviewTable::class, 'action' => 'run', ...$selection])->assertUnprocessable();
})->with([
    [['keys' => [[1, 2]]]], [['keys' => [true]]], [['keys' => [1.2]]], [['keys' => '1']],
    [['selectionMode' => 'everything']], [['selectionMode' => ['all']]],
]);

it('reports action failures and sends a safe message for JSON and Inertia', function (bool $inertia): void {
    Exceptions::fake();
    $this->table->action = Action::make('Run')->key('run')->handle(static fn (): never => throw new RuntimeException('Sensitive SQL details'));
    $data = ['table' => QualityReviewTable::class, 'action' => 'run', 'keys' => [1]];

    if ($inertia) {
        $this->from('/records')->post(route('table-builder.actions.execute'), $data, ['X-Inertia' => 'true'])
            ->assertRedirect('/records')->assertSessionHasErrors(['table_action' => 'Action failed.']);
    } else {
        $this->postJson(route('table-builder.actions.execute'), $data)->assertStatus(500)->assertJson(['message' => 'Action failed.'])->assertDontSee('Sensitive SQL details');
    }

    Exceptions::assertReported(static fn (RuntimeException $exception): bool => $exception->getMessage() === 'Sensitive SQL details');
})->with([false, true]);

it('passes the real exception to a custom error callback', function (): void {
    Exceptions::fake();
    $this->table->action = Action::make('Run')->key('run')->handle(static fn (): never => throw new RuntimeException('Internal details'))
        ->error(static function (Throwable $exception, array $payload): array {
            expect($exception->getMessage())->toBe('Internal details');

            return [...$payload, 'message' => 'Please retry later.'];
        });
    $this->postJson(route('table-builder.actions.execute'), ['table' => QualityReviewTable::class, 'action' => 'run', 'keys' => [1]])
        ->assertStatus(500)->assertJson(['message' => 'Please retry later.']);
});

it('can discard aggregate results while retaining chunk-local after-hook results', function (): void {
    $chunks = [];
    $this->table->action = Action::make('Run')->key('run')->asBulkAction(2)->collectResults(false)
        ->handle(static fn (Model $model): mixed => $model->getKey())
        ->after(static function (array $models, array $results) use (&$chunks): void {
            $chunks[] = $results;
        });
    $this->postJson(route('table-builder.actions.execute'), ['table' => QualityReviewTable::class, 'action' => 'run', 'selectionMode' => 'all'])
        ->assertOk()->assertJson(['processed' => 3, 'skipped' => 0, 'results' => []]);

    expect($chunks)->toBe([[1, 2], [3]]);
});

it('supports zero-argument, fixed-argument and variadic callbacks without scalar coercion', function (): void {
    expect(Callback::invoke(static fn (): string => 'zero', 1, 2))->toBe('zero')
        ->and(Callback::invoke(static fn (int $first): int => $first, 1, 2))->toBe(1)
        ->and(Callback::invoke(static fn (int ...$values): array => $values, 1, 2, 3))->toBe([1, 2, 3]);
    expect(fn (): mixed => Callback::invoke(static fn (int $value): int => $value, '1'))->toThrow(TypeError::class);
});
