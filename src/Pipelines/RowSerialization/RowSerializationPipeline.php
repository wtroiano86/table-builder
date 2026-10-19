<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pipelines\RowSerialization;

use Illuminate\Pipeline\Pipeline;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\Pipes\AttachSelectionAndActions;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\Pipes\ResolveColumns;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\Pipes\ResolveIdentity;
use Wtroiano\TableBuilder\Pipelines\RowSerialization\Pipes\TransformRow;
use Wtroiano\TableBuilder\Table;

final class RowSerializationPipeline
{
    /** @return array<string, mixed> */
    public function serialize(Table $table, mixed $record): array
    {
        /** @var RowContext $context */
        $context = new Pipeline()
            ->send(new RowContext($table, $record))
            ->through([
                new ResolveIdentity(),
                new ResolveColumns(),
                new AttachSelectionAndActions(),
                new TransformRow(),
            ])
            ->thenReturn();

        return $context->row;
    }
}
