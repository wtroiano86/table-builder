<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Table;

final class QueryLifecycleTable extends Table
{
    public bool $aliased = false;

    public bool $ordered = false;

    public bool $scoped = false;

    public bool $deleteRecords = false;

    public bool $renameRecords = false;

    public ?int $limit = null;

    public ?int $offset = null;

    public int $actionChunkSize = 2;

    public bool $partial = false;

    public int $queryCalls = 0;

    public string $field = 'name';

    public ?Closure $afterQuery = null;

    public ?Export $definition = null;

    protected ?string $resource = QualityParentRecord::class;

    protected PaginationType $paginationType = PaginationType::Cursor;

    protected ?int $defaultPerPage = 2;

    protected ?array $perPageOptions = [1, 2];

    public function columns(): array
    {
        return [TextColumn::make($this->field, sortable: true, searchable: true)];
    }

    public function actions(): array
    {
        return [Action::make('Run')->key('run')->asBulkAction($this->actionChunkSize)->handle(function (Model $record): mixed {
            $key = $record->getKey();
            if ($this->deleteRecords) {
                $record->delete();
            } elseif ($this->renameRecords) {
                $record->update(['name' => 'Changed']);
            }

            return $key;
        })];
    }

    public function exports(): array
    {
        return [$this->definition ?? Export::make('Records', 'records.xlsx')->key('records')->limitToFilteredRows()->asDownload(false)];
    }

    public function query(): Builder
    {
        $this->queryCalls++;
        $query = parent::query();
        if ($this->aliased) {
            $query->from('quality_parents as p')->select('p.*');
        }

        if ($this->partial) {
            $query->select('name');
        }

        if ($this->ordered) {
            $query->orderBy('name', 'desc');
        }

        if ($this->scoped) {
            $query->withGlobalScope('review', static function (Builder $scoped): void {
                $scoped->where('id', '<=', 3)->orderBy('name', 'desc');
            });
        }

        if ($this->afterQuery instanceof Closure) {
            $query->afterQuery($this->afterQuery);
        }

        if ($this->limit !== null) {
            $query->limit($this->limit);
        }

        if ($this->offset !== null) {
            $query->offset($this->offset);
        }

        return $query;
    }
}
