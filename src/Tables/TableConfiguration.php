<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Contracts\TableState;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Support\Url;
use Wtroiano\TableBuilder\Table;

/** @internal Configuration snapshots used by TableBuilder. */
final class TableConfiguration
{
    public string $name = '';

    /** @var class-string<Model>|Builder<Model>|Closure(): (Builder<Model>|class-string<Model>)|null */
    public string|Builder|Closure|null $resource = null;

    /** @var list<Column>|Closure(Table): list<Column> */
    public array|Closure $columns = [];

    /** @var list<Action>|Closure(Table): list<Action> */
    public array|Closure $actions = [];

    /** @var list<Export>|Closure(Table): list<Export> */
    public array|Closure $exports = [];

    /** @var bool|Closure(Request): bool|null */
    public bool|Closure|null $authorize = null;

    public ?string $defaultSort = null;

    public ?int $perPage = null;

    /** @var list<int>|null */
    public ?array $perPageOptions = null;

    public bool $pagination = true;

    public PaginationType $paginationType = PaginationType::Standard;

    public bool $scrollToTop = true;

    public bool $stickyHeader = false;

    public ?string $rowSelectionKey = 'id';

    public bool $selectable = true;

    public bool $persistRowSelectionAcrossPages = false;

    /** @var TableEmptyState|array<string, mixed>|false|null */
    public TableEmptyState|array|false|null $emptyState = null;

    /** @var list<string> */
    public array $searchable = [];

    /** @var array<string, mixed>|TableState|null */
    public array|TableState|null $defaultState = null;

    /** @var Closure(Model): bool|null */
    public ?Closure $selectableUsing = null;

    /** @var Closure(Model, Url): (string|Url|null)|null */
    public ?Closure $rowUrl = null;

    /** @var Closure(Model, array<string, mixed>): array<string, mixed>|null */
    public ?Closure $transformModel = null;

    /** @var Closure(TableQueryBuilder): ?TableQueryBuilder|null */
    public ?Closure $withQueryBuilder = null;

    /** @var Closure(ExportRequest): array<string, mixed>|null */
    public ?Closure $exportContext = null;

    /** @var Closure(array<string, mixed>, Closure): mixed|null */
    public ?Closure $withExportContext = null;

    public function __clone(): void
    {
        if ($this->resource instanceof Builder) {
            $this->resource = clone $this->resource;
        }

        if ($this->emptyState instanceof TableEmptyState) {
            $this->emptyState = clone $this->emptyState;
        }

        $this->columns = $this->cloneDefinitions($this->columns);
        $this->actions = $this->cloneDefinitions($this->actions);
        $this->exports = $this->cloneDefinitions($this->exports);
    }

    /**
     * @template TDefinition of object
     *
     * @param  list<TDefinition>|Closure(Table): list<TDefinition>  $definitions
     * @return list<TDefinition>|Closure(Table): list<TDefinition>
     */
    private function cloneDefinitions(array|Closure $definitions): array|Closure
    {
        return $definitions instanceof Closure ? $definitions : array_map(
            static fn (object $definition): object => clone $definition,
            $definitions,
        );
    }
}
