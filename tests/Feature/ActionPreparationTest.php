<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\ActionPreparationPipeline;
use Wtroiano\TableBuilder\Tests\Fixtures\PreparationTable;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'code' => 'first'], ['id' => 2, 'code' => 'second'], ['id' => 3, 'code' => 'third'],
    ]);
    $this->table = PreparationTable::make();
    $single = Action::make('Single')->key('single')->handle(static fn (Model $model): mixed => $model->getKey());
    $bulk = Action::make('Bulk')->key('bulk')->asBulkAction(2)->handle(static fn (Model $model): mixed => $model->getKey())
        ->error(function (Throwable $exception, array $payload): array {
            $this->table->events[] = 'error';

            return $payload;
        });
    $this->table->definitions = [$single, $bulk, Action::make('Missing handler')->key('no-handler')];
    app()->instance(PreparationTable::class, $this->table);
});

it('rejects invalid tables before resolving actions', function (string $table): void {
    $this->postJson(route('table-builder.actions.execute'), ['table' => $table, 'action' => 'single', 'keys' => ['first']])->assertStatus(422);
    expect($this->table->events)->toBe([]);
})->with(['', stdClass::class, 'MissingTable']);

it('rejects unknown actions and missing handlers before querying', function (string $action): void {
    $this->postJson(route('table-builder.actions.execute'), ['table' => PreparationTable::class, 'action' => $action, 'keys' => ['first']])->assertNotFound();
    expect($this->table->events)->toBe([]);
})->with(['missing', 'no-handler']);

it('validates bulk selection against the raw requested key count', function (array $keys, string $mode): void {
    $this->postJson(route('table-builder.actions.execute'), ['table' => PreparationTable::class, 'action' => 'single', 'keys' => $keys, 'selectionMode' => $mode])->assertStatus(422);
    expect($this->table->events)->toBe([]);
})->with([
    [['first', 'second'], 'page'], [['', 'first'], 'page'], [[null, 'first'], 'page'], [[], 'all'],
]);

it('normalizes empty keys after validation and queries using the configured selection key', function (): void {
    $this->postJson(route('table-builder.actions.execute'), ['table' => PreparationTable::class, 'action' => 'bulk', 'keys' => ['', null, 'second']])
        ->assertOk()->assertJson(['processed' => 1, 'results' => [2]]);
});

it('streams select-all records without issuing an unbounded selection query', function (): void {
    $selects = [];
    DB::listen(static function (QueryExecuted $query) use (&$selects): void {
        if (str_starts_with(mb_strtolower($query->sql), 'select')) {
            $selects[] = mb_strtolower($query->sql);
        }
    });
    $this->postJson(route('table-builder.actions.execute'), ['table' => PreparationTable::class, 'action' => 'bulk', 'selectionMode' => 'all'])
        ->assertOk()->assertJson(['processed' => 3, 'results' => [1, 2, 3]]);

    expect($selects)->not->toBeEmpty();
    foreach ($selects as $sql) {
        expect($sql)->toContain('limit 2');
    }
});

it('keeps selection query failures outside action error callbacks', function (): void {
    $this->withoutExceptionHandling();
    $this->table->failQuery = true;

    expect(fn () => $this->postJson(route('table-builder.actions.execute'), ['table' => PreparationTable::class, 'action' => 'bulk', 'keys' => ['first']]))
        ->toThrow(RuntimeException::class, 'Query failed');
    expect($this->table->events)->toBe(['query']);
});

it('keeps select-all query failures inside action error callbacks', function (): void {
    $this->table->failQuery = true;
    $this->postJson(route('table-builder.actions.execute'), ['table' => PreparationTable::class, 'action' => 'bulk', 'selectionMode' => 'all'])
        ->assertStatus(500)->assertJson(['message' => 'Action failed.', 'processed' => 0]);
    expect($this->table->events)->toBe(['query', 'error']);
});

it('prepares select-all actions without creating a record query', function (): void {
    $this->table->failQuery = true;
    $request = Request::create('/', 'POST', [
        'table' => PreparationTable::class, 'action' => 'bulk', 'selectionMode' => 'all',
    ]);
    $request->setUserResolver(static fn (): mixed => auth()->user());

    $prepared = resolve(ActionPreparationPipeline::class)->prepare($request);

    expect($prepared->isAllSelection)->toBeTrue()
        ->and($prepared->models)->toBe([])
        ->and($this->table->events)->toBe([]);
});
