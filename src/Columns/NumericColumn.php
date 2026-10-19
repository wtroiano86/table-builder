<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

use Wtroiano\TableBuilder\Enums\ColumnAlignment;

final class NumericColumn extends Column
{
    protected function __construct(string $name)
    {
        parent::__construct($name);

        $this->alignment = ColumnAlignment::Right->value;
    }

    public function precision(int $precision): self
    {
        return $this->meta([
            'precision' => $precision,
        ]);
    }

    public function thousandsSeparator(string $thousandsSeparator): self
    {
        return $this->meta([
            'thousandsSeparator' => $thousandsSeparator,
        ]);
    }

    public function decimalSeparator(string $decimalSeparator): self
    {
        return $this->meta([
            'decimalSeparator' => $decimalSeparator,
        ]);
    }

    public function prefix(string $prefix): self
    {
        return $this->meta([
            'prefix' => $prefix,
        ]);
    }

    public function suffix(string $suffix): self
    {
        return $this->meta([
            'suffix' => $suffix,
        ]);
    }

    public function placeholder(string $placeholder): self
    {
        return $this->meta([
            'placeholder' => $placeholder,
        ]);
    }

    public function presentation(string $presentation): self
    {
        return $this->meta([
            'presentation' => $presentation,
        ]);
    }
}
