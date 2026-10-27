<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pagination;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use InvalidArgumentException;
use Wtroiano\TableBuilder\Contracts\TableState;
use Wtroiano\TableBuilder\Query\QuerySource;
use Wtroiano\TableBuilder\Table;

final class TableCursorPaginationBuilder
{
    /**
     * @param  Builder<Model>  $query
     * @return CursorPaginator<int, Model>
     */
    public function paginate(Builder $query, Table $table, TableState $state): CursorPaginator
    {
        $base = clone $query->toBase();
        $scoped = clone $query;
        $scoped->setQuery($base);

        $orders = $this->prepareCursorColumns($scoped);

        // Materialize computed values once so cursor predicates use columns, including bindings.
        $outer = clone $query;
        $outer->withoutGlobalScopes()->setQuery($base->newQuery()->fromSub($base, '_inertia_cursor')->select('_inertia_cursor.*'));

        $cursor = $this->decodeCursor($state->cursor(), $orders);
        $reverse = $cursor?->pointsToPreviousItems() ?? false;

        if ($cursor instanceof Cursor) {
            $this->constrain($outer, $orders, $cursor, $reverse);
        }

        $this->applyCursorOrder($outer, $orders, $reverse);

        return new CursorPaginator($outer->limit($state->perPage() + 1)->get(), $state->perPage(), $cursor, [
            'path' => Paginator::resolveCurrentPath(),
            'cursorName' => $table->cursorName(),
            'parameters' => array_column($orders, 'column'),
        ]);
    }

    /**
     * @param  Builder<Model>  $scoped
     * @return list<array{column: string, direction: 'asc'|'desc'}>
     */
    private function prepareCursorColumns(Builder $scoped): array
    {
        $base = $scoped->getQuery();
        $orders = $base->orders ?? [];
        $key = QuerySource::qualify($scoped, $scoped->getModel()->getKeyName());

        if (! array_any($orders, static fn (mixed $order): bool => is_array($order) && is_string($order['column'] ?? null) && QuerySource::qualify($scoped, $order['column']) === $key)) {
            $orders[] = ['column' => $key, 'direction' => 'asc'];
        }

        if ($base->columns === null) {
            $base->select(QuerySource::table($scoped).'.*');
        }

        QuerySource::ensureKeySelected($scoped);

        /** @var list<array{column: string, direction: 'asc'|'desc'}> $cursorOrders */
        $cursorOrders = [];
        foreach ($orders as $index => $order) {
            throw_if(! is_array($order) || ! is_string($order['column'] ?? null), InvalidArgumentException::class, 'Cursor pagination requires named sort columns. Select expressions under an alias before ordering.');

            $column = $order['column'];
            $alias = $this->isSelectedAlias($base, $column) ? $column : '_inertia_cursor_sort_'.$index;
            if ($alias !== $column) {
                $base->addSelect(QuerySource::qualify($scoped, $column).' as '.$alias);
            }

            $cursorOrders[] = ['column' => $alias, 'direction' => ($order['direction'] ?? null) === 'desc' ? 'desc' : 'asc'];
        }

        // Limits and offsets select a dataset before cursor navigation is applied.
        if ($base->limit !== null || $base->offset !== null) {
            $base->orders = $orders;
        } else {
            $base->reorder();
        }

        return $cursorOrders;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<array{column: string, direction: 'asc'|'desc'}>  $orders
     */
    private function applyCursorOrder(Builder $query, array $orders, bool $reverse): void
    {
        foreach ($orders as $order) {
            $direction = $reverse ? ($order['direction'] === 'asc' ? 'desc' : 'asc') : $order['direction'];
            $query->orderBy(new NullsLastOrder($order['column']), $reverse ? 'desc' : 'asc')->orderBy($order['column'], $direction);
        }
    }

    private function isSelectedAlias(QueryBuilder $query, string $column): bool
    {
        $grammar = $query->getGrammar();
        foreach ($query->columns ?? [] as $selected) {
            $value = $selected instanceof Expression ? $selected->getValue($grammar) : $selected;
            if (is_string($value) && (str_ends_with(mb_strtolower($value), ' as '.mb_strtolower($grammar->wrap($column))) || str_ends_with(mb_strtolower($value), ' as '.mb_strtolower($column)))) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{column: string, direction: 'asc'|'desc'}> $orders */
    private function decodeCursor(?string $encoded, array $orders): ?Cursor
    {
        $cursor = Cursor::fromEncoded($encoded);
        if (! $cursor instanceof Cursor) {
            return null;
        }

        $parameters = $cursor->toArray();
        if (! is_bool($parameters['_pointsToNextItems'] ?? null)) {
            return null;
        }

        foreach ($orders as $order) {
            if (! array_key_exists($order['column'], $parameters)) {
                return null;
            }

            $value = $parameters[$order['column']];
            if ($value !== null && ! is_scalar($value)) {
                return null;
            }
        }

        return $cursor;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<array{column: string, direction: 'asc'|'desc'}>  $orders
     */
    private function constrain(Builder $query, array $orders, Cursor $cursor, bool $reverse): void
    {
        $query->where(static function (Builder $where) use ($orders, $cursor, $reverse): void {
            foreach ($orders as $index => $order) {
                $where->orWhere(static function (Builder $branch) use ($orders, $index, $order, $cursor, $reverse): void {
                    foreach (array_slice($orders, 0, $index) as $previous) {
                        $value = $cursor->parameter($previous['column']);
                        $value === null ? $branch->whereNull($previous['column']) : $branch->where($previous['column'], '=', $value);
                    }

                    $value = $cursor->parameter($order['column']);
                    if ($value === null) {
                        $reverse ? $branch->whereNotNull($order['column']) : $branch->whereRaw('1 = 0');

                        return;
                    }

                    $ascending = ($order['direction'] === 'asc') !== $reverse;
                    $branch->where(static function (Builder $comparison) use ($order, $value, $ascending, $reverse): void {
                        $comparison->where($order['column'], $ascending ? '>' : '<', $value);
                        if (! $reverse) {
                            $comparison->orWhereNull($order['column']);
                        }
                    });
                });
            }
        });
    }
}
