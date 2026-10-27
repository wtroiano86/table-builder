<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables\Concerns;

use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Contracts\TableState as TableStateContract;
use Wtroiano\TableBuilder\Enums\Direction;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pagination\PaginationConfiguration;
use Wtroiano\TableBuilder\Tables\TableNavigation;
use Wtroiano\TableBuilder\Tables\TableState;
use Wtroiano\TableBuilder\Tables\TableStateSerializer;

trait InteractsWithTableState
{
    /**
     * @var array<array-key, mixed>|string|null
     */
    protected array|string|null $search = null;

    protected ?string $defaultSort = null;

    protected bool $pagination = true;

    protected PaginationType $paginationType = PaginationType::Standard;

    /**
     * @var array<int, int|string>|null
     */
    protected ?array $perPageOptions = null;

    protected ?int $defaultPerPage = null;

    protected bool $scrollToTop = true;

    /**
     * @return array<string, mixed>|TableStateContract
     */
    public function defaultState(): array|TableStateContract
    {
        return $this->defaultSortState();
    }

    public function state(?TableRequest $request = null): TableState
    {
        $defaultState = $this->defaultState();
        $configuration = $this->pagination();
        $defaults = $defaultState instanceof TableStateContract ? TableStateSerializer::serialize($defaultState) : $defaultState;
        $defaults['columns'] ??= $this->defaultHiddenColumns();
        $state = TableState::fromArray($defaults, $configuration);

        if (! $request instanceof TableRequest) {
            return $state;
        }

        return $state->merge($request->for($this), $configuration);
    }

    public function stateFromRequest(Request $request): TableState
    {
        return $this->state(TableRequest::fromRequest($request));
    }

    public function defaultSort(): ?string
    {
        return $this->defaultSort;
    }

    public function navigation(?TableRequest $request = null): TableNavigation
    {
        return new TableNavigation($this, $this->state($request));
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        if (is_string($this->search)) {
            $search = [$this->search];
        } elseif (is_array($this->search)) {
            $search = $this->search;
        } else {
            $search = [];
        }

        $fields = [];

        foreach ($search as $field) {
            if (! is_string($field)) {
                continue;
            }

            $field = mb_trim($field);

            if ($field === '') {
                continue;
            }

            $fields[] = $field;
        }

        return array_values(array_unique($fields));
    }

    public function pagination(): PaginationConfiguration
    {
        $defaultPerPage = $this->defaultPerPage ?? config('table-builder.pagination.default_per_page');
        $perPageOptions = $this->perPageOptions ?? config('table-builder.pagination.per_page_options');

        $resolvedDefaultPerPage = (is_numeric($defaultPerPage) ? (int) $defaultPerPage : 15);
        $resolvedPerPageOptions = is_array($perPageOptions) ? array_values($perPageOptions) : [15, 30, 50, 100];

        if ($this->perPageOptions === null && ! in_array($resolvedDefaultPerPage, $resolvedPerPageOptions, true)) {
            $resolvedPerPageOptions[] = $resolvedDefaultPerPage;
            sort($resolvedPerPageOptions);
        }

        return PaginationConfiguration::make(
            enabled: $this->pagination,
            type: $this->paginationType,
            defaultPerPage: $resolvedDefaultPerPage,
            perPageOptions: $resolvedPerPageOptions,
            scrollToTop: $this->scrollToTop,
        );
    }

    public function pageName(): string
    {
        return $this->queryStringKey('page');
    }

    public function cursorName(): string
    {
        return $this->queryStringKey('cursor');
    }

    /**
     * @return array<string, mixed>
     */
    public function queryStringState(TableStateContract $state, bool $includePage = false): array
    {
        return TableStateSerializer::queryString(
            TableStateSerializer::serialize($state),
            $this->pagination()->defaultPerPage(),
            $this->isNamed() ? $this->name() : null,
            $includePage,
            defaults: $this->state(),
        );
    }

    private function queryStringKey(string $key): string
    {
        return $this->isNamed() ? $this->name().'['.$key.']' : $key;
    }

    private function defaultSortState(): TableState
    {
        if ($this->defaultSort === null) {
            return new TableState(perPage: $this->pagination()->defaultPerPage(), columns: $this->defaultHiddenColumns());
        }

        $sort = mb_trim($this->defaultSort);

        if ($sort === '') {
            return new TableState(perPage: $this->pagination()->defaultPerPage(), columns: $this->defaultHiddenColumns());
        }

        $direction = Direction::ASCENDING;

        if (str_starts_with($sort, '-')) {
            $direction = Direction::DESCENDING;
            $sort = mb_trim(mb_substr($sort, 1));
        }

        if ($sort === '') {
            return new TableState(perPage: $this->pagination()->defaultPerPage(), columns: $this->defaultHiddenColumns());
        }

        return new TableState(
            perPage: $this->pagination()->defaultPerPage(),
            sort: $sort,
            direction: $direction,
            columns: $this->defaultHiddenColumns(),
        );
    }

    /** @return list<string> */
    private function defaultHiddenColumns(): array
    {
        return array_values(array_map(
            static fn (Column $column): string => $column->resolvedKey(),
            array_filter($this->columnsDefinition(), static fn (Column $column): bool => ! $column->isVisible()),
        ));
    }
}
