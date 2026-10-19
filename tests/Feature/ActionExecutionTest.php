<?php

declare(strict_types=1);
use Illuminate\Validation\ValidationException;
use Wtroiano\TableBuilder\Tests\Fixtures\ActionTable;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'team' => 'red'], ['id' => 2, 'team' => 'blue'],
        ['id' => 3, 'team' => 'blue'], ['id' => 4, 'team' => 'red'],
    ]);
    $this->table = ActionTable::make();
    app()->instance(ActionTable::class, $this->table);
});

it('runs selected-record hooks once with the complete selection including skipped records', function (): void {
    $this->postJson(route('table-builder.actions.execute'), ['table' => ActionTable::class, 'action' => 'run', 'keys' => [1, 2, 3, 4]])
        ->assertOk()->assertJson(['processed' => 2, 'skipped' => 2, 'results' => [10, 40]]);

    expect($this->table->events)->toBe([
        ['before', [1, 2, 3, 4]], ['execute', 1], ['execute', 4], ['after', [1, 2, 3, 4], [10, 40]],
    ]);
});

it('runs select-all hooks per database chunk and gives after hooks only that chunks results', function (string $strategy): void {
    $this->table->strategy = $strategy;
    $this->postJson(route('table-builder.actions.execute'), ['table' => ActionTable::class, 'action' => 'run', 'selectionMode' => 'all'])
        ->assertOk()->assertJson(['processed' => 2, 'skipped' => 2, 'results' => [10, 40]]);

    expect($this->table->events)->toBe([
        ['before', [1, 2]], ['execute', 1], ['after', [1, 2], [10]],
        ['before', [3, 4]], ['execute', 4], ['after', [3, 4], [40]],
    ]);
})->with(['chunk', 'chunkById']);

it('skips ineligible actions while preserving before and after hooks', function (string $guard): void {
    $this->table->{$guard} = $guard !== 'authorized';
    $this->postJson(route('table-builder.actions.execute'), ['table' => ActionTable::class, 'action' => 'run', 'keys' => [1, 2]])
        ->assertOk()->assertJson(['processed' => 0, 'skipped' => 2, 'results' => []]);

    expect($this->table->events)->toBe([['before', [1, 2]], ['after', [1, 2], []]]);
})->with(['disabled', 'hidden']);

it('rejects unauthorized actions before lifecycle hooks or selection queries', function (): void {
    $this->table->authorized = false;
    $this->postJson(route('table-builder.actions.execute'), ['table' => ActionTable::class, 'action' => 'run', 'keys' => [1]])->assertForbidden();
    expect($this->table->events)->toBe([]);
});

it('keeps partial progress when a handler throws and stops the remaining lifecycle', function (string $mode): void {
    $this->table->failAt = 4;
    $this->postJson(route('table-builder.actions.execute'), ['table' => ActionTable::class, 'action' => 'run', 'keys' => [1, 2, 3, 4], 'selectionMode' => $mode])
        ->assertStatus(500)->assertJson(['processed' => 1, 'skipped' => 2, 'message' => 'Action failed.']);

    expect(array_last($this->table->events))->toBe(['execute', 4]);
})->with(['page', 'all']);

it('preserves progress when a lifecycle hook throws', function (bool $before): void {
    $this->table->failBefore = $before;
    $this->table->failAfter = ! $before;
    $this->postJson(route('table-builder.actions.execute'), ['table' => ActionTable::class, 'action' => 'run', 'keys' => [1, 2]])
        ->assertStatus(500)->assertJson([
            'processed' => $before ? 0 : 1,
            'skipped' => $before ? 0 : 1,
            'message' => 'Action failed.',
        ]);
})->with([true, false]);

it('lets HTTP exceptions retain their status', function (): void {
    $this->table->failAt = 1;
    $this->table->httpFailure = true;
    $this->postJson(route('table-builder.actions.execute'), ['table' => ActionTable::class, 'action' => 'run', 'keys' => [1]])->assertStatus(409);
});

it('rejects an empty selection before invoking lifecycle hooks', function (string $mode): void {
    if ($mode === 'all') {
        PipelineRecord::query()->delete();
    }

    $this->postJson(route('table-builder.actions.execute'), ['table' => ActionTable::class, 'action' => 'run', 'keys' => [], 'selectionMode' => $mode])->assertNotFound();
    expect($this->table->events)->toBe([]);
})->with(['page', 'all']);

it('retains Inertia success and error redirects', function (bool $fails): void {
    $this->table->failBefore = $fails;
    $response = $this->from('/records')->post(route('table-builder.actions.execute'), [
        'table' => ActionTable::class, 'action' => 'run', 'keys' => [1],
    ], ['X-Inertia' => 'true']);
    $response->assertRedirect('/records');

    if ($fails) {
        $response->assertSessionHasErrors('table_action');
    } else {
        $response->assertSessionHas('table_action.processed', 1);
    }
})->with([false, true]);

it('preserves handler validation errors and stops the remaining lifecycle', function (bool $inertia): void {
    $this->table->authorizeRecord = static function (PipelineRecord $record): void {
        if ($record->getKey() === 4) {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }
    };
    $input = ['table' => ActionTable::class, 'action' => 'run', 'keys' => [1, 4]];

    if ($inertia) {
        $this->from('/records')->post(route('table-builder.actions.execute'), $input, ['X-Inertia' => 'true'])
            ->assertRedirect('/records')->assertSessionHasErrors(['reason' => 'A reason is required.'])
            ->assertSessionMissing('table_action');
    } else {
        $this->postJson(route('table-builder.actions.execute'), $input)->assertUnprocessable()
            ->assertJsonValidationErrors(['reason' => 'A reason is required.']);
    }

    expect($this->table->events)->toBe([['before', [1, 4]], ['execute', 1]]);
})->with([false, true]);
