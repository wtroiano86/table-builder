<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Table;

final class PayloadTable extends Table
{
    public string $recordClass = PipelineRecord::class;

    public int $labelCalls = 0;

    public int $rowCalls = 0;

    protected ?int $defaultPerPage = 1;

    protected ?array $perPageOptions = [1, 2];

    public function resource(): string
    {
        return $this->recordClass;
    }

    public function columns(): array
    {
        return [TextColumn::make('name', label: fn (): string => 'Label '.++$this->labelCalls, sortable: true)];
    }

    public function transformModel(Model $model, array $data): array
    {
        $this->rowCalls++;

        return $data;
    }

    public function paginationMode(PaginationType $type): self
    {
        $this->paginationType = $type;

        return $this;
    }
}
