<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\ExcelServiceProvider;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pipelines\ActionChunk\ActionChunkPipeline;
use Wtroiano\TableBuilder\Pipelines\ActionChunk\ActionExecutionResult;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\RowSerializationPipeline;
use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\Tests\Fixtures\ContextTable;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'team' => 'red', 'code' => 'one'],
        ['id' => 2, 'team' => 'blue', 'code' => 'two'],
        ['id' => 3, 'team' => 'red', 'code' => 'three'],
    ]);
    $this->table = new class extends Table
    {
        public bool $allowed = true;

        public bool $partial = false;

        public bool $ordered = false;

        public bool $formulas = false;

        public bool $customKey = false;

        protected ?string $resource = PipelineRecord::class;

        public function authorize(Request $request): bool
        {
            return parent::authorize($request) && $this->allowed;
        }

        public function columns(): array
        {
            return [TextColumn::make('team', sortable: true)->exportFormulas($this->formulas), TextColumn::make('id')->mapAs(static fn (mixed $id): string => 'Record '.$id)];
        }

        public function actions(): array
        {
            return [Action::make('Run')->handle(static fn (Model $model): mixed => $model->getKey())];
        }

        public function exports(): array
        {
            return [Export::make('Records')->key('records')];
        }

        public function query(): Builder
        {
            $query = parent::query();
            if ($this->partial) {
                $query->select('team');
            }

            if ($this->ordered) {
                $query->orderBy('id');
            }

            return $query;
        }

        public function rowSelectionKey(): ?string
        {
            return $this->customKey ? 'code' : parent::rowSelectionKey();
        }

        public function paginationMode(PaginationType $type, bool $enabled = true): void
        {
            $this->paginationType = $type;
            $this->pagination = $enabled;
        }
    };
    app()->instance($this->table::class, $this->table);
});

it('requires authentication on both package endpoints before resolving a table', function (string $endpoint): void {
    auth()->logout();
    $this->postJson(route($endpoint), ['table' => $this->table::class, 'action' => 'run', 'export' => 'records', 'keys' => [1]])->assertUnauthorized();
})->with(['table-builder.actions.execute', 'table-builder.exports.execute']);

it('checks table access for authenticated callers before actions or exports', function (string $endpoint): void {
    $this->table->allowed = false;
    $this->postJson(route($endpoint), ['table' => $this->table::class, 'action' => 'run', 'export' => 'records', 'keys' => [1]])->assertForbidden();
})->with(['table-builder.actions.execute', 'table-builder.exports.execute']);

it('protects lifecycle hooks even when the action pipeline is called directly', function (): void {
    $called = false;
    $action = Action::make('Denied')->authorize(false)->before(function () use (&$called): void {
        $called = true;
    });
    expect(fn () => resolve(ActionChunkPipeline::class)->run($this->table, $action, [], new ActionExecutionResult()))->toThrow(HttpException::class)
        ->and($called)->toBeFalse();
});

it('retains primary and custom selection identity with partial projections in every pagination mode', function (PaginationType $type, bool $enabled): void {
    $this->table->partial = true;
    $this->table->customKey = true;
    $this->table->paginationMode($type, $enabled);

    $payload = $this->table->results();
    $row = $enabled ? $payload['results']['data'][0] : $payload['results'][0];

    expect($row['_primary_key'])->toBe(1)->and($row['_selection_key'])->toBe('one')
        ->and($row['id'])->toBe('Record 1')->and($payload['rowSelectionKey'])->toBe('_selection_key');
    $this->postJson(route('table-builder.actions.execute'), ['table' => $this->table::class, 'action' => 'run', 'keys' => ['one']])
        ->assertOk()->assertJson(['processed' => 1, 'results' => [1]]);
})->with([[PaginationType::Standard, true], [PaginationType::Simple, true], [PaginationType::Cursor, true], [PaginationType::Standard, false]]);

it('keeps formatted primary-key cells separate from selection identity', function (): void {
    $row = $this->table->results()['results']['data'][0];
    expect($row['id'])->toBe('Record 1')->and($row['_selection_key'])->toBe(1);
    $this->postJson(route('table-builder.actions.execute'), ['table' => $this->table::class, 'action' => 'run', 'keys' => [$row['_selection_key']]])
        ->assertOk()->assertJson(['results' => [1]]);
});

it('makes explicit sorting primary when the resource has existing ordering', function (): void {
    $this->table->ordered = true;
    $query = $this->table->apply(request: TableRequest::fromArray(['sort' => 'team', 'direction' => 'desc']));
    expect($query->get()->pluck('team')->all())->toBe(['red', 'red', 'blue']);
});

it('protects reserved identity fields from column keys', function (): void {
    $table = new class extends Table
    {
        public function columns(): array
        {
            return [TextColumn::make('team', key: '_primary_key'), TextColumn::make('team', key: '_selection_key')];
        }
    };
    $row = resolve(RowSerializationPipeline::class)->serialize($table, new PipelineRecord(['id' => 1, 'team' => 'red']));
    expect($row['_primary_key'])->toBe(1)->and($row['_selection_key'])->toBe(1);
});

it('writes untrusted text literally into real XLSX and CSV files', function (string $value): void {
    app()->register(ExcelServiceProvider::class);
    PipelineRecord::query()->whereKey(1)->update(['team' => $value]);
    $request = ExportRequest::fromRequest(Request::create('/'));
    $export = TableExcelExport::fromRequest($this->table, Export::make(), $request);
    $path = tempnam(sys_get_temp_dir(), 'safe-xlsx-');
    try {
        file_put_contents($path, Excel::raw($export, ExcelWriter::XLSX));
        $cell = IOFactory::load($path)->getActiveSheet()->getCell('A2');
        expect($cell->getDataType())->toBe('s')->and($cell->getValue())->toBe($value);
    } finally {
        File::delete($path);
    }

    $csvExport = TableExcelExport::fromRequest($this->table, Export::make(type: ExcelWriter::CSV), $request);
    $csv = Excel::raw($csvExport, ExcelWriter::CSV);
    expect($csv)->toContain("'".$value);
})->with(['=1+1', '+SUM(1,1)', '-1+2', '@SUM(1,1)', "\t=1+1"]);

it('requires an explicit column opt-in to write formulas and retains native numbers', function (): void {
    app()->register(ExcelServiceProvider::class);
    $this->table->formulas = true;
    PipelineRecord::query()->whereKey(1)->update(['team' => '=1+1']);
    $export = TableExcelExport::fromRequest($this->table, Export::make(), ExportRequest::fromRequest(Request::create('/')));
    $sheet = new Spreadsheet()->getActiveSheet();
    $export->bindValue($sheet->getCell('A2'), $export->map(PipelineRecord::query()->find(1))[0]);
    $export->bindValue($sheet->getCell('B2'), 12.5);
    expect($sheet->getCell('A2')->getDataType())->toBe('f')->and($sheet->getCell('B2')->getValue())->toBe(12.5);
});

it('restores trusted context after serialization and cleans up context after callbacks and failures', function (): void {
    Context::add('team', 'blue');
    $table = ContextTable::make();
    $export = TableExcelExport::fromRequest($table, $table->exports()[0], ExportRequest::fromRequest(Request::create('/')));
    $restored = unserialize(serialize($export));
    Context::forget('team');

    expect($restored->query()->get()->modelKeys())->toBe([2])
        ->and($restored->headings())->toBe(['Team blue'])
        ->and(Context::has('team'))->toBeFalse();
    $middleware = $restored->middleware()[0];
    $result = $middleware->handle(new stdClass(), static fn (): mixed => Context::get('team'));
    expect($result)->toBe('blue')->and(Context::has('team'))->toBeFalse();
    expect(fn () => $middleware->handle(new stdClass(), static fn (): never => throw new RuntimeException('Failure')))->toThrow(RuntimeException::class, 'Failure');
    expect(Context::has('team'))->toBeFalse();
});

it('executes real serialized export jobs inside their tenant context', function (): void {
    app()->register(ExcelServiceProvider::class);
    Storage::fake('local');
    config()->set('queue.default', 'sync');
    Context::add('team', 'blue');
    $table = ContextTable::make();
    $export = unserialize(serialize(TableExcelExport::fromRequest($table, $table->exports()[0], ExportRequest::fromRequest(Request::create('/')))));
    Context::forget('team');

    $pending = Excel::queue($export, 'tenant.xlsx', 'local', ExcelWriter::XLSX);
    unset($pending);
    $sheet = IOFactory::load(Storage::disk('local')->path('tenant.xlsx'))->getActiveSheet();
    expect($sheet->getCell('A1')->getValue())->toBe('Team blue')->and($sheet->getCell('A2')->getValue())->toBe('blue')
        ->and($sheet->getHighestDataRow())->toBe(2)->and(Context::has('team'))->toBeFalse();
});
