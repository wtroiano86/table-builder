<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Query;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Pipelines\TableQuery\Pipes\ApplySearch;

/** @deprecated Use the table query pipeline or its ApplySearch pipe. */
final readonly class TableSearchBuilder
{
    private ApplySearch $pipe;

    public function __construct(SearchTermParser $searchTermParser, RelationFieldResolver $relationFieldResolver)
    {
        $this->pipe = new ApplySearch($searchTermParser, $relationFieldResolver);
    }

    /** @param Builder<Model> $query */
    public function apply(Builder $query, TableQueryMetadata $metadata, ?string $search, ?Closure $searchUsing = null): void
    {
        $this->pipe->apply($query, $metadata, $search, $searchUsing);
    }
}
