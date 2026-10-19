<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

use Wtroiano\TableBuilder\Enums\ColumnAlignment;

final class ActionColumn extends Column
{
    protected function __construct(string $name = 'actions')
    {
        parent::__construct($name);

        $this->alignment = ColumnAlignment::Center;
    }

    /**
     * @param  array<array-key, mixed>  $meta
     * @param  array<array-key, mixed>|callable|null  $exportStyle
     */
    public static function make(
        string $name = 'actions',
        string|callable|null $label = null,
        bool $sortable = false,
        bool $toggleable = true,
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
        bool $asDropdown = false,
    ): static {
        $options = get_defined_vars();
        unset($options['name'], $options['asDropdown']);

        $column = self::fromOptions($name, $options);

        return $asDropdown ? $column->asDropdown() : $column;
    }

    public function asDropdown(bool $asDropdown = true): static
    {
        return $this->meta([
            'asDropdown' => $asDropdown,
            'dropdown' => $asDropdown,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     */
    public function actions(array $actions): self
    {
        return $this->meta([
            'actions' => $actions,
        ]);
    }

    /**
     * @param  array<string, mixed>  $action
     */
    public function addAction(array $action): self
    {
        $actions = $this->meta['actions'] ?? [];
        $actions = is_array($actions) ? $actions : [];
        $actions[] = $action;

        return $this->meta([
            'actions' => $actions,
        ]);
    }
}
