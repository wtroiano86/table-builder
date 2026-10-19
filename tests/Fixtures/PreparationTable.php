<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Wtroiano\TableBuilder\Table;

final class PreparationTable extends Table
{
    public array $definitions = [];

    public array $events = [];

    public bool $failQuery = false;

    protected ?string $resource = PipelineRecord::class;

    protected ?string $rowSelectionKey = 'code';

    public function columns(): array
    {
        return [];
    }

    public function actions(): array
    {
        return $this->definitions;
    }

    public function query(): Builder
    {
        $this->events[] = 'query';

        throw_if($this->failQuery, RuntimeException::class, 'Query failed');

        return parent::query()->orderBy('id');
    }
}
