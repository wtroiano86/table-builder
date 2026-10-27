# Defining tables

Start with [Getting started](getting-started.md) for a complete controller, route and supplied page. This guide covers PHP definitions and the public table API. Fluent and class tables use the same pipelines.

Define a table inline with `TableBuilder`; no application table class is required:

```php
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\TableBuilder;

$table = TableBuilder::make('users')
    ->resource(User::class)
    ->authorize(fn (Request $request): bool => $request->user()?->can('viewAny', User::class) ?? false)
    ->columns([
        TextColumn::make('name')->sortable()->searchable(),
        TextColumn::make('email')->label('Email address'),
    ])
    ->defaultSort('name')
    ->perPage(15)
    ->build();

abort_unless($table->authorize($request), 403);
return Inertia::render('Users/Index', ['table' => $table]);
```

`make('users')` names the table and scopes URL state to `users[search]`, `users[page]`, and so on. `build()` returns a `Table` using the existing query, navigation, pagination, and payload APIs. Changing the builder after building does not change that table's configuration. Inline tables support rendering and link actions. Register a factory for executable actions or exports, as described below.

The builder also provides `perPageOptions()`, `pagination(enabled: false)`, `pagination(type: PaginationType::Cursor)`, `scrollToTop()`, `stickyHeader()`, `rowSelectionKey()`, `selectable()`, `persistRowSelectionAcrossPages()`, `emptyState()`, `searchable()`, and `defaultState()`. Import `Wtroiano\TableBuilder\Enums\PaginationType` for pagination modes. Row/query hooks are `selectableUsing()`, `rowUrl()`, `transformModel()`, and `withQueryBuilder()`; their callbacks match the corresponding `Table` methods. Authorization defaults to requiring an authenticated user.

### Table classes

Existing subclasses remain supported and use the same pipelines:

```php
namespace App\Tables;

use App\Models\User;
use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Table;

final class UsersTable extends Table
{
    protected ?string $resource = User::class;
    protected ?string $defaultSort = '-name';

    public function authorize(Request $request): bool
    {
        return $request->user()?->can('viewAny', User::class) ?? false;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('email')->label('Email address'),
        ];
    }
}
```

In an authenticated controller:

```php
$table = UsersTable::make();
abort_unless($table->authorize($request), 403);
return Inertia::render('Users/Index', ['table' => $table]);
```

Serialization reads the current query string. For explicit control use `$table->results(request: TableRequest::fromRequest($request))`, importing `Wtroiano\TableBuilder\Http\Requests\TableRequest`. `payload()` returns definitions and state without fetching records. `apply()` returns a filtered/sorted Eloquent builder; `paginate()` returns its paginator or collection.

The result contains `results`, `columns`, `state`, `meta`, `actions`, `exports`, pagination configuration, and capability flags. `state.sort` and `state.direction` describe the active sort. Paginated rows live in `results.data`; disabling pagination produces rows directly in `results`.

Always read selection identity using `row[table.rowSelectionKey]`. This field is now `_selection_key`, a raw value separate from formatted columns. `_primary_key` contains the actual model primary key. The protected PHP `$rowSelectionKey` setting still chooses the database selection column; `id` maps to the model's actual key, including UUID keys. Set it to `null` to omit selection identity. `_primary_key` and `_selection_key` are reserved column keys.

### Registered fluent tables

Actions and exports need to reconstruct a table in a later HTTP request or queue worker. Define a factory in an application service provider's `boot()` method so it is available in both environments:

```php class-body
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\TableBuilder;
use Wtroiano\TableBuilder\TableRegistry;

public function boot(): void
{
    TableRegistry::define('users', static fn (): TableBuilder => TableBuilder::make('users')
        ->resource(User::class)
        ->authorize(fn (Request $request): bool => $request->user()?->can('viewAny', User::class) ?? false)
        ->columns([
            TextColumn::make('name')->sortable()->searchable(),
            TextColumn::make('email'),
        ])
        ->actions([
            Action::make('Deactivate')->key('deactivate')
                ->handle(function (User $user): void {
                    Gate::authorize('update', $user);
                    $user->update(['active' => false]);
                }),
        ])
        ->exports([Export::make('Users')->filename('users.xlsx')->key('users')->limitToFilteredRows()])
        ->defaultSort('name')
        ->perPage(15));
}
```

Render it from the controller:

```php
$table = TableRegistry::get('users');
abort_unless($table->authorize($request), 403);
return Inertia::render('Users/Index', ['table' => $table]);
```

Factories may return a `TableBuilder` or a built `Table`; container injection is supported for factory parameters. Every `get()` invokes the factory afresh. Registry keys must start with a letter or digit and contain only letters, digits, dots, underscores, or hyphens; duplicate definitions throw. `has()` and `forget()` support lookup and removal. The registry key identifies the factory; the builder's name scopes query state and may differ from that key.

Clients should pass `meta.table` unchanged to the action/export endpoint. Registered tables use references such as `registry:users`; class tables retain their class references. For example:

```json
{"table":"registry:users","action":"deactivate","selectionMode":"page","keys":[1,2]}
```

`resource()` accepts a model class, an Eloquent builder, or a lazy callback returning either. `columns()`, `actions()`, and `exports()` also accept lazy callbacks receiving the table. For tenant-dependent queued exports, resolve context inside these callbacks instead of capturing a request or live scoped query in the factory; see [export context hooks](exports.md#queued-tenant-context). Definitions are application code and are recreated by the registered factory, rather than serialized into jobs.

## Builder configuration

| Concern | Fluent API | Class-table equivalent |
| --- | --- | --- |
| Query source | `resource()` | `$resource`, `resource()`, or `query()` |
| Definitions | `columns()`, `actions()`, `exports()` | Methods of the same names |
| Policy | `authorize(bool or callable)` | `authorize(Request): bool` |
| Defaults | `defaultSort()`, `defaultState()` | `$defaultSort`, `defaultState()` |
| Pagination | `perPage()`, `perPageOptions()`, `pagination()` | `$defaultPerPage`, `$perPageOptions`, `$pagination`, `$paginationType` |
| Layout | `stickyHeader()`, `scrollToTop()`, `emptyState()` | Matching protected properties |
| Selection | `rowSelectionKey()`, `selectable()`, `persistRowSelectionAcrossPages()` | Matching protected properties |
| Additional search fields | `searchable(['name'])` | Protected `$search` |
| Row eligibility | `selectableUsing(fn (Model): bool => ...)` | `isSelectable(Model): bool` |
| Row navigation | `rowUrl(fn (Model, Url): string or Url or null => ...)` | `rowUrl(Model, Url)` |
| Row transformation | `transformModel(fn (Model, array): array => ...)` | `transformModel(Model, array): array` |
| Search pipeline hook | `withQueryBuilder()` | `withQueryBuilder(TableQueryBuilder)` |
| Worker context | `exportContext(capture: ..., restore: ...)` | `exportContext()`, `withExportContext()` |

The callback signatures are illustrated in the relevant guides; the pseudo-signatures in this table describe return shapes. Returning null from `withQueryBuilder()` keeps the original query builder. `perPage()` must be positive and present in the configured page-size options; include custom sizes with `perPageOptions()`.

`TableBuilder` is mutable while configuring a definition. `build()` creates an independent configuration snapshot. Resource builders and array-based definitions are cloned; callbacks still retain any values they capture. Registry resolution returns fresh tables, so factories should resolve request/tenant-sensitive state lazily and avoid caching live table instances.

## Query methods and payloads

| Method | Result |
| --- | --- |
| `query()` | A cloned resource builder or new model query; no client search/sort applied |
| `apply(request: ...)` | Eloquent query with declared search/sort behavior |
| `paginate(request: ...)` | Paginator or collection through the table's pagination strategy |
| `results(request: ...)` | Full payload with records and metadata |
| `payload(request: ...)` | Definitions/state metadata without fetching records |
| `toArray()` / JSON serialization | Full results using the current HTTP query string |
| `navigation(TableRequest)` | A navigation helper returning normalized query arrays |

`results()` without a request uses defaults; pass a `TableRequest` for an explicit query. Automatic Inertia serialization reads the current request. Always call the table's page authorization before rendering or invoking these query methods in a page controller. The action/export endpoints perform their own authorization.

## Generator and configuration

The command creates class tables under `app/Tables`. Configure `table-builder.tables_path` or use `--path`:

```sh
php artisan make:table-builder Admin/UsersTable --model=User --path=app/Admin/Tables
```

Paths outside `app` require a Composer PSR-4 mapping. The supported configuration keys are:

| Key | Default |
| --- | --- |
| `table-builder.middleware` | `['web', 'auth']` |
| `table-builder.pagination.default_per_page` | `15` |
| `table-builder.pagination.per_page_options` | `[15, 30, 50, 100]` |
| `table-builder.tables_path` | `app/Tables` |

Refresh the application's route cache after changing endpoint middleware. See [actions](actions.md), [exports](exports.md), and [query state](state-and-pagination.md) for behavior at those boundaries.
