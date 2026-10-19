<?php

declare(strict_types=1);

use Wtroiano\TableBuilder\Columns\ActionColumn;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Columns\ImageColumn;
use Wtroiano\TableBuilder\Columns\NumericColumn;
use Wtroiano\TableBuilder\Columns\SerialNumberColumn;
use Wtroiano\TableBuilder\Commands\MakeTableCommand;
use Wtroiano\TableBuilder\Table;

arch()->preset()->php();
// Consumers extend these base classes to define tables and custom columns.
arch()->preset()->strict()->ignoring([
    Table::class,
    Column::class,
    MakeTableCommand::class,
    ActionColumn::class,
    ImageColumn::class,
    NumericColumn::class,
    SerialNumberColumn::class,
]);
arch()->preset()->laravel();
arch()->preset()->security();

// Extension points still obey strict typing, equality, and concrete-class rules.
arch()->expect('Wtroiano\\TableBuilder')->toUseStrictTypes();
arch()->expect('Wtroiano\\TableBuilder')->toUseStrictEquality();
arch()->expect('Wtroiano\\TableBuilder')->classes()->toBeFinal()->ignoring([
    Table::class,
    Column::class,
]);
