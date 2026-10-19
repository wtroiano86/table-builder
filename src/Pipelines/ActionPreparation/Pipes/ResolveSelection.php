<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionPreparation\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\ActionRequestContext;
use Wtroiano\TableBuilder\Query\QuerySource;
use Wtroiano\TableBuilder\Query\SelectionKeyResolver;

final class ResolveSelection
{
    /** @param Closure(ActionRequestContext): ActionRequestContext $next */
    public function handle(ActionRequestContext $context, Closure $next): ActionRequestContext
    {
        if (! $context->isAllSelection) {
            $query = $context->table->query();
            QuerySource::ensureIdentitySelected($query, $context->table);
            $keyName = SelectionKeyResolver::resolve($context->table, $query->getModel());
            $keys = array_values(array_filter($context->requestedKeys, static fn (mixed $key): bool => $key !== null && $key !== ''));
            $context->models = array_values($query->whereIn(QuerySource::qualify($query, $keyName), $keys)->get()->all());
            abort_if($context->models === [], 404, 'No matching records found.');
        }

        return $next($context);
    }
}
