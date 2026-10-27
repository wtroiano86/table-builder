<?php

declare(strict_types=1);

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\ExcelServiceProvider;
use Maatwebsite\Excel\Jobs\AppendQueryToSheet;
use Maatwebsite\Excel\Jobs\CloseSheet;
use Maatwebsite\Excel\Jobs\QueueExport;
use Maatwebsite\Excel\Jobs\StoreQueuedExport;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Tests\Fixtures\ActionTable;
use Wtroiano\TableBuilder\Tests\Fixtures\ExportFlowTable;
use Wtroiano\TableBuilder\Tests\Fixtures\PayloadTable;
use Wtroiano\TableBuilder\Tests\Fixtures\TransportTable;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

it('preserves handler policy denials and stops the action lifecycle', function (int $status, bool $inertia): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'team' => 'red'], ['id' => 2, 'team' => 'red'],
    ]);
    Gate::define('review-update', static fn (mixed $user, Model $record): Response => $record->getKey() === 1
        ? Response::allow()
        : Response::denyWithStatus($status));
    $table = ActionTable::make();
    $table->authorizeRecord = static fn (Model $record): Response => Gate::authorize('review-update', $record);

    app()->instance(ActionTable::class, $table);
    $input = ['table' => ActionTable::class, 'action' => 'run', 'keys' => [1, 2]];

    $response = $inertia
        ? $this->post(route('table-builder.actions.execute'), $input, ['X-Inertia' => 'true'])
        : $this->postJson(route('table-builder.actions.execute'), $input);

    $response->assertStatus($status);

    expect($table->events)->toBe([['before', [1, 2]], ['execute', 1]]);
})->with([403, 404])->with([false, true]);

it('routes every job in a real export chain to the configured queue', function (): void {
    app()->register(ExcelServiceProvider::class);
    DatabaseFixtures::pipelineRecords([['id' => 1, 'name' => 'Ada'], ['id' => 2, 'name' => 'Grace']]);
    config()->set('excel.exports.chunk_size', 1);
    Bus::fake();
    $table = ExportFlowTable::make();
    $table->definition = Export::make('Report')->filename('report.xlsx')->key('report')->queue('report.xlsx', 'local');

    app()->instance(ExportFlowTable::class, $table);
    Export::defaultQueueName('reports');

    try {
        $this->postJson(route('table-builder.exports.execute'), ['table' => ExportFlowTable::class, 'export' => 'report'])
            ->assertOk();
        $job = Bus::dispatched(QueueExport::class)->sole();
        expect($job->queue)->toBe('reports');

        foreach ([AppendQueryToSheet::class, AppendQueryToSheet::class, CloseSheet::class, StoreQueuedExport::class] as $nextClass) {
            $job->dispatchNextJobInChain();
            $job = Bus::dispatched($nextClass)->last();
            expect($job)->toBeInstanceOf($nextClass)
                ->and($job->queue)->toBe('reports');
        }

        expect($job->chained)->toBe([]);
    } finally {
        Export::defaultQueueName();
    }
});

it('restarts offset pagination when sorting changes', function (PaginationType $mode, bool $named): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'name' => 'Ada'], ['id' => 2, 'name' => 'Grace'], ['id' => 3, 'name' => 'Linus'],
    ]);
    $table = PayloadTable::make()->paginationMode($mode);
    if ($named) {
        $table = $table->named('people');
    }

    $values = ['page' => 3, 'sort' => 'name'];
    $query = $table->navigation(TableRequest::fromArray($named ? ['people' => $values] : $values))->sort('name', 'desc');
    $request = TableRequest::fromRequest(Request::create('/?'.http_build_query($query)));

    expect($table->state($request)->page())->toBe(1)
        ->and($table->paginate(request: $request)->getCollection()->modelKeys())->toBe([3]);
})->with([PaginationType::Standard, PaginationType::Simple])->with([false, true]);

it('clears active and default cursors through sort navigation URL round trips', function (bool $named, bool $defaultCursor): void {
    DatabaseFixtures::relations([
        ['id' => 1, 'name' => 'Ada', 'manager_id' => null],
        ['id' => 2, 'name' => 'Grace', 'manager_id' => null],
        ['id' => 3, 'name' => 'Linus', 'manager_id' => null],
    ], []);
    $table = TransportTable::make();
    if ($named) {
        $table = $table->named('people');
    }

    $firstValues = ['sort' => 'name'];
    $first = $table->paginate(request: TableRequest::fromArray($named ? ['people' => $firstValues] : $firstValues));
    $cursor = $first->nextCursor()->encode();
    $table->defaults = $defaultCursor ? ['cursor' => $cursor, 'page' => 3] : [];
    $values = ['cursor' => $cursor, 'page' => 3, 'sort' => 'name', 'columns' => ['name'], 'sticky' => []];
    $query = $table->navigation(TableRequest::fromArray($named ? ['people' => $values] : $values))->sort('name', 'desc');
    $request = TableRequest::fromRequest(Request::create('/?'.http_build_query($query)));
    $state = $table->state($request);

    expect($state->page())->toBe(1)->and($state->cursor())->toBeNull()
        ->and($state->columns())->toBe(['name'])->and($state->sticky())->toBe([])
        ->and($state->hasStickyOverride())->toBeTrue()
        ->and($table->paginate(request: $request)->getCollection()->modelKeys())->toBe([3]);
})->with([false, true])->with([false, true]);
