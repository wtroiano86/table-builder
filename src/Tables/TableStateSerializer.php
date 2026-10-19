<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables;

use Wtroiano\TableBuilder\Contracts\TableState;

final class TableStateSerializer
{
    /** @return array<string, mixed> */
    public static function serialize(TableState $state): array
    {
        $values = array_replace($state->toArray(), ['columns' => $state->columns()]);

        if ($state->hasStickyOverride()) {
            $values['sticky'] = $state->sticky();
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function queryString(array $state, int $defaultPerPage, ?string $name = null, bool $includePage = false, bool $includeCursor = false, ?TableState $defaults = null): array
    {
        if (! $includePage) {
            unset($state['page']);
        }

        $baseline = $defaults instanceof TableState ? self::serialize($defaults) : [
            'perPage' => $defaultPerPage,
            'search' => null,
            'sort' => null,
            'direction' => 'asc',
            'columns' => [],
            'cursor' => null,
        ];

        $resetsDefaultCursor = ($baseline['cursor'] ?? null) !== null && array_key_exists('cursor', $state) && $state['cursor'] === null;
        if (! $includeCursor && ! $resetsDefaultCursor) {
            unset($state['cursor']);
        }

        foreach ($state as $key => $value) {
            if ($key === 'page') {
                continue;
            }

            if (array_key_exists($key, $baseline) && $value === $baseline[$key]) {
                unset($state[$key]);
            } elseif ($value === null || $value === []) {
                // Empty arrays vanish in http_build_query; an empty scalar resets defaults.
                $state[$key] = '';
            }
        }

        return $name === null ? $state : [$name => $state];
    }
}
