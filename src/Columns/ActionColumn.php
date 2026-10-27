<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

use ArgumentCountError;
use Wtroiano\TableBuilder\Enums\ColumnAlignment;

final class ActionColumn extends Column
{
    protected function __construct(string $name = 'actions')
    {
        parent::__construct($name);

        $this->alignment = ColumnAlignment::Center;
    }

    public static function make(string $name = 'actions'): static
    {
        throw_if(func_num_args() > 1, ArgumentCountError::class, 'Use fluent methods for additional factory configuration.');

        return new self($name);
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
