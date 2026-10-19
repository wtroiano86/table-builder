<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Table;

final class QuerySource
{
    /** @param Builder<Model> $query */
    public static function table(Builder $query): string
    {
        $from = $query->getQuery()->from;
        $from = $from instanceof Expression ? $from->getValue($query->getQuery()->getGrammar()) : $from;
        if (! is_string($from)) {
            return $query->getModel()->getTable();
        }

        $parts = preg_split('/\s+as\s+/i', mb_trim($from));

        return $parts === false ? $query->getModel()->getTable() : mb_trim((string) array_last($parts), " \t\n\r\0\x0B`\"[]");
    }

    /** @param Builder<Model> $query */
    public static function qualify(Builder $query, string $column): string
    {
        $physical = $query->getModel()->getTable().'.';
        if (str_starts_with($column, $physical)) {
            return self::table($query).'.'.mb_substr($column, mb_strlen($physical));
        }

        return str_contains($column, '.') ? $column : self::table($query).'.'.$column;
    }

    /**
     * @param  Builder<Model>  $query
     *
     * @internal Only use this clone when building correlated relation queries.
     */
    public static function relationModel(Builder $query): Model
    {
        return (clone $query->getModel())->setTable(self::table($query));
    }

    /** @param Builder<Model> $query */
    public static function ensureKeySelected(Builder $query): void
    {
        self::ensureColumnSelected($query, $query->getModel()->getKeyName());
    }

    /** @param Builder<Model> $query */
    public static function ensureIdentitySelected(Builder $query, Table $table): void
    {
        self::ensureKeySelected($query);
        self::ensureColumnSelected($query, SelectionKeyResolver::resolve($table, $query->getModel()));
    }

    /** @param Builder<Model> $query */
    private static function ensureColumnSelected(Builder $query, string $keyName): void
    {
        $columns = $query->getQuery()->columns;
        $key = self::qualify($query, $keyName);
        if ($columns === null) {
            return;
        }

        $grammar = $query->getQuery()->getGrammar();
        $selected = ['*', self::table($query).'.*', $keyName, $key, $key.' as '.$keyName];
        $selected = [...$selected, ...array_map($grammar->wrap(...), $selected)];
        foreach ($columns as $column) {
            $value = $column instanceof Expression ? $column->getValue($grammar) : $column;
            if (is_string($value) && array_intersect(self::splitSelectList($value), $selected) !== []) {
                return;
            }
        }

        $query->addSelect($key.' as '.$keyName);
    }

    /** @return list<string> */
    private static function splitSelectList(string $sql): array
    {
        $columns = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = mb_strlen($sql, '8bit');

        // Only top-level commas separate columns; expressions and quoted values may contain commas.
        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            if ($quote !== null) {
                if ($character === '\\' || ($character === $quote && ($sql[$index + 1] ?? null) === $quote)) {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }
            } elseif (in_array($character, ["'", '"', '`', '['], true)) {
                $quote = $character === '[' ? ']' : $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $columns[] = mb_trim(mb_substr($sql, $start, $index - $start, '8bit'));
                $start = $index + 1;
            }
        }

        $columns[] = mb_trim(mb_substr($sql, $start, null, '8bit'));

        return $columns;
    }
}
