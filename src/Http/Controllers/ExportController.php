<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\ExportExecutionPipeline;

final readonly class ExportController
{
    public function __construct(private ExportExecutionPipeline $pipeline) {}

    public function __invoke(Request $request): mixed
    {
        return $this->pipeline->run(ExportRequest::fromRequest($request));
    }
}
