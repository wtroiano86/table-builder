<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables;

use Illuminate\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Contracts\TableState as TableStateContract;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\RowSerializationPipeline;
use Wtroiano\TableBuilder\Table;

/** @phpstan-import-type ColumnPayload from Column */
final readonly class TablePayloadBuilder
{
    public function __construct(private RowSerializationPipeline $rowSerializer = new RowSerializationPipeline()) {}

    /**
     * @return array{name: string, state: array{page: int, perPage: int, cursor: ?string, search: ?string, sort: ?string, direction: string}, meta: array<string, mixed>, rowSelectionKey: ?string, selectable: bool, persistRowSelectionAcrossPages: bool}
     */
    public function build(Table $table, TableStateContract $state): array
    {
        return $table->withResolvedDefinitions(fn (): array => [
            'name' => $table->name(),
            'state' => [
                'page' => $state->page(),
                'perPage' => $state->perPage(),
                'cursor' => $state->cursor(),
                'search' => $state->search(),
                'sort' => $state->sort(),
                'direction' => $state->direction(),
            ],
            'meta' => $table->meta(),
            'rowSelectionKey' => $table->selectionPayloadKey(),
            'selectable' => $table->selectable(),
            'persistRowSelectionAcrossPages' => $table->persistsRowSelectionAcrossPages(),
        ]);
    }

    /** @return array<string, mixed> */
    public function buildResults(Table $table, TableStateContract $state, mixed $results): array
    {
        return $table->withResolvedDefinitions(function () use ($table, $state, $results): array {
            $configuration = $table->pagination();
            $bulkActions = array_values(array_filter(array_map(
                static fn (Action $action): ?array => $action->forBulk(),
                $table->actionsDefinition(),
            )));
            $authorizedExports = array_values(array_filter(
                $table->exportsDefinition(),
                static fn (Export $export): bool => $export->isAuthorized($table),
            ));
            $exports = array_map(static fn (Export $export): array => $export->toArray(), $authorizedExports);

            return [
                'name' => $table->name(),
                'results' => $this->serializeResults($table, $results),
                'meta' => $table->meta(),
                'search' => [],
                'columns' => array_map(
                    $this->serializeColumn(...),
                    $table->serializedColumnsDefinition(),
                ),
                'actions' => $bulkActions,
                'exports' => $exports,
                'state' => [
                    'columns' => $this->visibleColumns($table, $state),
                    'perPage' => $state->perPage(),
                    'search' => $state->search(),
                    'sort' => $state->sort(),
                    'direction' => $state->direction(),
                    'sticky' => $this->stickyColumns($table, $state),
                ],
                'pagination' => $configuration->enabled(),
                'paginationType' => $this->paginationType($configuration->type()),
                'perPageOptions' => $configuration->perPageOptions(),
                'defaultPerPage' => $configuration->defaultPerPage(),
                'defaultSort' => $table->defaultSort(),
                'debounceTime' => 300,
                'reloadProps' => [],
                'hasActions' => $table->actionsDefinition() !== [],
                'hasBulkActions' => $bulkActions !== [],
                'hasExports' => $exports !== [],
                'hasExportsThatLimitsToSelectedRows' => collect($authorizedExports)->contains(
                    static fn (Export $export): bool => $export->isLimitedToSelectedRows(),
                ),
                'hasFilters' => false,
                'hasSearch' => $table->searchable() !== [] || array_any($table->columnsDefinition(), static fn (Column $column): bool => $column->isSearchable()),
                'hasToggleableColumns' => array_any($table->columnsDefinition(), static fn (Column $column): bool => $column->isToggleable()),
                'scrollPositionAfterPageChange' => $configuration->scrollToTop() ? 'topOfPage' : 'preserve',
                'autofocus' => 'search',
                'emptyState' => $this->emptyState($table),
                'stickyHeader' => $table->stickyHeader(),
                'rowSelectionKey' => $table->selectionPayloadKey(),
                'selectable' => $table->selectable(),
                'persistRowSelectionAcrossPages' => $table->persistsRowSelectionAcrossPages(),
                'selection' => [
                    'mode' => 'page',
                ],
                'inDefaultState' => $this->inDefaultState($table, $state),
            ];
        });
    }

    /** @return array<array-key, mixed> */
    private function serializeResults(Table $table, mixed $results): array
    {
        if ($results instanceof LengthAwarePaginator || $results instanceof Paginator || $results instanceof CursorPaginatorContract) {
            $payload = $this->paginationMetadata($results);
            $payload['data'] = array_map(
                fn (mixed $record): array => $this->rowSerializer->serialize($table, $record),
                $results->items(),
            );
            $payload['on_first_page'] = $results->onFirstPage();
            $payload['on_last_page'] = $results->onLastPage();

            return $payload;
        }

        if ($results instanceof Collection) {
            return $results
                ->map(fn (mixed $record): array => $this->rowSerializer->serialize($table, $record))
                ->values()
                ->all();
        }

        return is_array($results) ? $results : [];
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>|Paginator<int, mixed>|CursorPaginatorContract<int, mixed>  $paginator
     * @return array<string, mixed>
     */
    private function paginationMetadata(LengthAwarePaginator|Paginator|CursorPaginatorContract $paginator): array
    {
        $metadata = [
            'path' => $paginator->path(),
            'per_page' => $paginator->perPage(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
        ];

        if ($paginator instanceof CursorPaginatorContract) {
            return array_replace($metadata, [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
            ]);
        }

        $metadata = array_replace($metadata, [
            'current_page' => $paginator->currentPage(),
            'first_page_url' => $paginator->url(1),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ]);

        if ($paginator instanceof LengthAwarePaginator) {
            return array_replace($metadata, [
                'last_page' => $paginator->lastPage(),
                'last_page_url' => $paginator->url($paginator->lastPage()),
                'links' => $paginator->linkCollection()->toArray(),
                'total' => $paginator->total(),
            ]);
        }

        return array_replace($metadata, ['current_page_url' => $paginator->url($paginator->currentPage())]);
    }

    private function paginationType(PaginationType $type): string
    {
        return match ($type) {
            PaginationType::Standard => 'full',
            PaginationType::Simple => 'simple',
            PaginationType::Cursor => 'cursor',
        };
    }

    /**
     * @param  ColumnPayload  $definition
     * @return array<string, mixed>
     */
    private function serializeColumn(array $definition): array
    {
        return [
            'type' => $definition['type'],
            'header' => $definition['label'],
            'attribute' => $definition['key'],
            'sortable' => $definition['sortable'],
            'toggleable' => $definition['toggleable'],
            'alignment' => $definition['alignment'] ?? 'left',
            'visibleByDefault' => $definition['visible'],
            'meta' => array_replace([
                'hidden' => ! $definition['visible'],
                'sortable' => $definition['sortable'],
                'toggleable' => $definition['toggleable'],
                'stickable' => $definition['sticky'],
                'defaultToSticky' => $definition['sticky'],
            ], $definition['meta']),
            'wrap' => false,
            'tooltip' => $definition['tooltip'] ?? null,
            'truncate' => null,
            'headerClass' => $definition['labelClass'],
            'cellClass' => $definition['cellClass'],
            'stickable' => $definition['sticky'],
        ];
    }

    /** @return array<string, bool> */
    private function visibleColumns(Table $table, TableStateContract $state): array
    {
        $columns = [];
        $hiddenColumns = $state->columns();

        foreach ($table->serializedColumnsDefinition() as $definition) {
            $columns[$definition['key']] = ! in_array($definition['key'], $hiddenColumns, true);
        }

        return $columns;
    }

    /** @return list<string> */
    private function stickyColumns(Table $table, TableStateContract $state): array
    {
        if ($state->hasStickyOverride()) {
            return $state->sticky();
        }

        $columns = [];

        foreach ($table->serializedColumnsDefinition() as $definition) {
            if ($definition['sticky']) {
                $columns[] = $definition['key'];
            }
        }

        return $columns;
    }

    private function inDefaultState(Table $table, TableStateContract $state): bool
    {
        return TableStateSerializer::serialize($state) === TableStateSerializer::serialize($table->state());
    }

    /**
     * @return array<array-key, mixed>|false
     */
    private function emptyState(Table $table): array|false
    {
        $emptyState = $table->emptyState();

        if ($emptyState === false || $emptyState === null) {
            return false;
        }

        if ($emptyState instanceof TableEmptyState) {
            return $emptyState->toArray();
        }

        return $emptyState;
    }
}
