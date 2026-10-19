<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

final class DateTimeColumn extends Column
{
    public function format(string $format): self
    {
        return $this->meta([
            'format' => $format,
        ]);
    }

    public function placeholder(string $placeholder): self
    {
        return $this->meta([
            'placeholder' => $placeholder,
        ]);
    }

    public function timezone(string $timezone): self
    {
        return $this->meta([
            'timezone' => $timezone,
        ]);
    }
}
