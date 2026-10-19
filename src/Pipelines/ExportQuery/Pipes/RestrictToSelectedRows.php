<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportQuery\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\ExportQueryContext;
use Wtroiano\TableBuilder\Query\QuerySource;
use Wtroiano\TableBuilder\Query\SelectionKeyResolver;

final class RestrictToSelectedRows
{
    /** @param Closure(ExportQueryContext): ExportQueryContext $next */
    public function handle(ExportQueryContext $context, Closure $next): ExportQueryContext
    {
        if ($context->export->isLimitedToSelectedRows()) {
            $keyName = SelectionKeyResolver::resolve($context->table, $context->query->getModel());

            if ($context->selectedKeys === []) {
                $context->query->whereRaw('1 = 0');
            } else {
                $context->query->whereIn(QuerySource::qualify($context->query, $keyName), $context->selectedKeys);
            }
        }

        return $next($context);
    }
}
