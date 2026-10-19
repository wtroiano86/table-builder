<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pagination;

use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Wtroiano\TableBuilder\Contracts\TableState;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Query\QuerySource;
use Wtroiano\TableBuilder\Table;

final readonly class TablePaginationBuilder
{
    public function __construct(
        private TableQueryBuilder $queryBuilder,
        private TableCursorPaginationBuilder $cursorBuilder = new TableCursorPaginationBuilder(),
    ) {}

    /**
     * @param  Builder<Model>  $query
     * @return LengthAwarePaginator<int, Model>|Paginator<int, Model>|CursorPaginatorContract<int, Model>|Collection<int, Model>
     */
    public function paginate(Builder $query, Table $table, TableState $state): LengthAwarePaginator|Paginator|CursorPaginatorContract|Collection
    {
        $queryBuilder = clone $this->queryBuilder;
        $queryBuilder = $table->withQueryBuilder($queryBuilder) ?? $queryBuilder;

        $query = $queryBuilder->apply($query, $table, $state);
        QuerySource::ensureIdentitySelected($query, $table);
        $configuration = $table->pagination();

        if (! $configuration->enabled()) {
            return $query->get();
        }

        $paginator = match ($configuration->type()) {
            PaginationType::Standard => $query->paginate(
                perPage: $state->perPage(),
                pageName: $table->pageName(),
                page: $state->page(),
            ),
            PaginationType::Simple => $query->simplePaginate(
                perPage: $state->perPage(),
                pageName: $table->pageName(),
                page: $state->page(),
            ),
            PaginationType::Cursor => $this->cursorBuilder->paginate($query, $table, $state),
        };

        $parameters = request()->query();
        if ($table->isNamed()) {
            unset($parameters[$table->name()]);
        } else {
            $parameters = array_diff_key($parameters, TableRequest::fromArray($parameters)->for($table));
            unset($parameters[$table->name()]);
        }

        $paginator->appends(array_replace($parameters, $table->queryStringState($state)));

        return $paginator;
    }
}
