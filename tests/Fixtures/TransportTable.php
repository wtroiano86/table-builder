<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Table;

final class TransportTable extends Table
{
    public string $field = 'name';

    public array $defaults = [];

    protected ?string $resource = QualityParentRecord::class;

    protected PaginationType $paginationType = PaginationType::Cursor;

    protected ?int $defaultPerPage = 1;

    protected ?array $perPageOptions = [1, 2, 15, 30];

    public function columns(): array
    {
        return [TextColumn::make($this->field, sortable: true, searchable: true)->exportStyle(TransportStyle::CALLBACK)];
    }

    public function exports(): array
    {
        return [Export::make('Records')->key('records')->limitToFilteredRows()];
    }

    public function defaultState(): array
    {
        return $this->defaults;
    }

    public function standardPagination(): self
    {
        $this->paginationType = PaginationType::Standard;

        return $this;
    }
}
