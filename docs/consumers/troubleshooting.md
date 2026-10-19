# Troubleshooting

## The page cannot be resolved

Confirm both React publish tags ran in the consuming application, the application compiles `resources/js/vendor/table-builder`, and its page resolver sees `pages/vendor/table-builder.tsx`. Move the page into `Pages/vendor/` if the resolver uses uppercase `Pages`. Rebuild Vite assets after changing published files. A consuming application needs React/Inertia configured before using the page.

## A table renders but commands return 401 or 403

The page route and the package's endpoints have separate access checks. Inspect `table-builder.middleware`, the application's guard, table `authorize()`, and action/export authorization. Refresh the route cache after changing middleware. Guest access requires both middleware and table policy to permit it. UI visibility does not authorize a request.

## Inline actions or exports ask for registration

Define a `TableRegistry` factory in an application service provider's `boot()` method and render `TableRegistry::get('key')`. Defining it only inside a controller does not make it available to the next command request or worker. See [registered tables](tables.md#registered-fluent-tables).

## Search or sorting does not apply

Declare searchable/sortable fields, use the column's serialized key, and use the table's query namespace. For `TableBuilder::make('users')`, send `users[search]`, not a top-level `search`. A manual `results()` call uses defaults unless a `TableRequest` is passed; automatic serialization reads the current request. Search case sensitivity follows database collation unless a callback supplies custom behavior.

## A custom page size is rejected

Page sizes must be positive and belong to the configured options. For example, use `perPage(20)->perPageOptions([20, 50, 100])`. Client sizes outside the options are normalized by the pagination configuration.

## A cleared setting comes back

Preserve empty scalar resets such as `users[search]=` and `users[columns]=`. Encoding an empty array omits the parameter and allows defaults to return. Input `columns` is a hidden-key list; rendered `state.columns` is a visibility map. See [defaults and visibility](state-and-pagination.md#defaults-and-column-visibility).

## Selected records do not match the displayed ID

Use the raw field named by `rowSelectionKey`, normally `_selection_key`. `mapAs()` may format an ID column independently. Preserve `_primary_key` and `_selection_key` in model transforms. The React view accepts string keys and safe integer keys; represent larger identifiers as strings. A selected-row export with no keys produces no rows.

## A queued export loses tenant data or callbacks

Register the factory during application boot, use lazy resource/definition callbacks, and configure both export context hooks. Capture trusted scalar/array context and restore it with cleanup around the callback. Workers must have the package, table definition, disk configuration and application context services. They do not inherit the HTTP request or authenticated user. See [queued tenant context](exports.md#queued-tenant-context).

## A download opens an Inertia error modal

Fetch downloads through ordinary HTTP and handle the file response. `router.post()` sends an Inertia request intended for page/redirect responses. The supplied table handles JSON commands and binary downloads separately. Custom exporters used with that view should return JSON or a file response.

## A bulk action stopped partway through

Inspect the processed/skipped counts and application exception report. Earlier mutations are retained; the package does not wrap the full selection in a transaction or automatically roll back. Default select-all uses primary-key chunks. The optional offset `chunk` strategy needs application-specific care when handlers change or delete the dataset.

## Local package tests cannot run

Consumer applications should run their own integration tests. Package contributors need PHP 8.5, installed Composer dependencies, the locked npm dependencies and an Xdebug/PCOV coverage driver for coverage checks. See [contributor testing](../development/testing.md).
