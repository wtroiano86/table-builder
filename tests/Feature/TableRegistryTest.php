<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\ExcelServiceProvider;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\TableBuilder;
use Wtroiano\TableBuilder\TableRegistry;
use Wtroiano\TableBuilder\Tables\ConfiguredTable;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineTable;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'name' => 'Ada', 'team' => 'red', 'code' => 'first'],
        ['id' => 2, 'name' => 'Grace', 'team' => 'blue', 'code' => 'second'],
        ['id' => 3, 'name' => 'Linus', 'team' => 'red', 'code' => 'third'],
    ]);
});

it('resolves fresh tables from builder factories and keeps registry identity separate from table names', function (): void {
    $calls = 0;
    $builder = TableBuilder::make('people')->resource(PipelineRecord::class)->columns([TextColumn::make('name')]);
    TableRegistry::define('accounts', static function () use (&$calls, $builder): TableBuilder {
        $calls++;

        return $builder;
    });
    expect($calls)->toBe(0)->and(TableRegistry::has('accounts'))->toBeTrue();
    $first = TableRegistry::get('accounts');
    $first->columnsDefinition()[0]->label('Mutated');
    $second = TableRegistry::get('accounts');

    expect($first)->not->toBe($second)->and($calls)->toBe(2)
        ->and($second->columnsDefinition()[0]->resolvedLabel())->toBe('Name')
        ->and($second->reference())->toBe('registry:accounts')->and($second->registryKey())->toBe('accounts')
        ->and($second->name())->toBe('people');
});

it('supports existing table factories and dependency injection', function (): void {
    TableRegistry::define('legacy', static fn (Request $request): Table => PipelineTable::make()->named($request->input('table_name', 'legacy')));
    app()->instance('request', Request::create('/?table_name=custom'));

    expect(TableRegistry::get('legacy'))->toBeInstanceOf(PipelineTable::class)
        ->and(TableRegistry::get('legacy')->name())->toBe('custom')
        ->and(TableRegistry::get('legacy')->reference())->toBe('registry:legacy');
});

it('executes registered actions from their transport reference and enforces row eligibility', function (): void {
    TableRegistry::define('accounts', static fn (): TableBuilder => TableBuilder::make('people')->resource(PipelineRecord::class)
        ->columns([TextColumn::make('name')])->rowSelectionKey('code')
        ->selectableUsing(static fn (Model $model): bool => $model->getAttribute('team') === 'red')
        ->actions([Action::make('Run')->key('run')->asBulkAction(1)->handle(static fn (Model $model): mixed => $model->getKey())]));
    $payload = TableRegistry::get('accounts')->results();
    expect($payload['meta']['table'])->toBe('registry:accounts');

    $this->postJson($payload['meta']['actionEndpoint'], ['table' => $payload['meta']['table'], 'action' => 'run', 'keys' => ['first', 'second']])
        ->assertOk()->assertJson(['processed' => 1, 'skipped' => 1, 'results' => [1]]);
    $this->postJson($payload['meta']['actionEndpoint'], ['table' => $payload['meta']['table'], 'action' => 'run', 'selectionMode' => 'all'])
        ->assertOk()->assertJson(['processed' => 2, 'skipped' => 1, 'results' => [1, 3]]);
});

it('authorizes registered tables before creating a query for either endpoint', function (bool $export): void {
    $queries = 0;
    TableRegistry::define('private', static fn (): TableBuilder => TableBuilder::make('private')->resource(static function () use (&$queries): Builder {
        $queries++;

        return PipelineRecord::query();
    })->authorize(static fn (Request $request): bool => false)
        ->actions([Action::make('Run')->key('run')->handle(static fn (): null => null)])
        ->exports([Export::make('Records')->key('records')]));

    $this->postJson(route($export ? 'table-builder.exports.execute' : 'table-builder.actions.execute'), [
        'table' => 'registry:private', 'action' => 'run', 'export' => 'records', 'keys' => [1],
    ])->assertForbidden();
    expect($queries)->toBe(0);
})->with([false, true]);

it('keeps destructive select-all actions inside an unordered bounded fluent resource', function (): void {
    TableRegistry::define('bounded', static fn (): TableBuilder => TableBuilder::make('people')
        ->resource(PipelineRecord::query()->limit(2))
        ->actions([Action::make('Delete')->key('delete')->asBulkAction(1)->handle(static function (Model $model): mixed {
            $key = $model->getKey();
            $model->delete();

            return $key;
        })]));

    $this->postJson(route('table-builder.actions.execute'), [
        'table' => 'registry:bounded', 'action' => 'delete', 'selectionMode' => 'all',
    ])->assertOk()->assertJson(['processed' => 2, 'skipped' => 0, 'results' => [1, 2]]);
    expect(PipelineRecord::query()->get()->modelKeys())->toBe([3]);
});

it('prepares filtered selected registered exports and reconstructs them from serialized inputs', function (): void {
    Excel::fake();
    TableRegistry::define('accounts', static fn (): TableBuilder => TableBuilder::make('people')->resource(PipelineRecord::class)
        ->columns([TextColumn::make('name', sortable: true, searchable: true)])
        ->exports([Export::make('Records', 'people.xlsx')->key('records')->limitToFilteredRows()->limitToSelectedRows()->asDownload(false)]));
    Excel::shouldReceive('store')->once()->andReturnUsing(static function (TableExcelExport $export): bool {
        expect($export->__serialize()['tableClass'])->toBe('registry:accounts')
            ->and($export->query()->get()->modelKeys())->toBe([1])
            ->and(unserialize(serialize($export))->query()->get()->modelKeys())->toBe([1]);

        return true;
    });

    $this->postJson(route('table-builder.exports.execute'), [
        'table' => 'registry:accounts', 'export' => 'records', 'keys' => [1, 2], 'state' => ['search' => 'Ada', 'sort' => 'name'],
    ])->assertOk();
});

it('reconstructs queued registered exports in captured context including column factories and sheet events', function (): void {
    app()->register(ExcelServiceProvider::class);
    Storage::fake('local');
    config()->set('queue.default', 'sync');
    $calls = 0;
    TableRegistry::define('tenant-people', static function () use (&$calls): TableBuilder {
        $calls++;

        return TableBuilder::make('people')
            ->resource(static fn (): Builder => PipelineRecord::query()->where('team', Context::get('team')))
            ->columns(static fn (): array => [TextColumn::make('name'), TextColumn::make('team', label: 'Team '.Context::get('team'))])
            ->exports(static fn (): array => [Export::make('People', 'people.xlsx')->key('people')->queue()->events([
                AfterSheet::class => static fn (AfterSheet $event): mixed => $event->sheet->getDelegate()->setCellValue('C1', Context::get('team')),
            ])])->exportContext(
                capture: static fn (ExportRequest $request): array => ['team' => Context::get('team')],
                restore: static fn (array $context, Closure $callback): mixed => Context::scope($callback, $context),
            );
    });
    Context::add('team', 'red');
    $table = TableRegistry::get('tenant-people');
    $export = TableExcelExport::fromRequest($table, $table->exportsDefinition()[0], ExportRequest::fromRequest(Request::create('/')));
    $restored = unserialize(serialize($export));
    Context::forget('team');
    $pending = Excel::queue($restored, 'people.xlsx', 'local');
    unset($pending);

    $sheet = IOFactory::load(Storage::disk('local')->path('people.xlsx'))->getActiveSheet();
    expect($sheet->getCell('A2')->getValue())->toBe('Ada')
        ->and($sheet->getCell('A3')->getValue())->toBe('Linus')
        ->and($sheet->getCell('A4')->getValue())->toBeNull()
        ->and($sheet->getCell('B1')->getValue())->toBe('Team red')
        ->and($sheet->getCell('C1')->getValue())->toBe('red')
        ->and(Context::has('team'))->toBeFalse()->and($calls)->toBeGreaterThan(1);
});

it('rejects unknown references and direct configured-table class references', function (string $reference, bool $export): void {
    $this->postJson(route($export ? 'table-builder.exports.execute' : 'table-builder.actions.execute'), [
        'table' => $reference, 'action' => 'run', 'export' => 'records', 'keys' => [1],
    ])->assertUnprocessable();
})->with(['registry:missing', 'registry:', ConfiguredTable::class])->with([false, true]);

it('validates registry keys and duplicate definitions', function (): void {
    foreach (['', ' bad', 'registry:people', 'App\\Tables\\People'] as $key) {
        expect(fn () => TableRegistry::define($key, static fn (): TableBuilder => TableBuilder::make('people')))
            ->toThrow(InvalidArgumentException::class);
    }

    TableRegistry::define('people', static fn (): TableBuilder => TableBuilder::make('people'));
    expect(fn () => TableRegistry::define('people', static fn (): TableBuilder => TableBuilder::make('people')))
        ->toThrow(InvalidArgumentException::class, 'already registered');
    TableRegistry::forget('people');
    expect(TableRegistry::has('people'))->toBeFalse()
        ->and(fn (): Table => TableRegistry::get('people'))->toThrow(InvalidArgumentException::class, 'not registered');
});

it('cleans up factory resolution after invalid returns exceptions and circular lookups', function (): void {
    TableRegistry::define('invalid', static fn (): stdClass => new stdClass());
    expect(fn (): Table => TableRegistry::get('invalid'))->toThrow(InvalidArgumentException::class, 'must return');
    TableRegistry::define('cycle', static fn (): Table => TableRegistry::get('cycle'));
    expect(fn (): Table => TableRegistry::get('cycle'))->toThrow(LogicException::class, 'Circular');
    $calls = 0;
    TableRegistry::define('retry', static function () use (&$calls): TableBuilder {
        throw_if($calls++ === 0, RuntimeException::class, 'First attempt');

        return TableBuilder::make('retry');
    });
    expect(fn (): Table => TableRegistry::get('retry'))->toThrow(RuntimeException::class, 'First attempt')
        ->and(TableRegistry::get('retry')->reference())->toBe('registry:retry');
});
