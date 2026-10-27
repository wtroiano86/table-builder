<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

use ArgumentCountError;
use Wtroiano\TableBuilder\Enums\ColumnAlignment;

final class SerialNumberColumn extends Column
{
    protected function __construct(string $name = '_serial_number')
    {
        parent::__construct($name);

        $this->label = 'S.no';
        $this->toggleable = false;
        $this->alignment = ColumnAlignment::Center->value;
        $this->width = '4rem';
        $this->meta = [
            'serialNumber' => true,
        ];
    }

    public static function make(string $name = '_serial_number'): static
    {
        throw_if(func_num_args() > 1, ArgumentCountError::class, 'Use fluent methods for additional factory configuration.');

        return new self($name);
    }
}
