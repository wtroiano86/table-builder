<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonSerializable;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Concerns\HasFactoryAlias;
use Wtroiano\TableBuilder\Contracts\TableState as TableStateContract;
use Wtroiano\TableBuilder\Enums\Direction;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Exceptions\TableBuilderException;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pagination\PaginationConfiguration;
use Wtroiano\TableBuilder\Pagination\TablePaginationBuilder;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Support\Url;
use Wtroiano\TableBuilder\Tables\TableDefinitions;
use Wtroiano\TableBuilder\Tables\TableEmptyState;
use Wtroiano\TableBuilder\Tables\TableNavigation;
use Wtroiano\TableBuilder\Tables\TablePayloadBuilder;
use Wtroiano\TableBuilder\Tables\TableState;
use Wtroiano\TableBuilder\Tables\TableStateSerializer;

/**
 * @implements Arrayable<string, mixed>
 *
 * @phpstan-import-type ColumnPayload from Column
 *
 * @phpstan-consistent-constructor
 */
abstract class Table implements Arrayable, JsonSerializable
{
    use HasFactoryAlias;

    /**
     * @var class-string<Model>|null
     */
    protected ?string $resource = null;

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

    protected ?bool $stickyHeader = false;

    protected ?string $rowSelectionKey = 'id';

    protected bool $selectable = true;

    protected bool $persistRowSelectionAcrossPages = false;

    /**
     * @var TableEmptyState|array<string, mixed>|false|null
     */
    protected TableEmptyState|array|false|null $emptyState = null;

    private ?string $tableName = null;

    private ?string $registeredTableKey = null;

    private ?TableDefinitions $resolvedDefinitions = null;

    public function __clone(): void
    {
        $this->resolvedDefinitions = null;
    }

    /**
     * @return list<Column>
     */
    abstract public function columns(): array;

    public static function make(): static
    {
        // The factory is inherited by concrete consumer subclasses.
        // @phpstan-ignore new.staticInAbstractClassStaticMethod
        return new static();
    }

    public function name(): string
    {
        return $this->tableName ?? $this->inferTableId();
    }

    public function isNamed(): bool
    {
        return $this->tableName !== null;
    }

    public function id(): string
    {
        return $this->name();
    }

    public function registryKey(): ?string
    {
        return $this->registeredTableKey;
    }

    public function reference(): ?string
    {
        return $this->registeredTableKey === null ? static::class : 'registry:'.$this->registeredTableKey;
    }

    /** @internal Applied by TableRegistry after resolving a trusted factory. */
    public function registeredAs(string $key): static
    {
        $table = clone $this;
        $table->registeredTableKey = $key;

        return $table;
    }

    public function named(string $name): static
    {
        $table = clone $this;
        $table->tableName = Str::of($name)->trim()->toString();

        if ($table->tableName === '') {
            throw TableBuilderException::emptyTableName();
        }

        return $table;
    }

    public function as(string $name): static
    {
        return $this->named($name);
    }

    /**
     * @return Builder<Model>|string|null
     */
    public function resource(): Builder|string|null
    {
        return $this->resource;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformModel(Model $model, array $data): array
    {
        return $data;
    }

    public function rowUrl(Model $model, Url $url): string|Url|null
    {
        return null;
    }

    /**
     * @return list<Action>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return list<Export>
     */
    public function exports(): array
    {
        return [];
    }

    public function isSelectable(Model $model): bool
    {
        return true;
    }

    public function authorize(Request $request): bool
    {
        return $request->user() !== null;
    }

    /** @return array<string, mixed> */
    public function exportContext(ExportRequest $request): array
    {
        return [];
    }

    /**
     * @template TResult
     *
     * @param  array<string, mixed>  $context
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function withExportContext(array $context, Closure $callback): mixed
    {
        return $callback();
    }

    public function selectable(): bool
    {
        return $this->selectable;
    }

    /**
     * @return array<string, mixed>|TableStateContract
     */
    public function defaultState(): array|TableStateContract
    {
        return $this->defaultSortState();
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $meta = [
            'columns' => $this->serializedColumnsDefinition(),
            'pagination' => $this->pagination()->toArray(),
            'queryString' => [
                'namespace' => $this->isNamed() ? $this->name() : null,
                'page' => $this->pageName(),
                'perPage' => $this->queryStringKey('perPage'),
                'cursor' => $this->cursorName(),
                'search' => $this->queryStringKey('search'),
                'sort' => $this->queryStringKey('sort'),
                'direction' => $this->queryStringKey('direction'),
                'columns' => $this->queryStringKey('columns'),
                'sticky' => $this->queryStringKey('sticky'),
            ],
            'table' => $this->reference(),
            'actionEndpoint' => route('table-builder.actions.execute', absolute: false),
            'exportEndpoint' => route('table-builder.exports.execute', absolute: false),
        ];

        if ($this->actionsDefinition() === [] && $this->exportsDefinition() === []) {
            unset($meta['table'], $meta['actionEndpoint'], $meta['exportEndpoint']);
        } elseif ($this->actionsDefinition() === []) {
            unset($meta['actionEndpoint']);
        } elseif ($this->exportsDefinition() === []) {
            unset($meta['exportEndpoint']);
        }

        return $meta;
    }

    /**
     * @return array<array-key, mixed>|false
     */
    public function emptyState(): TableEmptyState|array|false|null
    {
        return $this->emptyState;
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

    /**
     * @return array{
     *     name: string,
     *     state: array{
     *         page: int,
     *         perPage: int,
     *         cursor: ?string,
     *         search: ?string,
     *         sort: ?string,
     *         direction: string
     *     },
     *     meta: array<string, mixed>,
     *     rowSelectionKey: ?string,
     *     selectable: bool,
     *     persistRowSelectionAcrossPages: bool
     * }
     */
    public function payload(?TableRequest $request = null): array
    {
        return new TablePayloadBuilder()->build($this, $this->state($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->payload();
    }

    /**
     * @param  Builder<Model>|null  $query
     * @return Builder<Model>
     */
    public function apply(?Builder $query = null, ?TableRequest $request = null): Builder
    {
        /** @var TableQueryBuilder $queryBuilder */
        $queryBuilder = clone resolve(TableQueryBuilder::class);
        $queryBuilder = $this->withQueryBuilder($queryBuilder) ?? $queryBuilder;

        return $queryBuilder->apply($query ?? $this->query(), $this, $this->state($request));
    }

    /**
     * @param  Builder<Model>|null  $query
     */
    public function paginate(?Builder $query = null, ?TableRequest $request = null): mixed
    {
        /** @var TablePaginationBuilder $paginationBuilder */
        $paginationBuilder = resolve(TablePaginationBuilder::class);

        return $paginationBuilder->paginate($query ?? $this->query(), $this, $this->state($request));
    }

    /**
     * @param  Builder<Model>|null  $query
     * @return array<string, mixed>
     */
    public function results(?Builder $query = null, ?TableRequest $request = null): array
    {
        return $this->withResolvedDefinitions(function () use ($query, $request): array {
            $state = $this->state($request);

            /** @var TablePaginationBuilder $paginationBuilder */
            $paginationBuilder = resolve(TablePaginationBuilder::class);

            /** @var TablePayloadBuilder $payloadBuilder */
            $payloadBuilder = resolve(TablePayloadBuilder::class);

            $query ??= $this->query();

            return $payloadBuilder->buildResults(
                $this,
                $state,
                $paginationBuilder->paginate($query, $this, $state),
            );
        });
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        $resource = $this->resource();

        if ($resource instanceof Builder) {
            return clone $resource;
        }

        if (is_string($resource) && is_a($resource, Model::class, true)) {
            return $resource::query();
        }

        if ($resource === null) {
            throw TableBuilderException::missingTableResource(static::class);
        }

        throw TableBuilderException::invalidTableResource(static::class, $resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->results(request: TableRequest::fromRequest(request()));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function defaultSort(): ?string
    {
        return $this->defaultSort;
    }

    public function stickyHeader(): bool
    {
        return $this->stickyHeader ?? false;
    }

    public function rowSelectionKey(): ?string
    {
        $key = $this->rowSelectionKey;

        if ($key === null) {
            return null;
        }

        $key = mb_trim($key);

        return $key === '' ? null : $key;
    }

    public function selectionPayloadKey(): ?string
    {
        return $this->rowSelectionKey() === null ? null : '_selection_key';
    }

    public function persistsRowSelectionAcrossPages(): bool
    {
        return $this->persistRowSelectionAcrossPages;
    }

    public function navigation(?TableRequest $request = null): TableNavigation
    {
        return new TableNavigation($this, $this->state($request));
    }

    public function withQueryBuilder(TableQueryBuilder $queryBuilder): ?TableQueryBuilder
    {
        return $queryBuilder;
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

    /**
     * @return list<Column>
     */
    public function columnsDefinition(): array
    {
        return $this->resolvedDefinitions->columns ?? $this->normalizeDefinitions($this->columns(), Column::class, 'columns');
    }

    /**
     * @return list<ColumnPayload>
     *
     * @internal Serialized definitions are shared only within the current render.
     */
    public function serializedColumnsDefinition(): array
    {
        return $this->resolvedDefinitions?->serializedColumns() ?? array_map(
            static fn (Column $column): array => $column->toArray(),
            $this->columnsDefinition(),
        );
    }

    /**
     * @return list<Action>
     */
    public function actionsDefinition(): array
    {
        return $this->resolvedDefinitions->actions ?? $this->normalizeDefinitions($this->actions(), Action::class, 'actions');
    }

    /**
     * @return list<Export>
     */
    public function exportsDefinition(): array
    {
        return $this->resolvedDefinitions->exports ?? $this->normalizeDefinitions($this->exports(), Export::class, 'exports');
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     *
     * @internal Resolve once for a render and restore even if a callback throws.
     */
    public function withResolvedDefinitions(Closure $callback): mixed
    {
        $previous = $this->resolvedDefinitions;
        $this->resolvedDefinitions ??= new TableDefinitions(
            $this->columnsDefinition(),
            $this->actionsDefinition(),
            $this->exportsDefinition(),
        );

        try {
            return $callback();
        } finally {
            $this->resolvedDefinitions = $previous;
        }
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

    private function inferTableId(): string
    {
        $baseName = class_basename(static::class);

        if (str_ends_with($baseName, 'Table')) {
            $baseName = mb_substr($baseName, 0, -5);
        }

        return Str::kebab($baseName);
    }

    /**
     * @template TDefinition of object
     *
     * @param  array<int, mixed>  $definitions
     * @param  class-string<TDefinition>  $expectedClass
     * @return list<TDefinition>
     */
    private function normalizeDefinitions(array $definitions, string $expectedClass, string $segment): array
    {
        foreach ($definitions as $index => $definition) {
            if (! $definition instanceof $expectedClass) {
                throw TableBuilderException::invalidTableDefinition(static::class, $segment, $expectedClass, $index);
            }
        }

        return array_values($definitions);
    }
}
