<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Tests\Fixtures\PayloadTable;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'name' => 'Ada'], ['id' => 2, 'name' => 'Grace'], ['id' => 3, 'name' => 'Linus'],
    ]);
});

it('preserves other table filters and page controls through pagination links', function (PaginationType $mode, bool $named): void {
    $table = PayloadTable::make()->paginationMode($mode);
    if ($named) {
        $table = $table->named('people');
    }

    $state = ['sort' => 'name', 'direction' => 'desc'];
    $query = ['orders' => ['search' => 'pending', 'page' => 4], 'tab' => 'active'];
    $query = array_replace($query, $named ? ['people' => $state] : $state);

    $request = Request::create('/records', parameters: $query);
    app()->instance('request', $request);
    $first = $table->paginate(request: TableRequest::fromRequest($request));
    $nextRequest = Request::create($first->nextPageUrl());
    $second = $table->paginate(request: TableRequest::fromRequest($nextRequest));

    expect($nextRequest->query('orders'))->toBe(['search' => 'pending', 'page' => '4'])
        ->and($nextRequest->query('tab'))->toBe('active')
        ->and($first->getCollection()->modelKeys())->toBe([3])
        ->and($second->getCollection()->modelKeys())->toBe([2]);
    $previousRequest = Request::create($second->previousPageUrl());
    expect($previousRequest->query('orders'))->toBe(['search' => 'pending', 'page' => '4']);
})->with(PaginationType::cases())->with([false, true]);

it('replaces stale current-table parameters with explicitly supplied normalized state', function (bool $named, bool $legacy): void {
    $table = PayloadTable::make();
    if ($named) {
        $table = $table->named('people');
    }

    $stale = ['search' => 'missing', 'direction' => 'desc', 'perPage' => 99, 'page' => 8, 'cursor' => 'stale'];
    $query = ['tab' => 'active'];
    $query = array_replace($query, $named || $legacy ? [$table->name() => $stale] : $stale);

    app()->instance('request', Request::create('/records', parameters: $query));
    $page = $table->paginate(request: TableRequest::fromArray([]));
    $request = Request::create($page->nextPageUrl());
    $state = $table->stateFromRequest($request);

    expect($state->search())->toBeNull()->and($state->cursor())->toBeNull()
        ->and($state->direction())->toBe('asc')->and($state->perPage())->toBe(1)
        ->and($state->page())->toBe(2)->and($request->query('tab'))->toBe('active');
})->with([[false, false], [false, true], [true, false]]);
