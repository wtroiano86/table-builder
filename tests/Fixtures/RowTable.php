<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Support\Url;
use Wtroiano\TableBuilder\Table;

final class RowTable extends Table
{
    public array $events = [];

    public string|Url|null $link = '/records';

    protected ?string $rowSelectionKey = 'code';

    public function columns(): array
    {
        return [
            TextColumn::make('name')->key('display_name')->mapAs(function (mixed $value): string {
                $this->events[] = 'columns';

                return mb_strtoupper((string) $value);
            })->url(static fn (mixed $record): string => (string) data_get($record, 'link', ''))
                ->image('avatar'),
        ];
    }

    public function rowUrl(Model $model, Url $url): string|Url|null
    {
        $this->events[] = 'identity';

        return $this->link;
    }

    public function isSelectable(Model $model): bool
    {
        return $model->getAttribute('team') === 'red';
    }

    public function actions(): array
    {
        return [
            Action::make('View :id')->key('view'),
            Action::make('Hidden', hidden: true),
            Action::make('Bulk')->onlyAsBulkAction(),
        ];
    }

    public function transformModel(Model $model, array $data): array
    {
        $this->events[] = 'transform';
        $data['transformed'] = isset($data['_actions'], $data['_column_images'], $data['_selectable']);

        return $data;
    }
}
