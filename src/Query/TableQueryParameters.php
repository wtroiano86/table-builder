<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Query;

use Wtroiano\TableBuilder\Contracts\TableState;
use Wtroiano\TableBuilder\Enums\Direction;

final readonly class TableQueryParameters
{
    public function __construct(
        private ?string $search = null,
        private ?string $sort = null,
        private string $direction = Direction::ASCENDING->value,
    ) {}

    /**
     * @param  array<array-key, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $sort = $input['sort'] ?? null;
        $direction = $input['direction'] ?? Direction::ASCENDING;

        if (is_array($sort)) {
            $direction = $sort['direction'] ?? $direction;
            $sort = $sort['column'] ?? null;
        }

        return new self(
            search: self::normalizeNullableString($input['search'] ?? null),
            sort: self::normalizeNullableString($sort),
            direction: self::normalizeDirection($direction),
        );
    }

    public static function fromState(TableState $state): self
    {
        return new self(
            search: $state->search(),
            sort: $state->sort(),
            direction: $state->direction(),
        );
    }

    public function search(): ?string
    {
        return $this->search;
    }

    public function sort(): ?string
    {
        return $this->sort;
    }

    public function direction(): string
    {
        return $this->direction;
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    private static function normalizeDirection(mixed $value): string
    {
        if ($value instanceof Direction) {
            return $value->value;
        }

        if (! is_string($value)) {
            return Direction::ASCENDING->value;
        }

        return (Direction::tryFrom(mb_strtolower(mb_trim($value))) ?? Direction::ASCENDING)->value;
    }
}
