# Table Builder

Use `Wtroiano\TableBuilder\TableBuilder` for fluent definitions or extend `Wtroiano\TableBuilder\Table`. Columns live in `Wtroiano\TableBuilder\Columns`; actions and exports use `Actions\Action` and `Exports\Export`. Read the installed package documentation at `vendor/wtroiano/table-builder/docs/README.md` from the application root before inventing an API.

Factories accept only the identity: a column/action name or an optional export label. Set all other options fluently, such as `TextColumn::make('name')->sortable()` and `Export::make('Users')->filename('users.xlsx')`. For v1 migrations, read `docs/consumers/upgrading.md` in the installed package.

- Activate `table-builder-development` for PHP tables, columns, scoped queries, URL state and registry factories.
- Activate `table-builder-react` for the packaged React component/page, customization, transport and themes.
- Activate `table-builder-actions-exports` for executable actions, downloads, selected/filtered exports and queue context.

Inline builders support rendering. Executable actions and exports require a reconstructible table: register a fresh factory with `TableRegistry::define()` in a provider's `boot()` or use a concrete table subclass. Pass the payload's `meta.table` reference unchanged. Resolve request/tenant-sensitive resources and definitions lazily; queue workers need trusted context capture/restoration with cleanup.

The application owns models, policies, page routes and authentication. Protect the page route and explicitly check `$table->authorize($request)` before rendering. Command endpoints default to `web` and `auth` middleware and check table authorization; handlers must enforce record policies. Visibility is presentation: hidden column values remain in the payload.

Publish `table-builder-react` and optionally `table-builder-react-page`; the application compiles and owns these source copies. The supplied page resolves as `vendor/table-builder` under `resources/js/pages`, with a move needed for uppercase `Pages` resolvers. No separate npm table package is required.

Named tables scope query state, such as `users[search]`. Preserve empty reset values and server paginator URLs. Read raw selection keys via `row[table.rowSelectionKey]`, never formatted cells. Command requests use ordinary same-origin HTTP with CSRF protection; binary exports are not Inertia visits. Client `all` actions mean the base resource query, not the current search.

Follow application instructions and existing conventions. Verify changes at the affected boundary: policies and tenant scopes, normalized state, raw identity, React interaction or worker reconstruction. Use the installed version's local source and docs for package-specific behavior.
