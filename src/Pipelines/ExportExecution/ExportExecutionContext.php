<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportExecution;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Table;

final class ExportExecutionContext
{
    // Initialized by ResolveExport before any subsequent stage runs.
    public Table $table;

    public Export $export;

    /** @var Builder<Model>|null */
    public ?Builder $query = null;

    public bool $storageFailed = false;

    public mixed $response = null;

    public function __construct(public readonly ExportRequest $request) {}
}
