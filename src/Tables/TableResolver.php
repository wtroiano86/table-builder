<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables;

use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\TableRegistry;

/** @internal Resolves both existing class references and registered factory keys. */
final class TableResolver
{
    public function resolve(string $reference): Table
    {
        if (str_starts_with($reference, 'registry:')) {
            $key = mb_substr($reference, 9);
            abort_unless(TableRegistry::has($key), 422, 'Invalid table.');

            return TableRegistry::get($key);
        }

        abort_unless(is_subclass_of($reference, Table::class) && $reference !== ConfiguredTable::class, 422, 'Invalid table.');

        /** @var Table $table */
        $table = resolve($reference);

        return $table;
    }
}
