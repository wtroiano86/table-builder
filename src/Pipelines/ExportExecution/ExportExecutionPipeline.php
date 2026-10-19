<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportExecution;

use Illuminate\Pipeline\Pipeline;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes\AuthorizeExport;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes\BuildResponse;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes\DispatchExport;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes\PrepareQuery;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes\ResolveExport;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes\TryCustomExporter;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\ExportQueryBuilder;

final readonly class ExportExecutionPipeline
{
    public function __construct(private ExportQueryBuilder $queryBuilder) {}

    public function run(ExportRequest $request): mixed
    {
        /** @var ExportExecutionContext $context */
        $context = new Pipeline()
            ->send(new ExportExecutionContext($request))
            ->through([
                new ResolveExport(),
                new AuthorizeExport(),
                new PrepareQuery($this->queryBuilder),
                new TryCustomExporter(),
                new DispatchExport(),
                new BuildResponse(),
            ])
            ->thenReturn();

        return $context->response;
    }
}
