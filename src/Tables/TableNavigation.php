<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables;

use Wtroiano\TableBuilder\Contracts\TableState;
use Wtroiano\TableBuilder\Enums\Direction;
use Wtroiano\TableBuilder\Query\TableQueryParameters;
use Wtroiano\TableBuilder\Table;

final readonly class TableNavigation
{
    public function __construct(
        private Table $table,
        private TableState $state,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(int $page): array
    {
        $state = TableStateSerializer::serialize($this->state);
        $state['page'] = $this->table->pagination()->normalizePage($page);
        $state['cursor'] = null;

        return $this->queryStringState($state, includePage: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function cursor(?string $cursor): array
    {
        $state = TableStateSerializer::serialize($this->state);
        $state['page'] = 1;
        $state['cursor'] = $this->table->pagination()->normalizeCursor($cursor);

        return $this->queryStringState($state, includePage: true, includeCursor: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function perPage(int $perPage): array
    {
        $state = TableStateSerializer::serialize($this->state);
        $state['page'] = 1;
        $state['cursor'] = null;
        $state['perPage'] = $this->table->pagination()->normalizePerPage($perPage);

        return $this->queryStringState($state, includePage: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function search(?string $search): array
    {
        $state = TableStateSerializer::serialize($this->state);
        $state['search'] = TableQueryParameters::fromArray(['search' => $search])->search();
        $state['page'] = 1;
        $state['cursor'] = null;

        return $this->queryStringState($state, includePage: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function sort(?string $sort, string|Direction|null $direction = null): array
    {
        $state = TableStateSerializer::serialize($this->state);
        $state['sort'] = TableQueryParameters::fromArray(['sort' => $sort])->sort();
        $state['direction'] = TableQueryParameters::fromArray(['direction' => $direction ?? $this->state->direction()])->direction();
        $state['page'] = 1;
        $state['cursor'] = null;

        return $this->queryStringState($state, includePage: true);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function queryStringState(array $state, bool $includePage = false, bool $includeCursor = false): array
    {
        return TableStateSerializer::queryString(
            $state,
            $this->table->pagination()->defaultPerPage(),
            $this->table->isNamed() ? $this->table->name() : null,
            $includePage,
            $includeCursor,
            $this->table->state(),
        );
    }
}
