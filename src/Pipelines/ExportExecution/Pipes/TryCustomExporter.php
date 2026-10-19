<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\ExportExecutionContext;

final class TryCustomExporter
{
    /** @param Closure(ExportExecutionContext): ExportExecutionContext $next */
    public function handle(ExportExecutionContext $context, Closure $next): ExportExecutionContext
    {
        if ($context->export->hasCustomExporter() && $context->query instanceof Builder) {
            $context->response = $context->export->executeUsing($context->table, $context->request, $context->query);

            if ($context->response !== null) {
                return $context;
            }
        }

        return $next($context);
    }
}
