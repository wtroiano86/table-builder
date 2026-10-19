<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables;

use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Exports\Export;

/** @phpstan-import-type ColumnPayload from Column */
final class TableDefinitions
{
    /** @var list<ColumnPayload>|null */
    private ?array $serializedColumns = null;

    /**
     * @param  list<Column>  $columns
     * @param  list<Action>  $actions
     * @param  list<Export>  $exports
     */
    public function __construct(public readonly array $columns, public readonly array $actions, public readonly array $exports) {}

    /** @return list<ColumnPayload> */
    public function serializedColumns(): array
    {
        return $this->serializedColumns ??= array_map(
            static fn (Column $column): array => $column->toArray(),
            $this->columns,
        );
    }
}
