<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportQuery\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\ExportQueryContext;
use Wtroiano\TableBuilder\Query\QuerySource;

final class EnsureDeterministicOrder
{
    /** @param Closure(ExportQueryContext): ExportQueryContext $next */
    public function handle(ExportQueryContext $context, Closure $next): ExportQueryContext
    {
        // Preserve scoped ordering before appending the primary-key tie-breaker.
        $context->query = $context->query->applyScopes()->withoutGlobalScopes();
        $model = $context->query->getModel();
        $keyName = $model->getKeyName();
        $qualifiedKeyName = QuerySource::qualify($context->query, $keyName);

        foreach (($context->query->getQuery()->orders ?? []) as $order) {
            $column = is_array($order) ? ($order['column'] ?? null) : null;

            if (is_string($column) && QuerySource::qualify($context->query, $column) === $qualifiedKeyName) {
                return $next($context);
            }
        }

        $context->query->orderBy($qualifiedKeyName);

        return $next($context);
    }
}
