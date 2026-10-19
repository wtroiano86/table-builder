<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\RowSerialization\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\RowContext;

final class TransformRow
{
    /** @param Closure(RowContext): RowContext $next */
    public function handle(RowContext $context, Closure $next): RowContext
    {
        if ($context->record instanceof Model) {
            $context->row = $context->table->transformModel($context->record, $context->row);
        }

        return $next($context);
    }
}
