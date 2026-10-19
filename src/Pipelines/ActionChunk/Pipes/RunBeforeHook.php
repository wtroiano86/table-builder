<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionChunk\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ActionChunk\ActionChunkContext;

final class RunBeforeHook
{
    /** @param Closure(ActionChunkContext): ActionChunkContext $next */
    public function handle(ActionChunkContext $context, Closure $next): ActionChunkContext
    {
        $context->action->runBefore($context->models);

        return $next($context);
    }
}
