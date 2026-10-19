<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Pipelines\TableQuery\Pipes\ApplySort;

/** @deprecated Use the table query pipeline or its ApplySort pipe. */
final readonly class TableSortBuilder
{
    private ApplySort $pipe;

    public function __construct(RelationFieldResolver $relationFieldResolver)
    {
        $this->pipe = new ApplySort($relationFieldResolver);
    }

    /** @param Builder<Model> $query */
    public function apply(Builder $query, TableQueryMetadata $metadata, ?string $sort, string $direction): void
    {
        $this->pipe->apply($query, $metadata, $sort, $direction);
    }
}
