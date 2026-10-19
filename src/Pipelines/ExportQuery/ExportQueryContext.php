<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportQuery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Table;

final class ExportQueryContext
{
    /**
     * @param  Builder<Model>  $query
     * @param  list<int|string>  $selectedKeys
     */
    public function __construct(
        public Builder $query,
        public readonly Table $table,
        public readonly Export $export,
        public readonly TableRequest $request,
        public readonly array $selectedKeys,
    ) {}
}
