<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionPreparation\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\ActionRequestContext;

final class ResolveAction
{
    /** @param Closure(ActionRequestContext): ActionRequestContext $next */
    public function handle(ActionRequestContext $context, Closure $next): ActionRequestContext
    {
        $key = $context->input->action;

        foreach ($context->table->actionsDefinition() as $action) {
            if ($action->keyName() === $key) {
                abort_unless($action->hasHandler(), 404, 'Action not found.');
                abort_unless($action->isAuthorized(), 403);
                $context->action = $action;

                return $next($context);
            }
        }

        abort(404, 'Action not found.');
    }
}
