<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Exports;

use Closure;

final readonly class ExportContextMiddleware
{
    public function __construct(private TableExcelExport $export) {}

    /** @param Closure(object): mixed $next */
    public function handle(object $job, Closure $next): mixed
    {
        return $this->export->withContext(static fn (): mixed => $next($job));
    }
}
