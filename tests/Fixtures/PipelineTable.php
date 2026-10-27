<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Table;

final class PipelineTable extends Table
{
    protected ?string $resource = PipelineRecord::class;

    public function columns(): array
    {
        return [TextColumn::make('name')->sortable()->searchable()];
    }

    public function exports(): array
    {
        return [
            Export::make('Records')->key('records')->limitToFilteredRows()->limitToSelectedRows()->using(
                static fn (Table $table, Export $export, ExportRequest $request, Builder $query): array => [
                    'ids' => $query->pluck('id')->all(),
                ],
            ),
        ];
    }
}
