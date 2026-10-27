<?php

declare(strict_types=1);

use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\SerialNumberColumn;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Contracts\TableState as TableStateContract;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\Tables\TableEmptyState;
use Wtroiano\TableBuilder\Tables\TableNavigation;
use Wtroiano\TableBuilder\Tables\TablePayloadBuilder;
use Wtroiano\TableBuilder\Tables\TableState;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;

it('retains column overrides from contract-based default state', function (): void {
    $table = new class extends Table
    {
        public function columns(): array
        {
            return [];
        }

        public function defaultState(): TableStateContract
        {
            return new TableState(columns: ['email'], sticky: [], hasStickyOverride: true);
        }
    };
    $state = $table->state(TableRequest::fromArray(['search' => 'Ada']));
    expect($state->columns())->toBe(['email'])->and($state->sticky())->toBe([])->and($state->hasStickyOverride())->toBeTrue();
});

it('preserves hidden columns and explicit sticky overrides through every navigation operation', function (string $operation): void {
    $table = new class extends Table
    {
        public function columns(): array
        {
            return [];
        }
    };
    $table = $table->named('people');

    $state = new TableState(columns: ['email'], sticky: [], hasStickyOverride: true);
    $navigation = new TableNavigation($table, $state);
    $query = match ($operation) {
        'page' => $navigation->page(2),
        'cursor' => $navigation->cursor('next'),
        'perPage' => $navigation->perPage(30),
        'search' => $navigation->search(' Ada '),
        'sort' => $navigation->sort('name', 'desc'),
    };
    expect($query['people']['columns'])->toBe(['email'])->and($query['people']['sticky'])->toBe('');
})->with(['page', 'cursor', 'perPage', 'search', 'sort']);

it('treats column overrides as part of the default-state comparison', function (): void {
    $table = new class extends Table
    {
        public function columns(): array
        {
            return [];
        }
    };
    $payload = resolve(TablePayloadBuilder::class)->buildResults($table, new TableState(columns: ['email']), []);
    expect($payload['inDefaultState'])->toBeFalse();
});

it('uses export configuration for actual export behavior as well as metadata', function (): void {
    $column = TextColumn::make('amount')->export(['enabled' => false, 'format' => '0.00', 'style' => ['font' => ['bold' => true]]]);
    expect($column->isExportable())->toBeFalse()->and($column->resolvedExportFormat())->toBe('0.00')
        ->and($column->resolvedExportStyle())->toBe(['font' => ['bold' => true]]);
});

it('does not advertise cleared export configuration', function (): void {
    $column = TextColumn::make('amount')->exportFormat('0.00')->exportStyle(['font' => ['bold' => true]])
        ->exportFormat(null)->exportStyle(null);
    expect($column->toArray()['export'])->not->toHaveKeys(['format', 'style']);
});

it('can re-enable toggling without changing serial-number defaults', function (): void {
    expect(TextColumn::make('name')->toggleable(false)->toggleable(true)->toArray()['toggleable'])->toBeTrue()
        ->and(SerialNumberColumn::make()->toArray()['toggleable'])->toBeFalse()
        ->and(SerialNumberColumn::make()->toggleable()->toArray()['toggleable'])->toBeTrue();
});

it('resolves definitions and export authorization once per payload, never across payloads', function (): void {
    $table = new class extends Table
    {
        public array $calls = ['columns' => 0, 'actions' => 0, 'exports' => 0, 'authorize' => 0];

        public function columns(): array
        {
            $this->calls['columns']++;

            return [TextColumn::make('name')];
        }

        public function actions(): array
        {
            $this->calls['actions']++;

            return [Action::make('View')];
        }

        public function exports(): array
        {
            $this->calls['exports']++;

            return [Export::make('Report')->authorize(function (): bool {
                $this->calls['authorize']++;

                return true;
            })];
        }
    };
    $records = collect([new PipelineRecord(['id' => 1, 'name' => 'Ada']), new PipelineRecord(['id' => 2, 'name' => 'Grace'])]);
    $builder = resolve(TablePayloadBuilder::class);
    $builder->buildResults($table, new TableState(), $records);

    expect($table->calls)->toBe(['columns' => 1, 'actions' => 1, 'exports' => 1, 'authorize' => 1]);
    $builder->buildResults($table, new TableState(), $records);
    expect($table->calls)->toBe(['columns' => 2, 'actions' => 2, 'exports' => 2, 'authorize' => 2]);
});

it('keeps deprecated factories compatible with fluent configuration and strict identity types', function (): void {
    expect(TextColumn::create('email')->searchable()->isSearchable())->toBeTrue()
        ->and(SerialNumberColumn::create()->toArray()['toggleable'])->toBeFalse()
        ->and(Export::create(label: 'Report')->toArray()['label'])->toBe('Report')
        ->and(Action::create('Run')->key('run')->keyName())->toBe('run')
        ->and(TableEmptyState::create()->toArray()['title'])->toBeNull();
    expect(fn (): TextColumn => TextColumn::create(123))->toThrow(TypeError::class);
});

it('restores definition resolution after a rendering exception', function (): void {
    $table = new class extends Table
    {
        public int $calls = 0;

        public function columns(): array
        {
            $this->calls++;

            return [TextColumn::make('name')->mapAs(static fn (): never => throw new RuntimeException('Render failed'))];
        }
    };
    $builder = resolve(TablePayloadBuilder::class);
    expect(fn () => $builder->buildResults($table, new TableState(), collect([['name' => 'Ada']])))->toThrow(RuntimeException::class);
    $builder->buildResults($table, new TableState(), []);
    expect($table->calls)->toBe(2);
});

it('does not carry a render-scoped definition snapshot into a cloned table', function (): void {
    $table = new class extends Table
    {
        public int $calls = 0;

        public function columns(): array
        {
            $this->calls++;

            return [];
        }
    };
    $named = $table->withResolvedDefinitions(fn (): Table => $table->named('other'));
    resolve(TablePayloadBuilder::class)->buildResults($named, new TableState(), []);
    expect($named->calls)->toBe(2);
});

it('keeps export metadata derived when fluent configuration changes', function (): void {
    $column = TextColumn::make('amount')->export(['enabled' => false, 'format' => '0.00', 'custom' => true, 'vendor' => 'value'])
        ->dontExport(false)->exportAs(static fn (mixed $value): mixed => $value)
        ->exportStyle(static fn (): array => ['font' => ['bold' => true]]);
    expect($column->toArray()['export'])->toMatchArray(['enabled' => true, 'format' => '0.00', 'custom' => true, 'vendor' => 'value'])
        ->and($column->resolvedExportStyle())->toBe(['font' => ['bold' => true]]);
    expect(fn (): TextColumn => TextColumn::make('amount')->export(['format' => []]))->toThrow(InvalidArgumentException::class);
});
