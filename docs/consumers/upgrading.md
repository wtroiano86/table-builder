# Upgrading to v2

Version 2 simplifies column, action and export factories. Update calls before requiring `wtroiano/table-builder:^2.0`. The table payload and published React interfaces retain their existing shape.

## Factory arguments

Factories now accept only their identity argument:

| Factory | Argument |
| --- | --- |
| `TextColumn::make()` and other ordinary columns | Required `string $name` |
| `ActionColumn::make()` | Optional `string $name = 'actions'` |
| `SerialNumberColumn::make()` | Optional `string $name = '_serial_number'` |
| `Action::make()` | Required `string\|callable $name` |
| `Export::make()` | Optional `?string $label = null` |

Move every other positional or named argument to a fluent method. For example, replace v1's `TextColumn::make('name', label: 'Full name', sortable: true, searchable: true)` with:

```php
use Wtroiano\TableBuilder\Columns\TextColumn;

$column = TextColumn::make('name')->label('Full name')->sortable()->searchable();
```

Replace v1's `Action::make('Archive', key: 'archive', bulk: true, chunkSize: 200)` with:

```php
use Wtroiano\TableBuilder\Actions\Action;

$action = Action::make('Archive')->key('archive')->asBulkAction(200);
```

Replace v1's `Export::make('Users', 'users.csv', type: 'Csv', queued: true)` with:

```php
use Maatwebsite\Excel\Excel;
use Wtroiano\TableBuilder\Exports\Export;

$export = Export::make('Users')->filename('users.csv')->type(Excel::CSV)->queue();
```

Most former option names are also fluent method names: `label()`, `sortable()`, `searchable()`, `visible()`, `mapAs()`, `meta()`, `handle()`, `authorize()`, `before()`, `after()`, `success()`, `error()`, and the column export methods retain their types and behavior. The following options need a different mapping:

| Former option | Fluent configuration |
| --- | --- |
| Action `bulk: true` | `asBulkAction()` |
| Action `onlyBulk: true` | `onlyAsBulkAction()` |
| Action `chunkSize`, `chunkStrategy` | Arguments to `asBulkAction($chunkSize, $strategy)` or `onlyAsBulkAction($chunkSize, $strategy)` |
| Export `queued: true` | `queue()` |
| Export `data` | `dataAttributes($data)` |
| Action-column `asDropdown: true` | `asDropdown()` |

Omit disabled bulk/queue options and null lifecycle callbacks. For conditional configuration, use the action/export `when()` helper or an application conditional. Export `limitToFilteredRows(false)` and `limitToSelectedRows(false)` explicitly override enabled global defaults; omit these calls to inherit the defaults, matching the old factory's false-option behavior. `asDropdown(false)` writes disabled dropdown metadata; omit it to retain metadata supplied elsewhere.

Extra positional arguments now throw `ArgumentCountError`; removed named arguments throw PHP's unknown-named-parameter error. The deprecated `create()` alias follows the same signatures and errors. Replace legacy `create()` calls with `make()` and fluent configuration during migration. `Table::make()`, `TableBuilder::make()`, `TableEmptyState::make()`, and the URL/image builders retain their existing signatures.

## Custom columns

Factories now use the column constructor's defaults directly. If a custom constructor sets search, sort, visibility or export defaults, those defaults are preserved until a fluent setter overrides them. Standard column defaults, including serial-number visibility controls and numeric alignment, stay the same.

The protected `Column::fromOptions()` helper and its `ColumnOptions` PHPStan alias have been removed. Custom factories that used them should call `parent::make($name)` and apply fluent setters. Keep custom constructors compatible with the inherited `string $name` argument.
