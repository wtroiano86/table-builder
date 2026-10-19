<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

final class BooleanColumn extends Column
{
    public function trueLabel(string $label): self
    {
        return $this->meta([
            'trueLabel' => $label,
        ]);
    }

    public function falseLabel(string $label): self
    {
        return $this->meta([
            'falseLabel' => $label,
        ]);
    }

    public function trueIcon(string $icon): self
    {
        return $this->meta([
            'trueIcon' => $icon,
        ]);
    }

    public function falseIcon(string $icon): self
    {
        return $this->meta([
            'falseIcon' => $icon,
        ]);
    }

    public function nullLabel(string $label): self
    {
        return $this->meta([
            'nullLabel' => $label,
        ]);
    }

    public function displayAs(string $displayAs): self
    {
        return $this->meta([
            'displayAs' => $displayAs,
        ]);
    }
}
