<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportQuery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\Pipes\ApplyTableFilters;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\Pipes\EnsureDeterministicOrder;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\Pipes\RestrictToSelectedRows;
use Wtroiano\TableBuilder\Table;

final class ExportQueryBuilder
{
    /**
     * @param  list<int|string>  $selectedKeys
     * @return Builder<Model>
     */
    public function apply(Table $table, Export $export, TableRequest $request, array $selectedKeys = []): Builder
    {
        $context = new ExportQueryContext($table->query(), $table, $export, $request, $selectedKeys);

        /** @var ExportQueryContext $result */
        $result = new Pipeline()
            ->send($context)
            ->through([
                new ApplyTableFilters(),
                new RestrictToSelectedRows(),
                new EnsureDeterministicOrder(),
            ])
            ->thenReturn();

        return $result->query;
    }
}
