<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;

final class RelationFieldResolver
{
    /**
     * @param  Builder<Model>|null  $query
     * @return array{relation: string, column: string}|null
     */
    public function resolve(string $field, ?Builder $query = null): ?array
    {
        if (! str_contains($field, '.')) {
            return null;
        }

        if ($query instanceof Builder && $this->isQualifiedColumn($field, $query)) {
            return null;
        }

        $segments = array_values(array_filter(explode('.', $field), static fn (string $segment): bool => $segment !== ''));

        if (count($segments) < 2) {
            return null;
        }

        $column = array_pop($segments);
        $relation = implode('.', $segments);

        return [
            'relation' => $relation,
            'column' => $column,
        ];
    }

    /** @param Builder<Model> $query */
    private function isQualifiedColumn(string $field, Builder $query): bool
    {
        $prefix = mb_substr($field, 0, (int) mb_strrpos($field, '.'));
        $tables = [$query->getModel()->getTable(), $query->getQuery()->from];

        foreach ($query->getQuery()->joins ?? [] as $join) {
            if ($join instanceof JoinClause) {
                $tables[] = $join->table;
            }
        }

        foreach ($tables as $table) {
            if (! is_string($table)) {
                continue;
            }

            $parts = preg_split('/\s+(?:as\s+)?/i', mb_trim($table));

            if ($parts !== false && in_array($prefix, $parts, true)) {
                return true;
            }
        }

        return false;
    }
}
