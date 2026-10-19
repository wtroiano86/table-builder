<?php

declare(strict_types=1);

use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Tables\TablePayloadBuilder;
use Wtroiano\TableBuilder\Tests\Fixtures\PayloadTable;
use Wtroiano\TableBuilder\Tests\Fixtures\SerializationRecord;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'name' => 'Ada'], ['id' => 2, 'name' => 'Grace'], ['id' => 3, 'name' => 'Linus'],
    ]);
});

it('includes the active sort direction in results and definition payloads', function (string $direction, string $first): void {
    $table = PayloadTable::make();
    $request = TableRequest::fromArray(['sort' => 'name', 'direction' => $direction]);
    $payload = $table->results(request: $request);

    expect($payload['state']['direction'])->toBe($direction)
        ->and($payload['state']['sort'])->toBe('name')
        ->and($payload['results']['data'][0]['name'])->toBe($first)
        ->and($table->payload($request)['state']['direction'])->toBe($direction);
})->with([['asc', 'Ada'], ['desc', 'Linus']]);

it('serializes rows once without evaluating unrelated model attributes', function (PaginationType $mode): void {
    $table = PayloadTable::make()->paginationMode($mode);
    $table->recordClass = SerializationRecord::class;

    $request = TableRequest::fromArray(['sort' => 'name', 'perPage' => 2]);
    $payload = $table->results(request: $request);

    expect(array_column($payload['results']['data'], 'name'))->toBe(['Ada', 'Grace'])
        ->and($table->rowCalls)->toBe(2)
        ->and($payload['results']['next_page_url'])->toBeString();
})->with(PaginationType::cases());

it('preserves native pagination metadata and leaves the paginator collection intact', function (PaginationType $mode, bool $empty): void {
    $table = PayloadTable::make()->paginationMode($mode);
    $query = $table->query();
    if ($empty) {
        $query->whereRaw('1 = 0');
    }

    $paginator = $table->paginate($query);
    $original = $paginator->getCollection();
    $native = $paginator->toArray();
    unset($native['data']);
    $payload = resolve(TablePayloadBuilder::class)->buildResults($table, $table->state(), $paginator);

    foreach ($native as $key => $value) {
        expect($payload['results'][$key])->toBe($value);
    }

    expect($paginator->getCollection())->toBe($original)
        ->and($payload['results']['on_first_page'])->toBeTrue()
        ->and($payload['results']['on_last_page'])->toBe($empty);
})->with(PaginationType::cases())->with([false, true]);

it('resolves column labels once per render and refreshes the snapshot on subsequent renders', function (): void {
    $table = PayloadTable::make();
    foreach ([1, 2] as $render) {
        $payload = $table->results();
        expect($table->labelCalls)->toBe($render)
            ->and($payload['meta']['columns'][0]['label'])->toBe('Label '.$render)
            ->and($payload['columns'][0]['header'])->toBe('Label '.$render);
    }

    $table->payload();
    expect($table->labelCalls)->toBe(3);
    $table->meta();
    expect($table->labelCalls)->toBe(4);
});
