<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionChunk\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ActionChunk\ActionChunkContext;

final class ExecuteEligibleModels
{
    /** @param Closure(ActionChunkContext): ActionChunkContext $next */
    public function handle(ActionChunkContext $context, Closure $next): ActionChunkContext
    {
        foreach ($context->models as $model) {
            if (
                ! $context->table->isSelectable($model) ||
                ! $context->action->isAuthorized($model) ||
                $context->action->isDisabled($model) ||
                $context->action->isHidden($model)
            ) {
                $context->execution->skipped++;

                continue;
            }

            $result = $context->action->execute($model);
            if ($context->action->shouldCollectResults()) {
                $context->execution->results[] = $result;
            }

            $context->results[] = $result;
            $context->execution->processed++;
        }

        return $next($context);
    }
}
