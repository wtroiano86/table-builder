<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Table;

final class ActionTable extends Table
{
    public array $events = [];

    public string $strategy = 'chunkById';

    public ?int $failAt = null;

    public bool $failBefore = false;

    public bool $failAfter = false;

    public bool $httpFailure = false;

    public bool $authorized = true;

    public bool $disabled = false;

    public bool $hidden = false;

    public ?Closure $authorizeRecord = null;

    protected ?string $resource = PipelineRecord::class;

    public function columns(): array
    {
        return [];
    }

    public function query(): Builder
    {
        return parent::query()->orderBy('id');
    }

    public function isSelectable(Model $model): bool
    {
        return $model->getAttribute('team') === 'red';
    }

    public function actions(): array
    {
        return [Action::make('Run')->key('run')->asBulkAction(2, $this->strategy)
            ->authorize($this->authorized)->disabled($this->disabled)->hidden($this->hidden)
            ->before(function (array $models): void {
                $this->events[] = ['before', array_map(static fn (Model $model): mixed => $model->getKey(), $models)];

                throw_if($this->failBefore, RuntimeException::class, 'Before failed');
            })->handle(function (Model $model): int {
                if ($this->authorizeRecord instanceof Closure) {
                    ($this->authorizeRecord)($model);
                }

                $id = (int) $model->getKey();
                $this->events[] = ['execute', $id];

                if ($this->failAt === $id) {
                    abort_if($this->httpFailure, 409, 'Conflict');

                    throw new RuntimeException('Handler failed');
                }

                return $id * 10;
            })->after(function (array $models, array $results): void {
                $this->events[] = ['after', array_map(static fn (Model $model): mixed => $model->getKey(), $models), $results];

                throw_if($this->failAfter, RuntimeException::class, 'After failed');
            })];
    }
}
