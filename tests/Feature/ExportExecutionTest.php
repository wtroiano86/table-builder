<?php

declare(strict_types=1);

use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Http\Controllers\ExportController;
use Wtroiano\TableBuilder\Tests\Fixtures\ExportFlowTable;

beforeEach(function (): void {

    $this->table = ExportFlowTable::make();
    $this->table->definition = Export::make('Report', 'report.csv', ExcelWriter::CSV)->key('report');

    app()->instance(ExportFlowTable::class, $this->table);
    Excel::fake();
});

afterEach(function (): void {
    Export::defaultQueueName();
});

it('ends export execution on any non-null custom result even when queueing is enabled', function (mixed $value): void {
    $this->table->definition->queue()->using(function () use ($value): mixed {
        $this->table->events[] = 'custom';

        return $value;
    });
    Excel::shouldReceive('download')->never();
    Excel::shouldReceive('queue')->never();
    Excel::shouldReceive('store')->never();
    $request = Request::create('/', 'POST', ['table' => ExportFlowTable::class, 'export' => 'report']);
    $request->setUserResolver(static fn (): mixed => auth()->user());

    expect(resolve(ExportController::class)($request))->toBe($value)
        ->and($this->table->events)->toBe(['query', 'custom']);
})->with([false, 0, '', [['custom' => ['ok' => true]]]]);

it('continues to built-in storage when a custom exporter returns null', function (): void {
    Excel::fake();
    $this->table->definition->asDownload(false)->using(function (): mixed {
        $this->table->events[] = 'custom';

        return null;
    });
    $this->postJson(route('table-builder.exports.execute'), ['table' => ExportFlowTable::class, 'export' => 'report'])
        ->assertOk()->assertExactJson(['ok' => true, 'status' => 'processing', 'export' => 'report', 'message' => 'Export is being processed.']);
    Excel::assertStored('report.csv');
    expect($this->table->events)->toBe(['query', 'custom']);
});

it('downloads using the configured filename and writer type', function (): void {
    Excel::shouldReceive('download')->once()->with(Mockery::type(TableExcelExport::class), 'report.csv', ExcelWriter::CSV)
        ->andReturn(new BinaryFileResponse(__FILE__, headers: ['X-Test-Export' => 'downloaded']));
    Excel::shouldReceive('store')->never();
    Excel::shouldReceive('queue')->never();

    $this->postJson(route('table-builder.exports.execute'), ['table' => ExportFlowTable::class, 'export' => 'report'])
        ->assertOk()->assertHeader('X-Test-Export', 'downloaded');
});

it('queues before considering download and invokes the configured queue hook', function (): void {
    Excel::fake();
    Export::defaultQueueName('reports');
    $this->table->definition->queue('queued.csv', 'local', function (PendingDispatch $job): void {
        $this->table->events[] = ['queued', $job->getJob()->queue];
    });
    $this->postJson(route('table-builder.exports.execute'), ['table' => ExportFlowTable::class, 'export' => 'report'])
        ->assertOk()->assertJson(['status' => 'processing', 'export' => 'report']);
    Excel::assertQueued('queued.csv', 'local');
    expect($this->table->events)->toBe([['queued', 'reports']]);
});

it('retains Inertia processing redirects and export dialogs', function (bool $queued): void {
    Excel::fake();
    $this->table->definition->redirectBackWithDialog('Preparing', 'Your report is on its way.');

    if ($queued) {
        $this->table->definition->queue();
    }

    $this->from('/records')->post(route('table-builder.exports.execute'), ['table' => ExportFlowTable::class, 'export' => 'report'], ['X-Inertia' => 'true'])
        ->assertRedirect('/records')->assertSessionHas('table_export.status', 'processing')
        ->assertSessionHas('table_export_dialog.title', 'Preparing');
})->with([false, true]);

it('checks authorization before preparing a query or dispatching', function (): void {
    Excel::shouldReceive('download')->never();
    Excel::shouldReceive('store')->never();
    Excel::shouldReceive('queue')->never();
    $this->table->definition->authorize(function (): bool {
        $this->table->events[] = 'authorize';

        return false;
    });
    $this->postJson(route('table-builder.exports.execute'), ['table' => ExportFlowTable::class, 'export' => 'report'])->assertForbidden();
    expect($this->table->events)->toBe(['authorize']);
});

it('retains invalid table and unknown export statuses', function (mixed $table, string $export, int $status): void {
    $this->postJson(route('table-builder.exports.execute'), ['table' => $table, 'export' => $export])->assertStatus($status);
    expect($this->table->events)->toBe([]);
})->with([
    ['', 'report', 422], [stdClass::class, 'report', 422], [['bad'], 'report', 422],
    [ExportFlowTable::class, 'missing', 404],
]);

it('propagates custom exporter failures without dispatching a fallback export', function (): void {
    $this->withoutExceptionHandling();
    $this->table->definition->using(static fn (): never => throw new RuntimeException('Exporter failed'));
    Excel::shouldReceive('download')->never();
    Excel::shouldReceive('queue')->never();
    Excel::shouldReceive('store')->never();

    expect(fn () => $this->postJson(route('table-builder.exports.execute'), ['table' => ExportFlowTable::class, 'export' => 'report']))
        ->toThrow(RuntimeException::class, 'Exporter failed');
});
