<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes;

use Closure;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Wtroiano\TableBuilder\Exports\TableExcelExport;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\ExportExecutionContext;

final class DispatchExport
{
    /** @param Closure(ExportExecutionContext): ExportExecutionContext $next */
    public function handle(ExportExecutionContext $context, Closure $next): ExportExecutionContext
    {
        $export = $context->export;
        $excelExport = TableExcelExport::fromRequest($context->table, $export, $context->request, $export->isQueued() ? null : $context->query);

        if ($export->isQueued()) {
            $job = Excel::queue($excelExport, $export->queueFilename(), $export->queueDisk(), $export->writerType());
            $export->queuedJob($job);
        } elseif ($export->shouldDownload()) {
            $context->response = Excel::download($excelExport, $export->resolvedFilename(), $export->writerType());
        } else {
            $context->storageFailed = Excel::store($excelExport, $export->resolvedFilename(), $export->queueDisk(), $export->writerType()) === false;
            report_if($context->storageFailed, new RuntimeException('Export could not be stored.'));
        }

        return $next($context);
    }
}
