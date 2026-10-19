<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\ExportExecutionContext;

final class AuthorizeExport
{
    /** @param Closure(ExportExecutionContext): ExportExecutionContext $next */
    public function handle(ExportExecutionContext $context, Closure $next): ExportExecutionContext
    {
        abort_unless($context->export->isAuthorized($context->table), 403);

        return $next($context);
    }
}
