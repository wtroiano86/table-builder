<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\ExportExecutionContext;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\ExportQueryBuilder;

final readonly class PrepareQuery
{
    public function __construct(private ExportQueryBuilder $queryBuilder) {}

    /** @param Closure(ExportExecutionContext): ExportExecutionContext $next */
    public function handle(ExportExecutionContext $context, Closure $next): ExportExecutionContext
    {
        if ($context->export->hasCustomExporter() || ! $context->export->isQueued()) {
            $context->query = $this->queryBuilder->apply(
                $context->table,
                $context->export,
                $context->request->tableRequest($context->table),
                $context->request->selectedKeys(),
            );
        }

        return $next($context);
    }
}
