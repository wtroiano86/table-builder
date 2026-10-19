<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\RowSerialization\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\RowContext;
use Wtroiano\TableBuilder\Query\SelectionKeyResolver;
use Wtroiano\TableBuilder\Support\Url;

final class ResolveIdentity
{
    /** @param Closure(RowContext): RowContext $next */
    public function handle(RowContext $context, Closure $next): RowContext
    {
        $record = $context->record;
        $primaryKey = is_object($record) && method_exists($record, 'getKey')
            ? $record->getKey()
            : data_get($record, 'id');
        $context->row = [
            '_column_urls' => [],
            '_column_images' => [],
            '_primary_key' => $primaryKey,
        ];
        $selectionKey = $context->table->rowSelectionKey();

        if ($selectionKey !== null && ! array_key_exists($selectionKey, $context->row)) {
            $context->row[$selectionKey] = $record instanceof Model
                ? data_get($record, SelectionKeyResolver::resolve($context->table, $record))
                : ($selectionKey === 'id' ? $primaryKey : data_get($record, $selectionKey));
        }

        if ($selectionKey !== null) {
            $context->row['_selection_key'] = $context->row[$selectionKey];
        }

        if ($record instanceof Model) {
            $url = $context->table->rowUrl($record, new Url());
            $url = $url instanceof Url ? $url->toArray() : $url;

            if ($url !== null && $url !== '') {
                $context->row['_url'] = $url;
            }
        }

        return $next($context);
    }
}
