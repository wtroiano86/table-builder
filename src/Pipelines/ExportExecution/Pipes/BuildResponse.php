<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\ExportExecution\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\ExportExecution\ExportExecutionContext;

final class BuildResponse
{
    /** @param Closure(ExportExecutionContext): ExportExecutionContext $next */
    public function handle(ExportExecutionContext $context, Closure $next): ExportExecutionContext
    {
        if ($context->storageFailed) {
            $payload = [
                'ok' => false,
                'status' => 'error',
                'export' => $context->export->keyName(),
                'message' => 'Export could not be stored.',
            ];
            $context->response = $context->request->inertia()
                ? back()->withErrors(['table_export' => $payload['message']])
                : response()->json($payload, 500);

            return $next($context);
        }

        if ($context->response === null) {
            $payload = [
                'ok' => true,
                'status' => 'processing',
                'export' => $context->export->keyName(),
                'message' => 'Export is being processed.',
            ];
            $context->response = $context->request->inertia()
                ? $context->export->redirectResponse()->with('table_export', $payload)
                : response()->json($payload);
        }

        return $next($context);
    }
}
