<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\RowSerialization;

use Wtroiano\TableBuilder\Table;

final class RowContext
{
    /** @var array<string, mixed> */
    public array $row = [];

    public function __construct(
        public readonly Table $table,
        public readonly mixed $record,
    ) {}
}
