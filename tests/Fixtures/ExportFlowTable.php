<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Table;

final class ExportFlowTable extends Table
{
    public ?Export $definition = null;

    public array $events = [];

    protected ?string $resource = PipelineRecord::class;

    public function columns(): array
    {
        return [];
    }

    public function exports(): array
    {
        return $this->definition instanceof Export ? [$this->definition] : [];
    }

    public function query(): Builder
    {
        $this->events[] = 'query';

        return parent::query();
    }
}
