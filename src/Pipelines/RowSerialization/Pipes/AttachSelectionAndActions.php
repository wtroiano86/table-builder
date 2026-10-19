<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\RowSerialization\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\RowContext;

final class AttachSelectionAndActions
{
    /** @param Closure(RowContext): RowContext $next */
    public function handle(RowContext $context, Closure $next): RowContext
    {
        $record = $context->record;
        $context->row['_selectable'] = ! $record instanceof Model || $context->table->isSelectable($record);
        $context->row['_actions'] = $record instanceof Model
            ? array_values(array_filter(array_map(
                static fn (Action $action): ?array => $action->forRow($record),
                $context->table->actionsDefinition(),
            )))
            : [];

        return $next($context);
    }
}
