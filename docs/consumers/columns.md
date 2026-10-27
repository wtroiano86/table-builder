# Columns

Import columns from `Wtroiano\TableBuilder\Columns`. Definitions are shared by rendering, query metadata and spreadsheet exports; PHP `mapAs()` changes the serialized value while `exportAs()` changes only its exported value.

## Define columns

Pass the list to `TableBuilder::columns()` or return it from a table's `columns()` method:

```php
use Wtroiano\TableBuilder\Columns\BadgeColumn;
use Wtroiano\TableBuilder\Columns\BooleanColumn;
use Wtroiano\TableBuilder\Columns\DateTimeColumn;
use Wtroiano\TableBuilder\Columns\NumericColumn;
use Wtroiano\TableBuilder\Columns\TextColumn;

$columns = [
    TextColumn::make('name')->sortable()->searchable()
        ->label('Full name')->minWidth('12rem'),
    TextColumn::make('email')->url(fn ($user): string => 'mailto:'.$user->email),
    BadgeColumn::make('status')->colors(['active' => 'green', 'paused' => 'neutral']),
    BooleanColumn::make('active')->trueLabel('Active')->falseLabel('Inactive'),
    NumericColumn::make('balance')->precision(2)->prefix('$'),
    DateTimeColumn::make('created_at')->format('M j, Y H:i')->timezone('UTC'),
];
```

Examples referencing `email`, `status`, `active` or `balance` assume those attributes exist on the application's model. Column factories accept only the field name; configure labels, search, sorting, presentation and export behavior with fluent setters. Use `key('unique_key')` when two columns read the same attribute.

## Available types

| Column | Built-in React behavior |
| --- | --- |
| `TextColumn` | Escaped text; objects and arrays are displayed as JSON |
| `BadgeColumn` | Text badges, with color/variant metadata and optional host-rendered icons |
| `BooleanColumn` | True/false labels; `nullLabel()` for null values; optional host-rendered icons |
| `NumericColumn` | Precision, separators, prefix/suffix; right-aligned by default |
| `DateColumn` | Date formatted in UTC |
| `DateTimeColumn` | Date/time with optional `timezone()` |
| `ImageColumn` | An image or image list; width/height, shape, fit, alt and placeholder options |
| `SerialNumberColumn` | A position counter using paginator offsets when available; cursor pages count from one |
| `ActionColumn` | Row actions inline or with `asDropdown()` |

The React [formatting and extension rules](react.md#react-customization) describe date tokens and custom renderers. Additional metadata can be consumed by your own renderer; not every metadata value has a built-in visual treatment. For example, map additional badge colors through CSS `data-color` selectors and provide icons with `renderIcon()`.

## Common options

| Intent | Methods/options |
| --- | --- |
| Name and content | `label()`, `key()`, `mapAs()`, `defaultValue()` |
| Visibility | `visible()`, `toggleable()` |
| Query participation | `searchable()`, `sortable()`, `searchUsing()`, `sortUsing()` |
| Layout | `alignment()`, `width()`, `minWidth()`, `maxWidth()`, `sticky()` |
| Styling and help | `labelClass()`, `cellClass()`, `tooltip()`, `meta()` |
| Navigation/media | `url()`, `image()` |
| Export | `dontExport()`, `exportLabel()`, `exportAs()`, `exportFormat()`, `exportStyle()` |

`visible(false)` seeds the table's initial hidden-column state. An explicit URL visibility override can reveal that column. Hidden columns still have serialized values; omit a sensitive column definition entirely when the viewer should not receive it. See [state and defaults](state-and-pagination.md#defaults-and-column-visibility).

## Values and identity

```php
use Wtroiano\TableBuilder\Columns\TextColumn;

$column = TextColumn::make('name')
    ->mapAs(fn (mixed $value): string => mb_strtoupper((string) $value));
```

`mapAs()` receives the attribute value and model. A column key is its serialized key, so sort/search state should use that key. Relation fields such as `manager.name` resolve through the model relationship. SQL-qualified fields such as `users.name` resolve the model's hydrated `name` value.

Formatted columns are separate from selection identity. React reads the key named by `table.rowSelectionKey` (normally `_selection_key`); `_primary_key` is the actual model primary key. Keep both reserved fields intact in `transformModel()`.

## Custom search and sort

```php
use Illuminate\Database\Eloquent\Builder;
use Wtroiano\TableBuilder\Columns\TextColumn;

$column = TextColumn::make('name')->sortable()->searchable()
    ->searchUsing(fn (Builder $query, string $term): Builder => $query->where('name', 'like', $term.'%'))
    ->sortUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('name', $direction));
```

The normal query pipeline only enables declared searchable/sortable fields. Sort callbacks receive the validated direction; column search callbacks run for each parsed search term; define any application-specific matching behavior in the callback. Eager-load relationships used by display callbacks in the resource query.

## Links and images

```php
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Support\Image;
use Wtroiano\TableBuilder\Support\Url;

$column = TextColumn::make('name')
    ->url(fn (Model $user, Url $url): Url => $url->to('/users/'.$user->getKey())->preserveScroll())
    ->image(fn (Model $user, Image $image): Image => $image
        ->url((string) $user->getAttribute('avatar_url'))
        ->alt((string) $user->getAttribute('name'))
        ->rounded());
```

`Url` supports route/signed-route helpers, method, target, download and state/scroll metadata. `Image` supports one or more URLs, size, dimensions, position, alt/title and limits. The default React view renders ordinary links and images; custom cells can supply application modal behavior.

## Export configuration

```php
use Wtroiano\TableBuilder\Columns\NumericColumn;
use Wtroiano\TableBuilder\Columns\TextColumn;

$columns = [
    TextColumn::make('internal_note')->dontExport(),
    NumericColumn::make('balance')->exportLabel('Balance')->exportFormat('#,##0.00'),
];
```

Export labels, values, number formats and styles are independent of React markup. [Export safety and custom exporters](exports.md) cover literal strings, trusted formulas, callbacks and queues.
