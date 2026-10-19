<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\ExcelServiceProvider;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Tests\Fixtures\ContextTable;
use Wtroiano\TableBuilder\Tests\Support\DatabaseFixtures;

beforeEach(function (): void {
    DatabaseFixtures::pipelineRecords([
        ['id' => 1, 'name' => 'Ada', 'team' => 'blue'], ['id' => 2, 'name' => 'Grace', 'team' => 'red'],
    ]);
});

it('restores captured tenant context for real queued sheet events and cleans up afterward', function (): void {
    app()->register(ExcelServiceProvider::class);
    Storage::fake('local');
    config()->set('queue.default', 'sync');
    Context::add('team', 'blue');
    $table = ContextTable::make();
    $export = unserialize(serialize(TableExcelExport::fromRequest($table, $table->exports()[0], ExportRequest::fromRequest(Request::create('/')))));
    Context::forget('team');
    $pending = Excel::queue($export, 'events.xlsx', 'local');
    unset($pending);

    $sheet = IOFactory::load(Storage::disk('local')->path('events.xlsx'))->getActiveSheet();
    expect($sheet->getCell('A2')->getValue())->toBe('blue')
        ->and($sheet->getCell('C1')->getValue())->toBe('Context blue')
        ->and(Context::has('team'))->toBeFalse();
});

it('forwards event arguments and return values and restores outer context even when a listener throws', function (bool $throws): void {
    Context::add('team', 'blue');
    $table = ContextTable::make();
    $definition = $table->exports()[0]->events([
        AfterSheet::class => static function (string $first, int $second) use ($throws): false {
            expect(Context::get('team'))->toBe('blue')->and($first)->toBe('event')->and($second)->toBe(2);
            throw_if($throws, RuntimeException::class, 'Listener failed.');

            return false;
        },
    ]);
    $export = TableExcelExport::fromRequest($table, $definition, ExportRequest::fromRequest(Request::create('/')));
    Context::add('team', 'outer');
    $listener = $export->registerEvents()[AfterSheet::class];
    if ($throws) {
        expect(fn (): mixed => $listener('event', 2))->toThrow(RuntimeException::class, 'Listener failed.');
    } else {
        expect($listener('event', 2))->toBeFalse();
    }

    expect(Context::get('team'))->toBe('outer');
})->with([false, true]);
