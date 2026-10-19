<?php

declare(strict_types=1);

use Wtroiano\TableBuilder\Columns\ActionColumn;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Query\SearchTermParser;
use Wtroiano\TableBuilder\Query\TableQueryParameters;
use Wtroiano\TableBuilder\Tables\TableState;

it('parses words and quoted phrases', function (?string $input, array $expected): void {
    expect(new SearchTermParser()->parse($input))->toBe($expected);
})->with([
    [null, []],
    ['', []],
    ['"0" 0', ['0', '0']],
    ['  one two  ', ['one', 'two']],
    ['"Ada Lovelace" engineer', ['Ada Lovelace', 'engineer']],
]);

it('normalizes sort direction without rejecting unknown input', function (): void {
    expect(TableQueryParameters::fromArray(['direction' => 'unknown'])->direction())->toBe('asc')
        ->and(TableQueryParameters::fromArray(['direction' => ' DESC '])->direction())->toBe('desc');
});

it('normalizes table state and preserves column overrides', function (): void {
    $state = TableState::fromArray([
        'search' => ' Ada ',
        'sort' => ['column' => 'name', 'direction' => 'desc'],
        'columns' => ['email', 'email', '', null],
        'sticky' => ['name'],
    ]);

    expect($state->search())->toBe('Ada')
        ->and($state->sort())->toBe('name')
        ->and($state->direction())->toBe('desc')
        ->and($state->columns())->toBe(['email'])
        ->and($state->sticky())->toBe(['name'])
        ->and($state->hasStickyOverride())->toBeTrue();
});

it('supports typed action column factories and named dropdown options', function (): void {
    $column = ActionColumn::make(label: 'Options', sortable: true, asDropdown: true);

    expect($column->name())->toBe('actions')
        ->and($column->toArray()['meta'])->toMatchArray(['asDropdown' => true, 'dropdown' => true])
        ->and($column->toArray()['label'])->toBe('Options')
        ->and($column->isSortable())->toBeTrue()
        ->and(ActionColumn::create('controls', 'Manage', asDropdown: true)->name())->toBe('controls');
});

it('resolves custom column callbacks safely', function (): void {
    $column = TextColumn::make('name', label: static fn (): string => 'Display name')
        ->url(static fn (): string => '/users/1')
        ->mapAs(static fn (mixed $value): string => mb_strtoupper((string) $value));

    expect($column->resolvedLabel())->toBe('Display name')
        ->and($column->resolveUrl(['name' => 'Ada']))->toBe('/users/1')
        ->and($column->resolveValue(['name' => 'Ada']))->toBe('ADA');
});
