<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\RowSerialization\Pipes;

use Closure;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\RowContext;

final class ResolveColumns
{
    /** @param Closure(RowContext): RowContext $next */
    public function handle(RowContext $context, Closure $next): RowContext
    {
        $columnUrls = [];
        $columnImages = [];

        foreach ($context->table->columnsDefinition() as $column) {
            if (in_array($column->resolvedKey(), ['_primary_key', '_selection_key'], true)) {
                continue;
            }

            $context->row[$column->resolvedKey()] = $column->resolveValue($context->record);
            $url = $column->resolveUrl($context->record);

            if ($url !== null && $url !== '') {
                $columnUrls[$column->resolvedKey()] = $url;
            }

            $image = $column->resolveImage($context->record);

            if ($image !== null) {
                $columnImages[$column->resolvedKey()] = $image;
            }
        }

        $context->row['_column_urls'] = $columnUrls;
        $context->row['_column_images'] = $columnImages;

        return $next($context);
    }
}
