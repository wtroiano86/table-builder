<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables;

use Wtroiano\TableBuilder\Contracts\TableState as TableStateContract;
use Wtroiano\TableBuilder\Enums\Direction;
use Wtroiano\TableBuilder\Exceptions\TableBuilderException;
use Wtroiano\TableBuilder\Pagination\PaginationConfiguration;
use Wtroiano\TableBuilder\Query\TableQueryParameters;

final readonly class TableState implements TableStateContract
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $sticky
     */
    public function __construct(
        private int $page = 1,
        private int $perPage = 15,
        private ?string $cursor = null,
        private ?string $search = null,
        private ?string $sort = null,
        private Direction $direction = Direction::ASCENDING,
        private array $columns = [],
        private array $sticky = [],
        private bool $hasStickyOverride = false,
    ) {
        if ($this->page < 1) {
            throw TableBuilderException::invalidPage();
        }

        if ($this->perPage < 1) {
            throw TableBuilderException::invalidPerPage();
        }
    }

    /**
     * @param  array<array-key, mixed>  $state
     */
    public static function fromArray(array $state, ?PaginationConfiguration $pagination = null): self
    {
        $pagination ??= PaginationConfiguration::make();
        $parameters = TableQueryParameters::fromArray($state);

        return new self(
            page: $pagination->normalizePage($state['page'] ?? 1),
            perPage: $pagination->normalizePerPage($state['perPage'] ?? $pagination->defaultPerPage()),
            cursor: $pagination->normalizeCursor($state['cursor'] ?? null),
            search: $parameters->search(),
            sort: $parameters->sort(),
            direction: Direction::from($parameters->direction()),
            columns: self::normalizeColumns($state['columns'] ?? []),
            sticky: self::normalizeColumns($state['sticky'] ?? []),
            hasStickyOverride: array_key_exists('sticky', $state),
        );
    }

    /**
     * @param  array<array-key, mixed>  $state
     */
    public function merge(array $state, ?PaginationConfiguration $pagination = null): self
    {
        if ($pagination instanceof PaginationConfiguration) {
            $pagination = PaginationConfiguration::make(
                enabled: $pagination->enabled(),
                type: $pagination->type(),
                defaultPerPage: $this->perPage,
                perPageOptions: $pagination->perPageOptions(),
                scrollToTop: $pagination->scrollToTop(),
            );
        }

        return self::fromArray(array_replace(TableStateSerializer::serialize($this), $state), $pagination);
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function cursor(): ?string
    {
        return $this->cursor;
    }

    public function sort(): ?string
    {
        return $this->sort;
    }

    public function search(): ?string
    {
        return $this->search;
    }

    public function direction(): string
    {
        return $this->direction->value;
    }

    /**
     * @return list<string>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<string>
     */
    public function sticky(): array
    {
        return $this->sticky;
    }

    public function hasStickyOverride(): bool
    {
        return $this->hasStickyOverride;
    }

    /**
     * @return array{
     *     page: int,
     *     perPage: int,
     *     cursor: ?string,
     *     search: ?string,
     *     sort: ?string,
     *     direction: string
     * }
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'perPage' => $this->perPage,
            'cursor' => $this->cursor,
            'search' => $this->search,
            'sort' => $this->sort,
            'direction' => $this->direction->value,
        ];
    }

    /**
     * @return list<string>
     */
    private static function normalizeColumns(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $columns = [];

        foreach ($value as $column) {
            if (! is_string($column)) {
                continue;
            }

            $column = mb_trim($column);

            if ($column === '') {
                continue;
            }

            $columns[] = $column;
        }

        return array_values(array_unique($columns));
    }
}
