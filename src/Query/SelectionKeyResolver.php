<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Query;

use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Table;

final class SelectionKeyResolver
{
    public static function resolve(Table $table, Model $model): string
    {
        $key = $table->rowSelectionKey();

        // The public id field is the row's primary-key alias, including UUID keys.
        return $key === null || $key === 'id' ? $model->getKeyName() : $key;
    }
}
