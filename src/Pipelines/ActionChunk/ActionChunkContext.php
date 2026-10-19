<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionChunk;

use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Table;

final class ActionChunkContext
{
    /** @var list<mixed> */
    public array $results = [];

    /** @param list<Model> $models */
    public function __construct(
        public readonly Table $table,
        public readonly Action $action,
        public readonly array $models,
        public readonly ActionExecutionResult $execution,
    ) {}
}
