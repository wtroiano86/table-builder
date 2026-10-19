<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\TableQuery\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryContext;
use Wtroiano\TableBuilder\Query\QuerySource;
use Wtroiano\TableBuilder\Query\RelationFieldResolver;
use Wtroiano\TableBuilder\Query\SearchTermParser;
use Wtroiano\TableBuilder\Query\TableQueryMetadata;
use Wtroiano\TableBuilder\Support\Callback;

final readonly class ApplySearch
{
    public function __construct(
        private SearchTermParser $searchTermParser,
        private RelationFieldResolver $relationFieldResolver,
    ) {}

    /** @param Closure(TableQueryContext): TableQueryContext $next */
    public function handle(TableQueryContext $context, Closure $next): TableQueryContext
    {
        $this->apply($context->query, $context->metadata, $context->parameters->search(), $context->searchUsing);

        return $next($context);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, TableQueryMetadata $metadata, ?string $search, ?Closure $searchUsing = null): void
    {
        $terms = $this->searchTermParser->parse($search);

        if ($terms === []) {
            return;
        }

        if ($searchUsing instanceof Closure) {
            $query->where(function (Builder $searchQuery) use ($searchUsing, $search, $terms): void {
                Callback::invoke($searchUsing, $searchQuery, $search, new Collection($terms));
            });

            return;
        }

        $searchableColumns = $metadata->searchableColumns();

        if ($searchableColumns === []) {
            return;
        }

        $query->where(function (Builder $termQuery) use ($searchableColumns, $terms, $query): void {
            foreach ($terms as $term) {
                $termQuery->where(function (Builder $fieldQuery) use ($searchableColumns, $term, $query): void {
                    foreach ($searchableColumns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';

                        $fieldQuery->{$method}(function (Builder $columnQuery) use ($column, $term, $query): void {
                            $callback = $column->searchCallback();

                            if ($callback !== null) {
                                Callback::invoke($callback, $columnQuery, $term, $column);

                                return;
                            }

                            $resolvedRelationField = $this->relationFieldResolver->resolve($column->field(), $query);

                            if ($resolvedRelationField !== null) {
                                $columnQuery->setModel(QuerySource::relationModel($query));
                                $columnQuery->whereHas(
                                    $resolvedRelationField['relation'],
                                    static function (Builder $relationQuery) use ($resolvedRelationField, $term): void {
                                        $relationQuery->where(QuerySource::qualify($relationQuery, $resolvedRelationField['column']), 'like', '%'.$term.'%');
                                    },
                                );

                                return;
                            }

                            $columnQuery->where(QuerySource::qualify($query, $column->field()), 'like', '%'.$term.'%');
                        });
                    }
                });
            }
        });
    }
}
