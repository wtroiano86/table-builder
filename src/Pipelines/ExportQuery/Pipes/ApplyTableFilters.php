<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportQuery\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\ExportQueryContext;

final class ApplyTableFilters
{
    /** @param Closure(ExportQueryContext): ExportQueryContext $next */
    public function handle(ExportQueryContext $context, Closure $next): ExportQueryContext
    {
        if ($context->export->isLimitedToFilteredRows()) {
            $context->query = $context->table->apply($context->query, $context->request);
        }

        return $next($context);
    }
}
