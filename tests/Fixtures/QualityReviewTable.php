<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Table;

final class QualityReviewTable extends Table
{
    public string $field = 'name';

    public int $columnCalls = 0;

    public bool $columnSearchable = true;

    public bool $columnToggleable = false;

    public bool $joined = false;

    public ?Action $action = null;

    protected ?string $resource = QualityParentRecord::class;

    public function columns(): array
    {
        $this->columnCalls++;

        return [TextColumn::make($this->field, sortable: true, toggleable: $this->columnToggleable, searchable: $this->columnSearchable)];
    }

    public function actions(): array
    {
        return $this->action instanceof Action ? [$this->action] : [];
    }

    public function query(): Builder
    {
        $query = parent::query();

        return $this->joined
            ? $query->select('quality_parents.*')->join('quality_children as child', 'quality_parents.id', '=', 'child.parent_id')->where('child.id', 1)
            : $query;
    }
}
