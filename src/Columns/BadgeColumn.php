<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

use Wtroiano\TableBuilder\Enums\BadgeVariant;
use Wtroiano\TableBuilder\Enums\TableColor;

final class BadgeColumn extends Column
{
    /**
     * @param  array<string, string|TableColor>  $colors
     */
    public function colors(array $colors): self
    {
        return $this->meta([
            'colors' => array_map(
                static fn (string|TableColor $color): string => $color instanceof TableColor
                    ? $color->value
                    : $color,
                $colors,
            ),
        ]);
    }

    public function variant(string|BadgeVariant $variant): self
    {
        return $this->meta([
            'variant' => $variant instanceof BadgeVariant ? $variant->value : $variant,
        ]);
    }

    public function solid(): self
    {
        return $this->variant(BadgeVariant::Solid);
    }

    public function outline(): self
    {
        return $this->variant(BadgeVariant::Outline);
    }

    public function ghost(): self
    {
        return $this->variant(BadgeVariant::Ghost);
    }

    /**
     * @param  array<string, string>  $icons
     */
    public function icons(array $icons): self
    {
        return $this->meta([
            'icons' => $icons,
        ]);
    }

    public function icon(string $icon): self
    {
        return $this->meta([
            'icon' => $icon,
        ]);
    }
}
