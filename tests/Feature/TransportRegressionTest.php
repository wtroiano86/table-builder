<?php

declare(strict_types=1);
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\ExcelServiceProvider;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Tests\Fixtures\TransportTable;
use Wtroiano\TableBuilder\Tests\Fixtures\UuidTable;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    app()->register(ExcelServiceProvider::class);
    DatabaseFixtures::relations([
        ['id' => 1, 'name' => 'Ada', 'manager_id' => null],
        ['id' => 2, 'name' => 'Ada', 'manager_id' => 1],
        ['id' => 3, 'name' => 'Grace', 'manager_id' => 1],
        ['id' => 4, 'name' => 'Grace', 'manager_id' => null],
    ], [
        ['id' => 1, 'parent_id' => 1, 'label' => 'B', 'active' => true],
        ['id' => 2, 'parent_id' => 2, 'label' => 'B', 'active' => true],
        ['id' => 3, 'parent_id' => 2, 'label' => 'C', 'active' => true],
        ['id' => 4, 'parent_id' => 3, 'label' => 'A', 'active' => false],
    ]);
    DatabaseFixtures::uuidRecords([['uuid' => 'record-a', 'name' => 'Ada'], ['uuid' => 'record-b', 'name' => 'Grace']]);
});

it('visits every record once in both directions with cursor ordering ties and missing relations', function (string $field, string $direction, array $expected, int $perPage = 1): void {
    $table = TransportTable::make()->named('people');
    $table->field = $field;

    $cursor = null;
    $forward = [];
    $pages = [];
    do {
        $page = $table->paginate(request: TableRequest::fromArray(['people' => ['sort' => $field, 'direction' => $direction, 'cursor' => $cursor, 'perPage' => $perPage]]));
        $forward = [...$forward, ...$page->getCollection()->modelKeys()];
        $pages[] = $page;
        $cursor = $page->nextCursor()?->encode();
        expect(count($pages))->toBeLessThanOrEqual(5);
    } while ($cursor !== null);

    expect($forward)->toBe($expected);
    $backward = array_last($pages)->getCollection()->modelKeys();
    $cursor = array_last($pages)->previousCursor()?->encode();
    while ($cursor !== null) {
        $page = $table->paginate(request: TableRequest::fromArray(['people' => ['sort' => $field, 'direction' => $direction, 'cursor' => $cursor, 'perPage' => $perPage]]));
        $backward = [...$page->getCollection()->modelKeys(), ...$backward];
        $cursor = $page->previousCursor()?->encode();
        expect(count($backward))->toBeLessThanOrEqual(4);
    }

    expect($backward)->toBe($expected);
})->with([
    ['name', 'asc', [1, 2, 3, 4]], ['name', 'desc', [3, 4, 1, 2]],
    ['manager.name', 'asc', [2, 3, 1, 4]], ['manager.name', 'desc', [2, 3, 1, 4]],
    ['children.label', 'asc', [1, 2, 3, 4]], ['children.label', 'desc', [2, 1, 3, 4]],
    ['name', 'asc', [1, 2, 3, 4], 2], ['children.label', 'desc', [2, 1, 3, 4], 2],
]);

it('builds cursor payload links without exposing sorting attributes in rows', function (): void {
    $table = TransportTable::make();
    $table->field = 'manager.name';

    $payload = $table->results(request: TableRequest::fromArray(['sort' => 'manager.name']));

    expect($payload['results']['next_page_url'])->toBeString()
        ->and(array_keys($payload['results']['data'][0]))->not->toContain('_inertia_relation_sort', '_inertia_cursor_sort_0');
});

it('restarts pagination for stale cursors and cursors with non-scalar parameters', function (): void {
    $table = TransportTable::make();
    $first = $table->paginate(request: TableRequest::fromArray(['sort' => 'name']));
    $parameters = $first->nextCursor()->toArray();
    $parameters[array_key_first($parameters)] = ['invalid'];

    foreach ([new Cursor(['name' => 'Ada']), new Cursor($parameters)] as $cursor) {
        $page = $table->paginate(request: TableRequest::fromArray(['sort' => 'name', 'cursor' => $cursor->encode()]));
        expect($page->getCollection()->modelKeys())->toBe([1]);
    }
});

it('reads legacy unnamed export payloads without a table name', function (): void {
    $export = new TableExcelExport(TransportTable::class, 'records');
    $export->__unserialize(['tableClass' => TransportTable::class, 'exportKey' => 'records', 'tableRequestInput' => ['search' => 'Ada']]);

    expect($export->query()->get()->modelKeys())->toBe([1, 2]);
});

it('preserves cleared overrides through actual named and unnamed paginator URLs', function (bool $named): void {
    $table = TransportTable::make()->standardPagination();
    $table->defaults = ['perPage' => 2, 'search' => 'Ada', 'sort' => 'name', 'columns' => ['name'], 'sticky' => ['name']];
    if ($named) {
        $table = $table->named('people');
    }

    $values = ['perPage' => 1, 'search' => null, 'sort' => null, 'columns' => [], 'sticky' => []];
    $input = $named ? ['people' => $values] : $values;
    $page = $table->paginate(request: TableRequest::fromArray($input));
    $nextRequest = Request::create($page->nextPageUrl());
    $state = $table->stateFromRequest($nextRequest);

    expect($state->page())->toBe(2)->and($state->perPage())->toBe(1)
        ->and($state->search())->toBeNull()->and($state->sort())->toBeNull()
        ->and($state->columns())->toBe([])->and($state->sticky())->toBe([])->and($state->hasStickyOverride())->toBeTrue();

    $query = $table->navigation(TableRequest::fromArray($input))->page(3);
    $navigationState = $table->stateFromRequest(Request::create('/?'.http_build_query($query)));
    expect($navigationState->page())->toBe(3)->and($navigationState->perPage())->toBe(1)
        ->and($navigationState->search())->toBeNull()->and($navigationState->sort())->toBeNull()
        ->and($navigationState->columns())->toBe([])->and($navigationState->sticky())->toBe([]);
})->with([false, true]);

it('preserves named table filters in a serialized export and a real styled spreadsheet', function (): void {
    $table = TransportTable::make()->named('custom');
    $request = ExportRequest::fromRequest(Request::create('/', 'POST', ['state' => ['search' => 'Grace']]));
    $restored = unserialize(serialize(TableExcelExport::fromRequest($table, $table->exportsDefinition()[0], $request)));

    expect($restored->query()->get()->modelKeys())->toBe([3, 4]);
    $path = tempnam(sys_get_temp_dir(), 'inertia-xlsx-');
    try {
        file_put_contents($path, Excel::raw($restored, ExcelWriter::XLSX));
        $sheet = IOFactory::load($path)->getActiveSheet();
        expect($sheet->getCell('A1')->getValue())->toBe('Name')
            ->and($sheet->getCell('A2')->getValue())->toBe('Grace')
            ->and($sheet->getCell('A3')->getValue())->toBe('Grace')
            ->and($sheet->getHighestDataRow())->toBe(3)
            ->and($sheet->getStyle('A2')->getFont()->getBold())->toBeTrue();
    } finally {
        unlink($path);
    }
});

it('preserves an explicit reset of the default cursor when changing pages', function (): void {
    $table = TransportTable::make();
    $table->defaults = ['cursor' => 'default-cursor'];

    $query = $table->navigation(TableRequest::fromArray(['cursor' => null]))->page(2);

    expect($table->stateFromRequest(Request::create('/?'.http_build_query($query)))->cursor())->toBeNull();
});

it('uses non-id primary keys consistently in row aliases actions and exports', function (): void {
    $table = UuidTable::make();
    $payload = $table->results();
    expect($payload['rowSelectionKey'])->toBe('_selection_key')->and($payload['results'][0]['id'])->toBe('record-a')->and($payload['results'][0]['_selection_key'])->toBe('record-a');
    $this->postJson(route('table-builder.actions.execute'), ['table' => UuidTable::class, 'action' => 'run', 'keys' => ['record-b']])
        ->assertOk()->assertJson(['processed' => 1, 'results' => ['record-b']]);
    $request = ExportRequest::fromRequest(Request::create('/', 'POST', ['keys' => ['record-b']]));
    $export = unserialize(serialize(TableExcelExport::fromRequest($table, $table->exportsDefinition()[0], $request)));
    expect($export->query()->get()->modelKeys())->toBe(['record-b']);
});

it('returns validation errors for malformed action identifiers', function (array $identifiers): void {
    $this->postJson(route('table-builder.actions.execute'), ['table' => UuidTable::class, 'action' => 'run', 'keys' => ['record-a'], ...$identifiers])
        ->assertUnprocessable();
})->with([
    [['table' => ['bad']]], [['table' => true]], [['table' => 123]], [['action' => ['run']]], [['action' => false]], [['action' => 123]],
]);

it('canonicalizes generator paths before deriving their namespaces', function (string $input): void {
    $path = app_path('Tables/TransportGenerated.php');
    try {
        expect(Artisan::call('make:table-builder', ['name' => 'TransportGenerated', '--path' => $input]))->toBe(0)
            ->and(file_get_contents($path))->toContain('namespace App\\Tables;');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
})->with(['./app/Tables', 'app/Admin/../Tables', './app//Tables']);

it('restarts pagination when a cursor direction flag is malformed', function (): void {
    $table = TransportTable::make();
    $first = $table->paginate();
    $parameters = $first->nextCursor()->toArray();
    $parameters['_pointsToNextItems'] = 'false';
    $page = $table->paginate(request: TableRequest::fromArray(['cursor' => base64_encode(json_encode($parameters))]));
    expect($page->getCollection()->modelKeys())->toBe([1]);
});
