<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\TableQuery;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Query\TableQueryMetadata;
use Wtroiano\TableBuilder\Query\TableQueryParameters;

final readonly class TableQueryContext
{
    /** @param Builder<Model> $query */
    public function __construct(
        public Builder $query,
        public TableQueryMetadata $metadata,
        public TableQueryParameters $parameters,
        public ?Closure $searchUsing = null,
    ) {}
}
