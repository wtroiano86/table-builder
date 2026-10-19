<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Contracts\TableState;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Support\Url;
use Wtroiano\TableBuilder\Table;

/** @internal Table adapter for fluent configuration. */
final class ConfiguredTable extends Table
{
    private TableConfiguration $configuration;

    public function __construct()
    {
        $this->configuration = new TableConfiguration();
    }

    public function __clone(): void
    {
        parent::__clone();
        $this->configuration = clone $this->configuration;
        $this->emptyState = $this->configuration->emptyState;
    }

    public static function fromConfiguration(TableConfiguration $configuration): self
    {
        $table = new self();
        $table->configuration = clone $configuration;

        $configuration = $table->configuration;
        $table->defaultSort = $configuration->defaultSort;
        $table->defaultPerPage = $configuration->perPage;
        $table->perPageOptions = $configuration->perPageOptions;
        $table->pagination = $configuration->pagination;
        $table->paginationType = $configuration->paginationType;
        $table->scrollToTop = $configuration->scrollToTop;
        $table->stickyHeader = $configuration->stickyHeader;
        $table->rowSelectionKey = $configuration->rowSelectionKey;
        $table->selectable = $configuration->selectable;
        $table->persistRowSelectionAcrossPages = $configuration->persistRowSelectionAcrossPages;
        $table->emptyState = $configuration->emptyState;
        $table->search = $configuration->searchable;

        return $table->named($configuration->name);
    }

    public function reference(): ?string
    {
        return $this->registryKey() === null ? null : parent::reference();
    }

    public function resource(): Builder|string|null
    {
        $source = $this->configuration->resource;

        return $source instanceof Closure ? $source() : $source;
    }

    /** @return list<Column> */
    public function columns(): array
    {
        $definitions = $this->configuration->columns;

        return $definitions instanceof Closure ? $definitions($this) : $definitions;
    }

    /** @return list<Action> */
    public function actions(): array
    {
        $definitions = $this->configuration->actions;

        return $definitions instanceof Closure ? $definitions($this) : $definitions;
    }

    /** @return list<Export> */
    public function exports(): array
    {
        $definitions = $this->configuration->exports;

        return $definitions instanceof Closure ? $definitions($this) : $definitions;
    }

    public function authorize(Request $request): bool
    {
        $authorize = $this->configuration->authorize;

        return $authorize instanceof Closure ? $authorize($request) : ($authorize ?? parent::authorize($request));
    }

    public function defaultState(): array|TableState
    {
        return $this->configuration->defaultState ?? parent::defaultState();
    }

    public function isSelectable(Model $model): bool
    {
        return ! $this->configuration->selectableUsing instanceof Closure || ($this->configuration->selectableUsing)($model);
    }

    public function rowUrl(Model $model, Url $url): string|Url|null
    {
        return $this->configuration->rowUrl instanceof Closure ? ($this->configuration->rowUrl)($model, $url) : null;
    }

    public function transformModel(Model $model, array $data): array
    {
        return $this->configuration->transformModel instanceof Closure ? ($this->configuration->transformModel)($model, $data) : $data;
    }

    public function withQueryBuilder(TableQueryBuilder $queryBuilder): ?TableQueryBuilder
    {
        return $this->configuration->withQueryBuilder instanceof Closure ? ($this->configuration->withQueryBuilder)($queryBuilder) : $queryBuilder;
    }

    public function exportContext(ExportRequest $request): array
    {
        return $this->configuration->exportContext instanceof Closure ? ($this->configuration->exportContext)($request) : [];
    }

    public function withExportContext(array $context, Closure $callback): mixed
    {
        return $this->configuration->withExportContext instanceof Closure ? ($this->configuration->withExportContext)($context, $callback) : $callback();
    }

    public function meta(): array
    {
        throw_if($this->reference() === null && ($this->exportsDefinition() !== [] || array_any($this->actionsDefinition(), static fn (Action $action): bool => $action->hasHandler())), LogicException::class, 'Register fluent tables with TableRegistry before using executable actions or exports.');

        $meta = parent::meta();
        if ($this->reference() === null) {
            unset($meta['table'], $meta['actionEndpoint'], $meta['exportEndpoint']);
        }

        return $meta;
    }
}
