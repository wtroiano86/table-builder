<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\TableQuery\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryContext;
use Wtroiano\TableBuilder\Query\QuerySource;
use Wtroiano\TableBuilder\Query\RelationFieldResolver;
use Wtroiano\TableBuilder\Query\ResolvedTableQueryColumn;
use Wtroiano\TableBuilder\Query\TableQueryMetadata;
use Wtroiano\TableBuilder\Support\Callback;

final readonly class ApplySort
{
    public function __construct(
        private RelationFieldResolver $relationFieldResolver,
    ) {}

    /** @param Closure(TableQueryContext): TableQueryContext $next */
    public function handle(TableQueryContext $context, Closure $next): TableQueryContext
    {
        $this->apply($context->query, $context->metadata, $context->parameters->sort(), $context->parameters->direction());

        return $next($context);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, TableQueryMetadata $metadata, ?string $sort, string $direction): void
    {
        $column = $metadata->sortableColumn($sort);

        if (! $column instanceof ResolvedTableQueryColumn) {
            return;
        }

        $callback = $column->sortCallback();

        if ($callback instanceof Closure) {
            $query->reorder();
            Callback::invoke($callback, $query, $direction, $column);

            return;
        }

        $resolvedRelationField = $this->relationFieldResolver->resolve($column->field(), $query);

        if ($resolvedRelationField !== null) {
            $this->applyRelationSort($query, $resolvedRelationField['relation'], $resolvedRelationField['column'], $direction);

            return;
        }

        $query->reorder()->orderBy(QuerySource::qualify($query, $column->field()), $direction === 'desc' ? 'desc' : 'asc');
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyRelationSort(Builder $query, string $relationPath, string $column, string $direction): void
    {
        if (! $this->isSafeIdentifier($column)) {
            return;
        }

        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $subquery = $this->relationSortQuery($query, explode('.', $relationPath), $column, $direction);

        if ($subquery instanceof Builder) {
            $query->reorder();
            if ($query->getQuery()->columns === null) {
                $query->select(QuerySource::table($query).'.*');
            }

            $query->selectSub($subquery, '_inertia_relation_sort')->orderBy('_inertia_relation_sort', $direction);
        }
    }

    /**
     * @param  Builder<Model>  $parentQuery
     * @param  list<string>  $segments
     * @param  'asc'|'desc'  $direction
     * @return Builder<Model>|null
     */
    private function relationSortQuery(Builder $parentQuery, array $segments, string $column, string $direction): ?Builder
    {
        $segment = array_shift($segments);
        $model = QuerySource::relationModel($parentQuery);

        if ($segment === null || ! $this->isSafeIdentifier($segment) || ! method_exists($model, $segment)) {
            return null;
        }

        $relation = Relation::noConstraints(static fn (): mixed => $model->{$segment}());

        if (! $relation instanceof BelongsTo && ! $relation instanceof HasOneOrMany) {
            return null;
        }

        $relatedTable = $relation->getRelated()->getTable();
        $query = $relation->getRelationExistenceQuery($relation->getRelated()->newQuery(), $parentQuery);
        $query->mergeConstraintsFrom($relation->getQuery());

        $singular = $relation instanceof HasOne || $relation instanceof BelongsTo;

        if ($singular) {
            $orders = $relation->getQuery()->getQuery()->orders;
            foreach ($orders ?? [] as $index => $order) {
                if (is_array($order) && is_string($order['column'] ?? null) && str_starts_with($order['column'], $relatedTable.'.')) {
                    $order['column'] = QuerySource::qualify($query, mb_substr($order['column'], mb_strlen($relatedTable) + 1));
                    $orders[$index] = $order;
                }
            }

            $query->getQuery()->orders = $orders;
            $query->getQuery()->setBindings($relation->getQuery()->getQuery()->getRawBindings()['order'], 'order');
            $query = $query->applyScopes()->withoutGlobalScopes();
            if (($query->getQuery()->orders ?? []) === []) {
                $query->orderBy(QuerySource::qualify($query, $query->getModel()->getKeyName()));
            }
        } else {
            $query->reorder();
        }

        $query->select([]);

        if ($segments !== []) {
            $nested = $this->relationSortQuery($query, $segments, $column, $direction);

            if (! $nested instanceof Builder) {
                return null;
            }

            $query->selectSub($nested, 'inertia_sort_value');
            if (! $singular) {
                $query->whereHas(implode('.', $segments), static function (Builder $related) use ($column): void {
                    $related->whereNotNull($related->getModel()->qualifyColumn($column));
                })
                    ->orderBy('inertia_sort_value', $direction);
            }
        } else {
            $qualifiedColumn = QuerySource::qualify($query, $column);
            $query->select($qualifiedColumn);
            if (! $singular) {
                $query->whereNotNull($qualifiedColumn)->orderBy($qualifiedColumn, $direction);
            }
        }

        return $query->limit(1);
    }

    private function isSafeIdentifier(string $identifier): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) === 1;
    }
}
