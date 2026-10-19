<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ActionPreparation\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ActionPreparation\ActionRequestContext;

final class ValidateBulkSelection
{
    /** @param Closure(ActionRequestContext): ActionRequestContext $next */
    public function handle(ActionRequestContext $context, Closure $next): ActionRequestContext
    {
        $context->request->validate([
            'selectionMode' => ['sometimes', 'string', 'in:page,all'],
            'keys' => ['sometimes', 'array'],
            'keys.*' => [static function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && ! is_int($value) && ! is_string($value)) {
                    $fail('Selection keys must be integers or strings.');
                }
            }],
        ]);
        $context->isAllSelection = $context->request->input('selectionMode') === 'all';
        $context->requestedKeys = (array) $context->request->input('keys', []);
        abort_unless(
            $context->action->isBulk() || (! $context->isAllSelection && count($context->requestedKeys) <= 1),
            422,
            'Action is not bulk executable.'
        );

        return $next($context);
    }
}
