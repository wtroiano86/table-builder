<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionChunk;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Pipelines\ActionChunk\Pipes\ExecuteEligibleModels;
use Wtroiano\TableBuilder\Pipelines\ActionChunk\Pipes\RunAfterHook;
use Wtroiano\TableBuilder\Pipelines\ActionChunk\Pipes\RunBeforeHook;
use Wtroiano\TableBuilder\Table;

final class ActionChunkPipeline
{
    /** @param list<Model> $models */
    public function run(Table $table, Action $action, array $models, ActionExecutionResult $execution): void
    {
        abort_unless($action->isAuthorized(), 403);

        new Pipeline()
            ->send(new ActionChunkContext($table, $action, $models, $execution))
            ->through([
                new RunBeforeHook(),
                new ExecuteEligibleModels(),
                new RunAfterHook(),
            ])
            ->thenReturn();
    }
}
