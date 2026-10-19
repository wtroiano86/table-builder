<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

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

    /**
     * @param  array<string, mixed>|callable|null  $exportStyle
     * @param  array<string, mixed>  $meta
     */
    public static function make(
        string $name = '_serial_number',
        string|callable|null $label = null,
        bool $sortable = false,
        bool $toggleable = false,
        bool $searchable = false,
        string|ColumnAlignment|null $alignment = null,
        ?callable $mapAs = null,
        bool $visible = true,
        ?callable $sortUsing = null,
        array $meta = [],
        bool $sticky = false,
        ?callable $searchUsing = null,
        ?string $key = null,
        ?string $labelClass = null,
        ?string $cellClass = null,
        false|callable|null $exportAs = null,
        string|callable|null $exportLabel = null,
        ?string $exportFormat = null,
        array|callable|null $exportStyle = null,
    ): static {
        $options = get_defined_vars();
        unset($options['name']);

        return self::fromOptions($name, $options);
    }
}
