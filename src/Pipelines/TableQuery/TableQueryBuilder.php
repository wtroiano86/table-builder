<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\TableQuery;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;
use Wtroiano\TableBuilder\Contracts\TableState;
use Wtroiano\TableBuilder\Pipelines\TableQuery\Pipes\ApplySearch;
use Wtroiano\TableBuilder\Pipelines\TableQuery\Pipes\ApplySort;
use Wtroiano\TableBuilder\Query\TableQueryMetadataResolver;
use Wtroiano\TableBuilder\Query\TableQueryParameters;
use Wtroiano\TableBuilder\Table;

final class TableQueryBuilder
{
    private ?Closure $searchUsing = null;

    public function __construct(
        private readonly TableQueryMetadataResolver $metadataResolver,
        private readonly ApplySearch $searchPipe,
        private readonly ApplySort $sortPipe,
    ) {}

    public function searchUsing(?callable $callback): static
    {
        if ($callback === null) {
            return $this;
        }

        $builder = clone $this;
        $builder->searchUsing = Closure::fromCallable($callback);

        return $builder;
    }

    /**
     * @param  TableState|TableQueryParameters|array<string, mixed>|null  $parameters
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, Table $table, TableState|TableQueryParameters|array|null $parameters = null): Builder
    {
        $context = new TableQueryContext(
            $query,
            $this->metadataResolver->resolve($table),
            $this->normalizeParameters($parameters),
            $this->searchUsing,
        );

        /** @var TableQueryContext $result */
        $result = new Pipeline()
            ->send($context)
            ->through([
                $this->searchPipe,
                $this->sortPipe,
            ])
            ->thenReturn();

        return $result->query;
    }

    /**
     * @param  TableState|TableQueryParameters|array<string, mixed>|null  $parameters
     */
    private function normalizeParameters(TableState|TableQueryParameters|array|null $parameters): TableQueryParameters
    {
        if ($parameters instanceof TableQueryParameters) {
            return $parameters;
        }

        if ($parameters instanceof TableState) {
            return TableQueryParameters::fromState($parameters);
        }

        if (is_array($parameters)) {
            return TableQueryParameters::fromArray($parameters);
        }

        return new TableQueryParameters();
    }
}
