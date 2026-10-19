<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Maatwebsite\Excel\Events\AfterSheet;
use RuntimeException;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Table;

final class ContextTable extends Table
{
    protected ?string $resource = PipelineRecord::class;

    public function columns(): array
    {
        return [TextColumn::make('team', label: static fn (): string => 'Team '.Context::get('team'))];
    }

    public function exports(): array
    {
        return [Export::make('Records')->key('records')->queue()->events([
            AfterSheet::class => static fn (AfterSheet $event): mixed => $event->sheet->getDelegate()->setCellValue('C1', 'Context '.Context::get('team')),
        ])];
    }

    public function query(): Builder
    {
        // Fail closed if an export is attempted outside the trusted tenant context.
        $team = Context::get('team');
        throw_unless(is_string($team), RuntimeException::class, 'A team context is required.');

        return parent::query()->withGlobalScope('team', static fn (Builder $query): Builder => $query->where('team', Context::get('team')));
    }

    public function exportContext(ExportRequest $request): array
    {
        return ['team' => Context::get('team')];
    }

    public function withExportContext(array $context, Closure $callback): mixed
    {
        return Context::scope($callback, $context);
    }
}
