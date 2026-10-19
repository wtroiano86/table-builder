<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Tests\Fixtures\ExportRedirect;
use Wtroiano\TableBuilder\Tests\Fixtures\QualityParentRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\QueryLifecycleTable;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::relations([
        ['id' => 1, 'name' => 'Ada', 'manager_id' => null], ['id' => 2, 'name' => 'Ada', 'manager_id' => 1],
        ['id' => 3, 'name' => 'Grace', 'manager_id' => 1], ['id' => 4, 'name' => 'Grace', 'manager_id' => 2],
    ], [
        ['id' => 1, 'parent_id' => 1, 'label' => 'B', 'active' => true],
        ['id' => 2, 'parent_id' => 2, 'label' => 'C', 'active' => true],
        ['id' => 3, 'parent_id' => 3, 'label' => 'A', 'active' => false],
    ]);
    $this->table = QueryLifecycleTable::make();
    app()->instance(QueryLifecycleTable::class, $this->table);
    Excel::fake();
});

it('streams every eligible model in primary-key order despite existing ordering and mutations', function (bool $aliased, bool $scoped, bool $deletes): void {
    $this->table->aliased = $aliased;
    $this->table->ordered = true;
    $this->table->scoped = $scoped;
    $this->table->deleteRecords = $deletes;

    $ids = $scoped ? [1, 2, 3] : [1, 2, 3, 4];
    $this->postJson(route('table-builder.actions.execute'), ['table' => QueryLifecycleTable::class, 'action' => 'run', 'selectionMode' => 'all'])
        ->assertOk()->assertJson(['processed' => count($ids), 'results' => $ids]);

    expect($this->table->queryCalls)->toBe(1)
        ->and(QualityParentRecord::query()->count())->toBe($deletes ? 4 - count($ids) : 4);
})->with([
    [false, false, false], [true, false, false], [false, true, false], [true, true, false],
    [false, false, true], [true, true, true],
]);

it('qualifies aliases in selected actions and filtered selected exports', function (): void {
    $this->table->aliased = true;
    $this->postJson(route('table-builder.actions.execute'), ['table' => QueryLifecycleTable::class, 'action' => 'run', 'keys' => [1, 3]])
        ->assertOk()->assertJson(['processed' => 2, 'results' => [1, 3]]);
    $this->table->definition = Export::make('Selected', 'selected.xlsx')->key('records')->limitToFilteredRows()->limitToSelectedRows()->asDownload(false);
    Excel::shouldReceive('store')->once()->andReturnUsing(static function (TableExcelExport $export): bool {
        expect($export->query()->get()->modelKeys())->toBe([3]);

        return true;
    });
    $this->postJson(route('table-builder.exports.execute'), ['table' => QueryLifecycleTable::class, 'export' => 'records', 'state' => ['search' => 'Grace', 'sort' => 'name'], 'keys' => [1, 3]])->assertOk();
});

it('keeps bounded select-all actions inside the original resource dataset during mutations', function (?int $limit, ?int $offset, bool $aliased, bool $deletes, array $expected): void {
    foreach ([1 => 'Alpha', 2 => 'Bravo', 3 => 'Charlie', 4 => 'Delta'] as $id => $name) {
        QualityParentRecord::query()->whereKey($id)->update(['name' => $name]);
    }

    $this->table->ordered = true;
    $this->table->limit = $limit;
    $this->table->offset = $offset;
    $this->table->aliased = $aliased;
    $this->table->partial = $aliased;
    $this->table->actionChunkSize = 1;
    $this->table->deleteRecords = $deletes;
    $this->table->renameRecords = ! $deletes;

    $this->postJson(route('table-builder.actions.execute'), ['table' => QueryLifecycleTable::class, 'action' => 'run', 'selectionMode' => 'all'])
        ->assertOk()->assertJson(['processed' => count($expected), 'results' => $expected]);

    $remaining = QualityParentRecord::query()->orderBy('id')->pluck('name', 'id')->all();
    foreach ([1 => 'Alpha', 2 => 'Bravo', 3 => 'Charlie', 4 => 'Delta'] as $id => $name) {
        if (in_array($id, $expected, true)) {
            expect($remaining[$id] ?? null)->toBe($deletes ? null : 'Changed');
        } else {
            expect($remaining[$id])->toBe($name);
        }
    }
})->with([
    [2, null, false, false, [3, 4]],
    [2, 2, true, true, [1, 2]],
    [null, 1, true, false, [1, 2, 3]],
    [2, null, true, true, [3, 4]],
]);

it('supports qualified and relation search and sorting against an aliased base', function (string $field, string $search, array $ids): void {
    $this->table->aliased = true;
    $this->table->field = $field;

    $query = $this->table->apply(request: TableRequest::fromArray(['search' => $search, 'sort' => $field]));

    expect($query->get()->modelKeys())->toBe($ids);
})->with([
    ['name', 'Ada', [1, 2]], ['quality_parents.name', 'Ada', [1, 2]], ['p.name', 'Ada', [1, 2]],
    ['manager.name', 'Ada', [2, 3, 4]], ['manager.manager.name', 'Ada', [4]], ['children.label', 'B', [1]],
]);

it('keeps cursor callbacks and model identity for aliased partial projections', function (): void {
    $this->table->aliased = true;
    $this->table->partial = true;

    $calls = 0;
    $this->table->afterQuery = static function (Collection $records) use (&$calls): void {
        $calls++;
        $records->each(static fn (Model $record): Model => $record->setAttribute('name', 'Redacted'));
    };
    $payload = $this->table->results(request: TableRequest::fromArray(['sort' => 'name']));

    expect($calls)->toBe(1)->and($payload['results']['data'][0]['name'])->toBe('Redacted')
        ->and($payload['results']['data'][0]['_primary_key'])->toBe(1)->and($payload['results']['data'][0]['id'])->toBe(1);
});

it('keeps cursor relation ordering and constraints with aliased base queries', function (): void {
    $this->table->aliased = true;
    $this->table->field = 'manager.name';

    $first = $this->table->paginate(request: TableRequest::fromArray(['sort' => 'manager.name']));
    $second = $this->table->paginate(request: TableRequest::fromArray(['sort' => 'manager.name', 'cursor' => $first->nextCursor()->encode()]));

    expect($first->getCollection()->modelKeys())->toBe([2, 3])->and($second->getCollection()->modelKeys())->toBe([4, 1]);
});

it('preserves cursor identity with raw select lists without duplicating existing keys', function (string $projection): void {
    $query = $this->table->query();
    $grammar = $query->getQuery()->getGrammar();
    $query->selectRaw(str_replace(['{all}', '{key}'], [$grammar->wrap('quality_parents.*'), $grammar->wrap('quality_parents.id')], $projection));

    $first = $this->table->paginate(clone $query);
    $second = $this->table->paginate(clone $query, TableRequest::fromArray(['cursor' => $first->nextCursor()->encode()]));

    expect($first->getCollection()->modelKeys())->toBe([1, 2])
        ->and($second->getCollection()->modelKeys())->toBe([3, 4]);
})->with([
    'wildcard before expression' => ["quality_parents.*, coalesce(name, 'Fallback') as label"],
    'wildcard after expression' => ["coalesce(name, 'name, id, other') as label, {all}"],
    'quoted key in select list' => ['name, {key}'],
    'key alias in select list' => ['name, quality_parents.id as id'],
    'nested function with key argument' => ['coalesce(null, coalesce(null, id, 0), 0) as priority'],
    'quoted comma and key text' => ["'name, id, other' as label"],
    'escaped quote and key text' => ["'can''t, id, other' as label"],
    'key text literal' => ["'id' as label"],
    'unicode before wildcard' => ["'équipe, id' as label, {all}"],
]);

it('reports storage failures without successful flashes or dialogs', function (bool $inertia): void {
    Exceptions::fake();
    $this->table->definition = Export::make('Records', 'records.xlsx')->key('records')->redirectBackWithDialog('Preparing', 'A success dialog');
    Excel::shouldReceive('store')->once()->andReturn(false);
    $input = ['table' => QueryLifecycleTable::class, 'export' => 'records'];
    if ($inertia) {
        $this->from('/records')->post(route('table-builder.exports.execute'), $input, ['X-Inertia' => 'true'])
            ->assertRedirect('/records')->assertSessionHasErrors(['table_export' => 'Export could not be stored.'])
            ->assertSessionMissing('table_export')->assertSessionMissing('table_export_dialog');
    } else {
        $this->postJson(route('table-builder.exports.execute'), $input)->assertStatus(500)
            ->assertExactJson(['ok' => false, 'status' => 'error', 'export' => 'records', 'message' => 'Export could not be stored.']);
    }

    Exceptions::assertReported(RuntimeException::class);
})->with([false, true]);

it('reuses a prepared query while keeping each synchronous query call isolated', function (): void {
    Excel::shouldReceive('store')->once()->andReturnUsing(static function (TableExcelExport $export): bool {
        expect($export->query()->where('quality_parents.id', 2)->get()->modelKeys())->toBe([2])
            ->and($export->query()->get()->modelKeys())->toBe([1, 2, 3, 4]);

        return true;
    });
    $this->postJson(route('table-builder.exports.execute'), ['table' => QueryLifecycleTable::class, 'export' => 'records'])->assertOk();
    expect($this->table->queryCalls)->toBe(1);
});

it('retains custom query changes when a synchronous custom exporter falls through', function (): void {
    $this->table->definition = Export::make('Records', 'records.xlsx')->key('records')->asDownload(false)
        ->using(static function (QueryLifecycleTable $table, Export $definition, mixed $request, Builder $query): mixed {
            $query->where('quality_parents.id', 3);

            return null;
        });
    Excel::shouldReceive('store')->once()->andReturnUsing(static function (TableExcelExport $export): bool {
        expect($export->query()->get()->modelKeys())->toBe([3]);

        return true;
    });
    $this->postJson(route('table-builder.exports.execute'), ['table' => QueryLifecycleTable::class, 'export' => 'records'])->assertOk();
    expect($this->table->queryCalls)->toBe(1);
});

it('defers queued preparation and reconstructs filters from serializable inputs', function (): void {
    $this->table->definition = Export::make('Records', 'records.xlsx')->key('records')->limitToFilteredRows()->queue();
    $this->postJson(route('table-builder.exports.execute'), ['table' => QueryLifecycleTable::class, 'export' => 'records', 'state' => ['search' => 'Ada']])->assertOk();
    expect($this->table->queryCalls)->toBe(0);
    Excel::assertQueued('records.xlsx', static function (TableExcelExport $export): bool {
        $restored = unserialize(serialize($export));
        app()->forgetInstance(QueryLifecycleTable::class);
        expect($restored->query()->get()->modelKeys())->toBe([1, 2]);

        return true;
    });
});

it('supports callable-array redirects while preserving route configuration arrays', function (bool $callable): void {
    Route::get('/completed/records', static fn (): string => 'Done')->name('review.completed');
    $this->table->definition = Export::make('Records', 'records.xlsx')->key('records')->asDownload(false)
        ->redirect($callable ? ExportRedirect::CALLBACK : ['route' => 'review.completed']);
    Excel::shouldReceive('store')->once()->andReturn(true);
    $this->from('/records')->post(route('table-builder.exports.execute'), ['table' => QueryLifecycleTable::class, 'export' => 'records'], ['X-Inertia' => 'true'])
        ->assertRedirect('/completed/records')->assertSessionHas('table_export.status', 'processing');
})->with([false, true]);

it('preserves limited resource rows through forward and backward cursor navigation', function (int $offset, bool $computed): void {
    $query = $this->table->query();
    if ($computed) {
        $query->selectRaw('quality_parents.*, case when name = ? then 1 else 0 end as priority', ['Grace'])
            ->orderBy('priority', 'desc');
    } else {
        $query->orderBy('name', 'desc');
    }

    $query->orderBy('id', 'desc')->offset($offset)->limit(3);
    $expected = $offset === 0 ? [4, 3, 2] : [3, 2, 1];
    $cursor = null;
    $forward = [];
    $pages = [];

    do {
        $page = $this->table->paginate(clone $query, TableRequest::fromArray(['perPage' => 1, 'cursor' => $cursor]));
        $forward = [...$forward, ...$page->getCollection()->modelKeys()];
        $pages[] = $page;
        $cursor = $page->nextCursor()?->encode();
        expect(count($pages))->toBeLessThanOrEqual(3);
    } while ($cursor !== null);

    expect($forward)->toBe($expected);
    $backward = array_last($pages)->getCollection()->modelKeys();
    $cursor = array_last($pages)->previousCursor()?->encode();

    while ($cursor !== null) {
        $page = $this->table->paginate(clone $query, TableRequest::fromArray(['perPage' => 1, 'cursor' => $cursor]));
        $backward = [...$page->getCollection()->modelKeys(), ...$backward];
        $cursor = $page->previousCursor()?->encode();
        expect(count($backward))->toBeLessThanOrEqual(3);
    }

    expect($backward)->toBe($expected);
})->with([0, 1])->with([false, true]);

it('preserves scoped ordering and constraints in synchronous and reconstructed exports', function (bool $queued): void {
    $this->table->scoped = true;
    $this->table->definition = Export::make('Records', 'records.xlsx')->key('records')->asDownload(false);

    $verify = static function (TableExcelExport $export): bool {
        expect($export->query()->get()->modelKeys())->toBe([3, 1, 2])
            ->and($export->query()->getBindings())->toBe([3]);

        return true;
    };

    if ($queued) {
        $this->table->definition->queue();
    } else {
        Excel::shouldReceive('store')->once()->andReturnUsing($verify);
    }

    $this->postJson(route('table-builder.exports.execute'), ['table' => QueryLifecycleTable::class, 'export' => 'records'])->assertOk();

    if ($queued) {
        Excel::assertQueued('records.xlsx', static fn (TableExcelExport $export): bool => $verify(unserialize(serialize($export))));
    }
})->with([false, true]);
