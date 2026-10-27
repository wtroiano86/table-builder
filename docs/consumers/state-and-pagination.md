# Search, state, and pagination

The React view manages these controls automatically. Use this contract when linking to tables or writing a custom renderer.

Table query parameters are `page`, `perPage`, `cursor`, `search`, `sort`, `direction`, `columns`, and `sticky`. Only declared searchable/sortable fields participate. Search uses the database's `LIKE` semantics and collation; PostgreSQL's default search is case-sensitive. `searchUsing()` and `sortUsing()` on columns provide custom query callbacks. Explicit sorting replaces resource ordering. Relation fields such as `manager.name` support searching and sorting; collection sorts use the minimum non-null value ascending and maximum descending. Ordered `HasOne` relations keep their chosen row, including null values, before table sorting. SQL-qualified fields such as `users.name` resolve their hydrated model attribute in rendered rows and exports; model relationship and nested attribute paths retain their normal meaning.

Use `UsersTable::make()->named('users')` when rendering several tables; its query parameters become `users[search]`, `users[page]`, and so on. Paginator links retain the current request's other tables and page controls while replacing this table's parameters with normalized state. `navigation($request)->search('Ada')`, `->perPage(30)`, `->sort('name', 'desc')`, and `->page(2)` produce query arrays for the current table. Pass a `TableRequest` wrapper to `navigation()`. Preserve generated empty scalar values: they reset defaults during URL round trips.

Configure pagination with protected `$pagination`, `$paginationType` (`PaginationType::Standard`, `Simple`, or `Cursor`), `$defaultPerPage`, and `$perPageOptions`. Cursor pagination supports null values and deterministic primary-key tie-breakers. Raw sort expressions must be selected under a named alias first.

## Defaults and column visibility

`defaultSort('-name')` means descending name order. A table's `defaultState()` can set search, sort, direction, page size, hidden columns and sticky columns. For example:

```php
use App\Models\User;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\TableBuilder;

$table = TableBuilder::make('users')->resource(User::class)
    ->columns([TextColumn::make('name')->sortable()->searchable(), TextColumn::make('email')])
    ->perPage(30)->perPageOptions([15, 30, 50])
    ->defaultState(['search' => 'Ada', 'sort' => 'name', 'direction' => 'desc', 'columns' => ['email'], 'sticky' => []])
    ->build();
```

State input uses `columns` as a list of **hidden keys**. Rendered `state.columns` is instead a map of key to visibility, such as `{"name":true,"email":false}`. Columns configured with `visible(false)` seed the initial hidden list unless an explicit default state supplies it. An explicit empty override reveals the default-hidden columns. A column key selects the serialized column, including one configured with `key()`.

Empty scalars are meaningful resets: `users[search]=`, `users[sort]=`, `users[columns]=`, `users[sticky]=`, and `users[cursor]=`. Empty arrays disappear from ordinary URL encoding. Keep the helper's generated empty values so clearing a control does not reinstate its default on the next request.

## Fluent pagination

```php
use Wtroiano\TableBuilder\Enums\PaginationType;
use Wtroiano\TableBuilder\TableBuilder;

$builder = TableBuilder::make('users')
    ->pagination(type: PaginationType::Cursor)
    ->perPage(30)->perPageOptions([15, 30, 50])
    ->scrollToTop(false);
```

| PHP mode | Rendered `paginationType` | Navigation |
| --- | --- | --- |
| `PaginationType::Standard` | `full` | Total count, page position and previous/next links |
| `PaginationType::Simple` | `simple` | Page position and previous/next links without a total count |
| `PaginationType::Cursor` | `cursor` | Opaque previous/next URLs; no page count |
| `pagination(false)` | Pagination disabled | `results` is a row array |

Search, sort and page-size changes restart pagination. Follow the server's previous/next URLs, especially for cursors, instead of constructing cursor tokens. Paginator links retain other tables and page-level query parameters. Offset/limit constraints in the base resource remain part of the eligible cursor dataset.

## Server-generated links

```php
use Wtroiano\TableBuilder\Http\Requests\TableRequest;

$query = $table->navigation(TableRequest::fromRequest($request))->sort('name', 'desc');
$url = $request->url().'?'.http_build_query($query);
```

Each navigation operation returns a query array, not another fluent navigation object. The application can combine it with unrelated page parameters as needed. For explicit rendering, use `$table->results(request: TableRequest::fromRequest($request))`.
