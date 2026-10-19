<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Contracts\TableState;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Exceptions\TableBuilderException;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Support\Url;
use Wtroiano\TableBuilder\Tables\ConfiguredTable;
use Wtroiano\TableBuilder\Tables\TableConfiguration;
use Wtroiano\TableBuilder\Tables\TableEmptyState;

final class TableBuilder
{
    private TableConfiguration $configuration;

    private function __construct(string $name)
    {
        $this->configuration = new TableConfiguration();
        $this->configuration->name = mb_trim($name);
        if ($this->configuration->name === '') {
            throw TableBuilderException::emptyTableName();
        }
    }

    public function __clone(): void
    {
        $this->configuration = clone $this->configuration;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /** @param class-string<Model>|Builder<Model>|callable(): (Builder<Model>|class-string<Model>) $resource */
    public function resource(string|Builder|callable $resource): self
    {
        if ($resource instanceof Builder || (is_string($resource) && is_a($resource, Model::class, true))) {
            $this->configuration->resource = $resource;
        } elseif (is_callable($resource)) {
            $this->configuration->resource = $resource(...);
        } else {
            throw TableBuilderException::invalidTableResource(self::class, $resource);
        }

        return $this;
    }

    /** @param list<Column>|callable(Table): list<Column> $columns */
    public function columns(array|callable $columns): self
    {
        $this->configuration->columns = is_callable($columns) ? Closure::fromCallable($columns) : $columns;

        return $this;
    }

    /** @param list<Action>|callable(Table): list<Action> $actions */
    public function actions(array|callable $actions): self
    {
        $this->configuration->actions = is_callable($actions) ? Closure::fromCallable($actions) : $actions;

        return $this;
    }

    /** @param list<Export>|callable(Table): list<Export> $exports */
    public function exports(array|callable $exports): self
    {
        $this->configuration->exports = is_callable($exports) ? Closure::fromCallable($exports) : $exports;

        return $this;
    }

    /** @param bool|callable(Request): bool $authorize */
    public function authorize(bool|callable $authorize): self
    {
        $this->configuration->authorize = is_bool($authorize) ? $authorize : Closure::fromCallable($authorize);

        return $this;
    }

    public function defaultSort(?string $sort): self
    {
        $this->configuration->defaultSort = $sort;

        return $this;
    }

    public function perPage(int $perPage): self
    {
        if ($perPage < 1) {
            throw TableBuilderException::invalidPerPage();
        }

        $this->configuration->perPage = $perPage;

        return $this;
    }

    /** @param list<int> $options */
    public function perPageOptions(array $options): self
    {
        $this->configuration->perPageOptions = $options;

        return $this;
    }

    public function pagination(bool $enabled = true, PaginationType $type = PaginationType::Standard): self
    {
        $this->configuration->pagination = $enabled;
        $this->configuration->paginationType = $type;

        return $this;
    }

    public function scrollToTop(bool $enabled = true): self
    {
        $this->configuration->scrollToTop = $enabled;

        return $this;
    }

    public function stickyHeader(bool $enabled = true): self
    {
        $this->configuration->stickyHeader = $enabled;

        return $this;
    }

    public function rowSelectionKey(?string $key): self
    {
        $this->configuration->rowSelectionKey = $key;

        return $this;
    }

    public function selectable(bool $enabled = true): self
    {
        $this->configuration->selectable = $enabled;

        return $this;
    }

    public function persistRowSelectionAcrossPages(bool $enabled = true): self
    {
        $this->configuration->persistRowSelectionAcrossPages = $enabled;

        return $this;
    }

    /** @param TableEmptyState|array<string, mixed>|false|null $state */
    public function emptyState(TableEmptyState|array|false|null $state): self
    {
        $this->configuration->emptyState = $state;

        return $this;
    }

    /** @param list<string> $fields */
    public function searchable(array $fields): self
    {
        $this->configuration->searchable = $fields;

        return $this;
    }

    /** @param array<string, mixed>|TableState $state */
    public function defaultState(array|TableState $state): self
    {
        $this->configuration->defaultState = $state;

        return $this;
    }

    /** @param callable(Model): bool $callback */
    public function selectableUsing(callable $callback): self
    {
        $this->configuration->selectableUsing = Closure::fromCallable($callback);

        return $this;
    }

    /** @param callable(Model, Url): (string|Url|null) $callback */
    public function rowUrl(callable $callback): self
    {
        $this->configuration->rowUrl = Closure::fromCallable($callback);

        return $this;
    }

    /** @param callable(Model, array<string, mixed>): array<string, mixed> $callback */
    public function transformModel(callable $callback): self
    {
        $this->configuration->transformModel = Closure::fromCallable($callback);

        return $this;
    }

    /** @param callable(TableQueryBuilder): ?TableQueryBuilder $callback */
    public function withQueryBuilder(callable $callback): self
    {
        $this->configuration->withQueryBuilder = Closure::fromCallable($callback);

        return $this;
    }

    /**
     * @param  callable(ExportRequest): array<string, mixed>  $capture
     * @param  callable(array<string, mixed>, Closure): mixed  $restore
     */
    public function exportContext(callable $capture, callable $restore): self
    {
        $this->configuration->exportContext = Closure::fromCallable($capture);
        $this->configuration->withExportContext = Closure::fromCallable($restore);

        return $this;
    }

    public function build(): Table
    {
        return ConfiguredTable::fromConfiguration($this->configuration);
    }
}
