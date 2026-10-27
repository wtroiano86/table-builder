<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL as UrlFactory;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\ActionColumn;
use Wtroiano\TableBuilder\Columns\BadgeColumn;
use Wtroiano\TableBuilder\Columns\BooleanColumn;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Columns\DateColumn;
use Wtroiano\TableBuilder\Columns\DateTimeColumn;
use Wtroiano\TableBuilder\Columns\ImageColumn;
use Wtroiano\TableBuilder\Columns\NumericColumn;
use Wtroiano\TableBuilder\Columns\SerialNumberColumn;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Enums\BadgeVariant;
use Wtroiano\TableBuilder\Enums\ColumnAlignment;
use Wtroiano\TableBuilder\Enums\HttpMethod;
use Wtroiano\TableBuilder\Enums\ImageSize;
use Wtroiano\TableBuilder\Enums\TableColor;
use Wtroiano\TableBuilder\Exceptions\TableBuilderException;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Support\Image;
use Wtroiano\TableBuilder\Support\Url;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineTable;

beforeEach(function (): void {
    Route::get('/configuration/{id}', static fn (): string => 'ok')->name('configuration');
});

it('serializes navigational behavior and validates generated signed URLs', function (): void {
    $url = new Url()->route('configuration', ['id' => 7], false)->preserveScroll()->preserveState()->openInNewTab()->asDownload()->disabled()->hidden()->modal()->prefetch('hover', 60)->method('POST');
    expect($url->jsonSerialize())->toBe([
        'url' => '/configuration/7', 'target' => '_blank', 'preserveScroll' => true, 'preserveState' => true,
        'download' => true, 'disabled' => true, 'hidden' => true, 'modal' => true,
        'prefetch' => ['strategy' => 'hover', 'cacheFor' => 60], 'method' => 'post',
    ])->and($url->shouldOpenInNewTab())->toBeTrue()->and($url->value())->toBe('/configuration/7');
    expect(new Url()->method(HttpMethod::DELETE)->method('unsupported')->toArray()['method'])->toBe('delete');
    $signed = new Url()->signedRoute('configuration', ['id' => 7]);
    $temporary = new Url()->temporarySignedRoute('configuration', now()->addMinute(), ['id' => 7]);
    expect(UrlFactory::hasValidSignature(Request::create((string) $signed)))->toBeTrue()
        ->and(UrlFactory::hasValidSignature(Request::create((string) $temporary)))->toBeTrue();
});

it('serializes image galleries and all presentation sizes', function (): void {
    $image = Image::make()->to(['/a.png', '', '/b.png'])->rounded()->dimensions(80, 60)->start()->end()->class('one')->class(' two ')->class('')->alt('Portrait')->title('Profile')->icon('user')->limit(-1);
    expect($image->jsonSerialize())->toMatchArray([
        'url' => null, 'urls' => ['/a.png', '/b.png'], 'rounded' => true, 'width' => 80, 'height' => 60,
        'position' => 'end', 'class' => 'one two', 'alt' => 'Portrait', 'title' => 'Profile', 'icon' => 'user', 'limit' => 0,
    ]);
    foreach (['small' => 'size-4', 'medium' => 'size-6', 'large' => 'size-8', 'extraLarge' => 'size-10'] as $method => $classes) {
        $size = ImageSize::from($image->{$method}()->toArray()['size']);
        expect($size->classes())->toBe($classes);
    }

    expect(Image::make()->route('configuration', ['id' => 8])->toArray()['url'])->toEndWith('/configuration/8')
        ->and(Image::make()->signedRoute('configuration', ['id' => 8])->toArray()['url'])->toContain('signature=')
        ->and(Image::make()->temporarySignedRoute('configuration', now()->addMinute(), ['id' => 8])->toArray()['url'])->toContain('expires=');
});

it('preserves specialized column metadata and factory defaults', function (): void {
    $badge = BadgeColumn::make('status')->colors(['open' => TableColor::Green, 'closed' => 'red'])->solid()->outline()->ghost()->variant(BadgeVariant::Solid)->icons(['open' => 'check'])->icon('info');
    expect($badge->toArray()['meta'])->toBe(['colors' => ['open' => 'green', 'closed' => 'red'], 'variant' => 'solid', 'icons' => ['open' => 'check'], 'icon' => 'info']);
    expect(BooleanColumn::make('active')->trueLabel('Yes')->falseLabel('No')->trueIcon('check')->falseIcon('x')->nullLabel('Unknown')->displayAs('icon')->toArray()['meta'])
        ->toBe(['trueLabel' => 'Yes', 'falseLabel' => 'No', 'trueIcon' => 'check', 'falseIcon' => 'x', 'nullLabel' => 'Unknown', 'displayAs' => 'icon']);
    expect(DateColumn::make('created_at')->format('Y-m-d')->placeholder('—')->toArray()['meta'])->toBe(['format' => 'Y-m-d', 'placeholder' => '—']);
    expect(DateTimeColumn::make('created_at')->format('Y-m-d H:i')->placeholder('—')->timezone('UTC')->toArray()['meta'])->toBe(['format' => 'Y-m-d H:i', 'placeholder' => '—', 'timezone' => 'UTC']);

    $numeric = NumericColumn::make('amount')->precision(2)->thousandsSeparator(',')->decimalSeparator('.')->prefix('$')->suffix(' USD')->placeholder('—')->presentation('currency');
    expect($numeric->toArray()['alignment'])->toBe('right')->and($numeric->toArray()['meta'])->toBe(['precision' => 2, 'thousandsSeparator' => ',', 'decimalSeparator' => '.', 'prefix' => '$', 'suffix' => ' USD', 'placeholder' => '—', 'presentation' => 'currency']);
    $image = ImageColumn::make('avatar')->imageWidth(40)->imageHeight('3rem')->rounded()->circle()->fit('cover')->alt('Avatar')->placeholder('/empty.png');
    expect($image->toArray()['meta'])->toBe(['imageWidth' => 40, 'imageHeight' => '3rem', 'shape' => 'circle', 'fit' => 'cover', 'alt' => 'Avatar', 'placeholder' => '/empty.png'])
        ->and($image->resolveImage(['avatar' => '/avatar.png'])['url'])->toBe('/avatar.png');
    expect(SerialNumberColumn::make()->toArray())->toMatchArray(['toggleable' => false, 'label' => 'S.no', 'alignment' => 'center', 'width' => '4rem']);
    $actions = ActionColumn::make()->actions([['key' => 'view']])->addAction(['key' => 'edit']);
    expect(array_column($actions->toArray()['meta']['actions'], 'key'))->toBe(['view', 'edit']);
});

it('carries column layout and export configuration through serialization', function (): void {
    $column = TextColumn::make('amount')->alignment(ColumnAlignment::Right)->width('8rem')->minWidth(40)->maxWidth(160)->labelClass('heading')->cellClass('number')->tooltip('Total')->defaultValue('—')
        ->exportLabel(static fn (): string => 'Amount')->exportAs(static fn (mixed $value): float => (float) $value)->exportFormat('0.00')->exportStyle(static fn (): array => ['font' => ['bold' => true]]);
    expect($column->toArray())->toMatchArray(['width' => '8rem', 'minWidth' => 40, 'maxWidth' => 160, 'defaultValue' => '—', 'tooltip' => 'Total', 'labelClass' => 'heading', 'cellClass' => 'number', 'alignment' => 'right'])
        ->and($column->resolvedExportLabel())->toBe('Amount')->and($column->resolveExportValue(['amount' => '12.5']))->toBe(12.5)
        ->and($column->resolvedExportStyle())->toBe(['font' => ['bold' => true]])
        ->and(TextColumn::make('name')->alignment('custom')->toArray()['alignment'])->toBe('custom');
});

it('resolves media callback signatures and omits unsupported URL values', function (): void {
    $record = ['avatar' => ['/a.png', 1, '/b.png']];
    expect(TextColumn::make('name')->image('avatar')->resolveImage($record)['urls'])->toBe(['/a.png', '/b.png']);
    $callbacks = [
        static fn (): string => '/a.png',
        static fn (Image $image): Image => $image->url('/a.png'),
        static fn (Image $image, array $row): Image => $image->url($row['avatar'][0]),
        static fn (array $row): array => $row['avatar'],
        static fn (array $row, Image $image): string => $row['avatar'][0],
    ];
    foreach ($callbacks as $callback) {
        $resolved = TextColumn::make('name')->image($callback, static fn (Image $image): Image => $image->rounded())->resolveImage($record);
        expect($resolved['url'] ?? $resolved['urls'][0])->toBe('/a.png')->and($resolved['rounded'])->toBeTrue();
    }

    expect(TextColumn::make('name')->url(new Url()->to('/a'))->resolveUrl([]))->toBe(['url' => '/a', 'target' => null])
        ->and(TextColumn::make('name')->url(static fn (): int => 1)->resolveUrl([]))->toBeNull();
});

it('serializes action names links and confirmation callbacks for rows and bulk controls', function (): void {
    $record = new PipelineRecord(['id' => 7, 'team' => 'red']);
    $action = Action::make('Original')->name(static fn (PipelineRecord $row): string => 'View :id')->url(static fn (PipelineRecord $row, Url $url): Url => $url->to('/records/'.$row->getKey()))
        ->confirm(static fn (PipelineRecord $row): array => ['title' => 'Delete :id', 'message' => $row->team]);
    expect($action->forRow($record))->toMatchArray(['label' => 'View 7', 'url' => ['url' => '/records/7', 'target' => null], 'confirm' => ['title' => 'Delete 7', 'message' => 'red']]);
    expect($action->asBulkAction()->forBulk()['label'])->toBe('action');
    $action->name('Edit :id')->url('/edit')->confirm('Edit :id', 'Continue?', 'Yes', 'No')->disabledAndHidden();
    expect($action->jsonSerialize())->toMatchArray(['disabled' => true, 'hidden' => true, 'confirm' => ['title' => 'Edit :id', 'message' => 'Continue?', 'confirmButton' => 'Yes', 'cancelButton' => 'No']]);
    $action->disabledAndHidden(false)->confirm(static fn (): string => 'Edit :id', static fn (): string => 'Continue?');
    expect($action->forRow($record)['confirm']['title'])->toBe('Edit 7');
    $action->confirm(['title' => static fn (): string => 'Run :id'])->url(['url' => '/custom']);
    expect($action->forRow($record)['confirm'])->toBe(['title' => 'Run 7'])->and($action->forRow($record)['url'])->toBe(['url' => '/custom']);
    expect(Action::make(static fn (): int => 5)->toArray()['label'])->toBe('action')
        ->and(Action::make('Run')->confirm(static fn (): int => 5)->toArray()['confirm'])->toBeFalse()
        ->and(Action::make('Run')->onlyAsBulkAction()->isOnlyBulk())->toBeTrue()
        ->and(Action::make('Run')->asInfoButton()->asSuccessButton()->asDangerButton()->hideLabel()->toArray())->toMatchArray(['variantColor' => 'red', 'showLabel' => false]);
});

it('supports action success callbacks and string confirmations while rejecting empty names', function (): void {
    expect(fn (): Action => Action::make(''))->toThrow(TableBuilderException::class, 'Action name cannot be empty.');
    $action = Action::make('Run');
    expect(fn (): Action => $action->name(''))->toThrow(TableBuilderException::class, 'Action name cannot be empty.');
    $action->success(static fn (array $payload): array => [...$payload, 'message' => 'Done'])
        ->confirm(static fn (): string => 'Continue?')
        ->url(static fn (PipelineRecord $record, Action $definition): string => '/'.$definition->keyName().'/'.$record->getKey());
    expect($action->successResponse(['ok' => true]))->toBe(['ok' => true, 'message' => 'Done'])
        ->and($action->forRow(new PipelineRecord(['id' => 3])))->toMatchArray([
            'url' => ['url' => '/run/3', 'target' => null],
            'confirm' => ['title' => 'Continue?', 'message' => null, 'confirmButton' => null, 'cancelButton' => null],
        ]);
});

it('supports explicit export labels styles and disabled columns without losing URL configuration', function (): void {
    $column = TextColumn::make('name')->url('/retained')->url(null)->exportLabel('Display name')->exportStyle(['font' => ['bold' => true]]);
    expect($column->toArray()['export']['style'])->toBe(['font' => ['bold' => true]])
        ->and($column->resolvedExportLabel())->toBe('Display name')->and($column->resolveUrl([]))->toBe('/retained')
        ->and($column->exportAs(false)->isExportable())->toBeFalse();
});

it('inherits export defaults and supports named redirects and serialization', function (): void {
    $class = Export::class;
    $class::defaultLimitToFilteredRows();
    $class::defaultLimitToSelectedRows();
    $class::defaultQueueDisk('local');
    $class::defaultQueueName('reports');
    try {
        $export = $class::make('Records')->authorize(false)->limitToFilteredRows()->limitToSelectedRows()->queue()
            ->redirectToRoute('configuration', ['id' => 9]);
        expect($export->isAuthorized(PipelineTable::make()))->toBeFalse()
            ->and($export->jsonSerialize())->toMatchArray(['queued' => true, 'limitToFilteredRows' => true, 'limitToSelectedRows' => true])
            ->and($export->queueName())->toBe('reports')->and($export->queueDisk())->toBe('local')
            ->and($export->redirectResponse()->getTargetUrl())->toEndWith('/configuration/9');
    } finally {
        $class::defaultLimitToFilteredRows(false);
        $class::defaultLimitToSelectedRows(false);
        $class::defaultQueueDisk();
        $class::defaultQueueName();
    }
});

it('preserves action column dropdown metadata through fluent configuration', function (): void {
    expect(ActionColumn::make()->toArray()['meta'])->toBe([])
        ->and(ActionColumn::make()->meta(['asDropdown' => true])->toArray()['meta'])->toBe(['asDropdown' => true]);
});

it('rejects removed factory arguments instead of silently discarding configuration', function (string $class, string $identity, string $factory): void {
    expect(fn (): object => $class::$factory($identity, 'extra'))->toThrow(ArgumentCountError::class, 'Use fluent methods')
        ->and(fn (): object => $class::$factory($identity, meta: []))->toThrow(Error::class, 'Unknown named parameter $meta');
})->with([
    'column' => [TextColumn::class, 'name'],
    'action column' => [ActionColumn::class, 'controls'],
    'serial number column' => [SerialNumberColumn::class, 'row'],
    'action' => [Action::class, 'Run'],
    'export' => [Export::class, 'Records'],
])->with(['make', 'create']);

it('serializes action presentation and merged attributes configured fluently', function (bool $confirmation): void {
    $action = Action::make('Run')->icon('play')->tooltip('Run now')->variant('outline')->class('run-action')
        ->confirm($confirmation)->meta(['group' => 'old', 'priority' => 1])->meta(['group' => 'new'])
        ->data(['target' => 'old', 'mode' => 'safe'])->data(['target' => 'new']);

    expect($action->toArray())->toMatchArray([
        'icon' => 'play',
        'tooltip' => 'Run now',
        'variant' => 'outline',
        'class' => 'run-action',
        'confirm' => $confirmation,
        'meta' => ['group' => 'new', 'priority' => 1],
        'data' => ['target' => 'new', 'mode' => 'safe'],
    ]);
})->with([true, false]);

it('merges export metadata and data attributes through fluent configuration', function (): void {
    $export = Export::make('Records')->meta(['group' => 'old', 'priority' => 1])->meta(['group' => 'new'])
        ->dataAttributes(['target' => 'old', 'mode' => 'safe'])->dataAttributes(['target' => 'new']);

    expect($export->toArray())->toMatchArray([
        'meta' => ['group' => 'new', 'priority' => 1],
        'data' => ['target' => 'new', 'mode' => 'safe'],
    ]);
});

it('retains configured column values and callbacks when optional fluent inputs are null', function (): void {
    $queryCallback = static fn (): null => null;
    $column = TextColumn::make('name')->key('display')->label('Display name')->alignment('right')
        ->mapAs(static fn (string $value): string => mb_strtoupper($value))
        ->exportAs(static fn (string $value): string => 'Export '.$value)->exportLabel('Export name')
        ->searchUsing($queryCallback)->sortUsing($queryCallback);

    $column->key(null)->label(null)->alignment(null)->mapAs(null)->exportAs()->exportLabel(null)
        ->searchUsing(null)->sortUsing(null);

    expect($column->toArray())->toMatchArray(['key' => 'display', 'label' => 'Display name', 'alignment' => 'right'])
        ->and($column->resolveValue(['name' => 'Ada']))->toBe('ADA')
        ->and($column->resolveExportValue(['name' => 'Ada']))->toBe('Export ADA')
        ->and($column->resolvedExportLabel())->toBe('Export name')
        ->and($column->searchCallback())->toBe($queryCallback)
        ->and($column->sortCallback())->toBe($queryCallback);
});

it('preserves custom column constructor defaults until fluent configuration overrides them', function (): void {
    $prototype = new class('prototype') extends Column
    {
        public function __construct(string $name)
        {
            parent::__construct($name);

            $this->searchable = true;
            $this->visible = false;
            $this->exportable = false;
        }
    };
    $column = $prototype::make('name');

    expect($column->isSearchable())->toBeTrue()
        ->and($column->isVisible())->toBeFalse()
        ->and($column->isExportable())->toBeFalse();

    $column->searchable(false)->visible()->dontExport(false);

    expect($column->isSearchable())->toBeFalse()
        ->and($column->isVisible())->toBeTrue()
        ->and($column->isExportable())->toBeTrue();
});
