<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns\Concerns;

use Wtroiano\TableBuilder\Enums\ColumnAlignment;

trait ConfiguresColumnPresentation
{
    protected bool $toggleable = true;

    protected bool $sticky = false;

    protected string|int|null $width = null;

    protected string|int|null $minWidth = null;

    protected string|int|null $maxWidth = null;

    protected string|ColumnAlignment|null $alignment = null;

    protected ?string $labelClass = null;

    protected ?string $cellClass = null;

    protected ?string $tooltip = null;

    protected mixed $defaultValue = null;

    public function toggleable(bool $toggleable = true): static
    {
        $this->toggleable = $toggleable;

        return $this;
    }

    public function isToggleable(): bool
    {
        return $this->toggleable;
    }

    public function sticky(bool $sticky = true): static
    {
        $this->sticky = $sticky;

        return $this;
    }

    public function alignment(string|ColumnAlignment|null $alignment): static
    {
        if ($alignment === null) {
            return $this;
        }

        $this->alignment = is_string($alignment)
            ? ColumnAlignment::tryFrom($alignment) ?? $alignment
            : $alignment;

        return $this;
    }

    public function width(string|int|null $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function minWidth(string|int|null $minWidth): static
    {
        $this->minWidth = $minWidth;

        return $this;
    }

    public function maxWidth(string|int|null $maxWidth): static
    {
        $this->maxWidth = $maxWidth;

        return $this;
    }

    public function labelClass(?string $labelClass): static
    {
        $this->labelClass = $labelClass;

        return $this;
    }

    public function cellClass(?string $cellClass): static
    {
        $this->cellClass = $cellClass;

        return $this;
    }

    public function tooltip(?string $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function defaultValue(mixed $defaultValue): static
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }
}
