<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionPreparation\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\ActionRequestContext;
use Wtroiano\TableBuilder\Tables\TableResolver;

final class ResolveTable
{
    /** @param Closure(ActionRequestContext): ActionRequestContext $next */
    public function handle(ActionRequestContext $context, Closure $next): ActionRequestContext
    {
        $table = resolve(TableResolver::class)->resolve($context->input->table);
        abort_unless($table->authorize($context->request), 403);
        $context->table = $table;

        return $next($context);
    }
}
