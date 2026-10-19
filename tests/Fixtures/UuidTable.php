<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Table;

final class UuidTable extends Table
{
    protected ?string $resource = UuidRecord::class;

    protected bool $pagination = false;

    public function columns(): array
    {
        return [TextColumn::make('name')];
    }

    public function actions(): array
    {
        return [Action::make('Run')->key('run')->asBulkAction()->handle(static fn (Model $record): mixed => $record->getKey())];
    }

    public function exports(): array
    {
        return [Export::make('Selected')->key('selected')->limitToSelectedRows()];
    }
}
