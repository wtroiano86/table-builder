---
name: table-builder-development
description: Build and integrate PHP tables using wtroiano/table-builder in a Laravel application. Use when creating fluent builders or table subclasses, configuring columns, scoped Eloquent resources, registry factories, authorization, search, sorting or pagination.
---

# Build a Table Builder

Work in the consuming application's conventions. Read `getting-started.md`, `tables.md`, `columns.md` and `state-and-pagination.md` in `vendor/wtroiano/table-builder/docs/consumers/`, relative to the application root, as needed. If Composer uses a custom vendor directory, resolve that directory first. Inspect the installed source for any unfamiliar callback or method.

## Choose the definition

Use `Wtroiano\TableBuilder\TableBuilder` for inline fluent configuration; extend `Wtroiano\TableBuilder\Table` when the app wants a named class. Both paths share query/render behavior. Use application models and policies that exist; identify missing prerequisites explicitly.

```php
use App\Models\User;
use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\TableBuilder;

$table = TableBuilder::make('users')
    ->resource(User::class)
    ->authorize(fn (Request $request): bool => $request->user()?->can('viewAny', User::class) ?? false)
    ->columns([TextColumn::make('name')->sortable()->searchable()])
    ->defaultSort('name')
    ->build();
```

The name scopes query parameters; it is not a registry reference. `build()` snapshots configuration. Use a fresh resource query and eager-load relationships needed by callbacks. A model class, Eloquent builder or lazy callback can supply the resource. Additional query constraints belong in the resource, including tenant scope. Do not rely on URL search to constrain access.

## Reconstruct executable tables

For actions or exports, register `TableRegistry::define('users', factory)` in an application provider's `boot()`. The factory returns a builder or table and must work in HTTP requests and queue workers. Resolve with `TableRegistry::get('users')`; every lookup invokes the factory afresh. Avoid capturing a live Request, authenticated user or scoped query in a long-lived factory. Use lazy resources and lazy `columns()`, `actions()`, `exports()` callbacks when context matters. Pass `meta.table` unchanged to commands; registry references look like `registry:users`.

Use the actions/exports skill for command policies, handlers and worker context. Inline executable definitions fail because later requests cannot reconstruct them.

## Columns, state and rendering

- Pass only the field name to column factories and configure options through fluent methods. Read the installed `docs/consumers/upgrading.md` for v1 migrations.
- Import column types from `Wtroiano\TableBuilder\Columns`. Mark search/sort fields explicitly. Use `key()` to disambiguate duplicate attributes; use the serialized key in state.
- Keep `_primary_key` and `_selection_key` intact in row transforms. Read selection from `row[table.rowSelectionKey]`. Rendered labels and mapped IDs are not identity.
- `visible(false)` sets an initial hidden state; users can reveal it and values remain serialized. Omit sensitive columns when unauthorized.
- Input `columns` is a list of hidden keys; payload `state.columns` is a visibility map. Preserve empty scalar resets in URLs. Each navigation operation returns a query array, not a chainable helper.
- `perPage()` must be positive and included in `perPageOptions()`. Use `PaginationType::Standard`, `Simple` or `Cursor`; preserve server-generated cursor links.
- Authorize the table before page serialization and protect the app page route. Return `Inertia::render('vendor/table-builder', ['table' => $table])` only after publishing the supplied page, or embed `Table` in an existing page.

## Validate the integration

Use the application's tests for the allowed/denied page, scoped dataset, declared search/sort and default/reset state. Include a relationship or cursor case when the requested behavior depends on it. Check the resulting serialized payload and rendered view. Report checks actually run; syntax validation alone does not prove application policies or database behavior.
