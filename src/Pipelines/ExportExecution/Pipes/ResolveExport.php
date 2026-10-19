<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\ExportExecutionContext;
use Wtroiano\TableBuilder\Tables\TableResolver;

final class ResolveExport
{
    /** @param Closure(ExportExecutionContext): ExportExecutionContext $next */
    public function handle(ExportExecutionContext $context, Closure $next): ExportExecutionContext
    {
        $table = resolve(TableResolver::class)->resolve($context->request->table());
        abort_unless($table->authorize($context->request->baseRequest()), 403);
        $context->table = $table;

        foreach ($table->exportsDefinition() as $export) {
            if ($export->keyName() === $context->request->export()) {
                $context->export = $export;

                return $next($context);
            }
        }

        abort(404, 'Export not found.');
    }
}
