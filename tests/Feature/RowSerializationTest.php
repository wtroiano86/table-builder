<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\RowSerializationPipeline;
use Wtroiano\TableBuilder\Support\Url;
use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\Tables\TablePayloadBuilder;
use Wtroiano\TableBuilder\Tables\TableState;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\RowTable;

it('serializes model identity, columns and media before transforming the completed row', function (): void {
    $table = RowTable::make();
    $table->link = new Url()->to('/records/1')->openInNewTab();

    $record = new PipelineRecord(['id' => 1, 'code' => 'first', 'name' => 'Ada', 'team' => 'red', 'link' => '/profile/1', 'avatar' => '/avatar.png']);
    $payload = resolve(TablePayloadBuilder::class)->buildResults($table, new TableState(), collect([$record]));
    $row = $payload['results'][0];

    expect($row)->toMatchArray([
        '_primary_key' => 1,
        'code' => 'first',
        'display_name' => 'ADA',
        '_url' => ['url' => '/records/1', 'target' => '_blank'],
        '_column_urls' => ['display_name' => '/profile/1'],
        '_selectable' => true,
        'transformed' => true,
    ])->and($row['_column_images']['display_name']['url'])->toBe('/avatar.png')
        ->and(array_column($row['_actions'], 'key'))->toBe(['view'])
        ->and($row['_actions'][0]['label'])->toBe('View 1')
        ->and($table->events)->toBe(['identity', 'columns', 'transform']);
});

it('serializes paginator rows and retains pagination metadata', function (): void {
    $table = RowTable::make();
    $table->link = '';

    $record = new PipelineRecord(['id' => 2, 'name' => 'Grace', 'team' => 'blue']);
    $paginator = new LengthAwarePaginator([$record], 3, 1, 2);
    $payload = resolve(TablePayloadBuilder::class)->buildResults($table, new TableState(), $paginator);
    $results = $payload['results'];

    expect($results)->toMatchArray(['total' => 3, 'current_page' => 2, 'on_first_page' => false, 'on_last_page' => false])
        ->and($results['data'][0]['_selectable'])->toBeFalse()
        ->and($results['data'][0])->not->toHaveKey('_url')
        ->and($results['data'][0]['_column_urls'])->toBe([]);
});

it('supports non-model records without model-only actions or transforms', function (): void {
    $table = RowTable::make();
    $payload = resolve(TablePayloadBuilder::class)->buildResults($table, new TableState(), collect([
        ['id' => 7, 'code' => 'seventh', 'name' => 'Alan'],
        new class
        {
            public string $name = 'Grace';

            public function getKey(): int
            {
                return 8;
            }
        },
    ]));

    expect($payload['results'][0])->toMatchArray(['_primary_key' => 7, 'code' => 'seventh', 'display_name' => 'ALAN', '_actions' => [], '_selectable' => true])
        ->and($payload['results'][1]['_primary_key'])->toBe(8)
        ->and($payload['results'][1])->not->toHaveKey('transformed')
        ->and($table->events)->toBe(['columns', 'columns']);
});

it('retains already serialized array results unchanged', function (): void {
    $table = RowTable::make();
    $rows = [['custom' => 'payload']];
    $payload = resolve(TablePayloadBuilder::class)->buildResults($table, new TableState(), $rows);

    expect($payload['results'])->toBe($rows)->and($table->events)->toBe([]);
});

it('preserves default and reserved identity keys and allows selection keys to be disabled', function (?string $selectionKey): void {
    $table = new class extends Table
    {
        public ?string $selectionKey = null;

        public function columns(): array
        {
            return [];
        }

        public function rowSelectionKey(): ?string
        {
            return $this->selectionKey;
        }
    };
    $table->selectionKey = $selectionKey;

    $row = resolve(RowSerializationPipeline::class)->serialize($table, ['id' => 3]);

    expect($row['_primary_key'])->toBe(3)
        ->and(array_key_exists('id', $row))->toBe($selectionKey === 'id');
})->with(['id', null, '_primary_key']);
