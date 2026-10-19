# Architecture and namespaces

These notes describe Table Builder's internals and PHP namespaces. For the public contract, start with the [README](../README.md).

Source organization:

| Directory | Responsibility |
| --- | --- |
| `Actions/` | Action definitions and configuration/execution concerns |
| `Columns/` | Column definitions |
| `Concerns/` | Shared compatibility behavior |
| `Exports/` | Export definitions, concerns, and Excel integration |
| `Http/Controllers/`, `Http/Requests/` | HTTP entry points and request wrappers |
| `Query/` | Search, sorting, relation resolution, and query metadata |
| `Pipelines/` | Table/export queries, export execution, action preparation/execution, and row serialization |
| `Pagination/` | Pagination configuration and execution |
| `Tables/` | Table state, navigation, empty state, payload construction, fluent configuration, and reference resolution |
| `Support/` | Image and URL value builders and callback invocation |
| `Commands/`, `Contracts/`, `Enums/`, `Exceptions/`, `Providers/` | Framework integration and shared contracts/types |

`Table.php`, `TableBuilder.php`, and `TableRegistry.php` are the public entry points directly in `src/`.

The React view is shipped as source in `resources/react/`. The provider's `table-builder-react` publish tag copies it to `resources/js/vendor/table-builder`; `table-builder-react-page` publishes an optional resolver page. `Table` consumes the serialized backend contract, `TablePage` adds page structure and an Inertia head title, and cell/link/dialog helpers retain native semantics. Scoped CSS uses theme variables. The application supplies React, Inertia, Vite, routing and authorization.

Query visits use the Inertia router; command requests and downloads use same-origin JSON fetch with Laravel CSRF headers. Navigation retains other query namespaces and sends explicit empty values to clear defaults. Visibility travels as hidden column keys, while rendered `state.columns` is a visibility map. Column `visible(false)` values seed default hidden keys; explicit state can reveal them. Selection uses raw keys and server eligibility, and the supplied UI executes page selections, optionally retained across pages. Browser tests use the real Inertia client and a test-only contract server; PHP tests verify the shared fixture matches actual serialization. Frontend development manifests, dependencies and test harnesses are excluded from Composer archives.

`TableBuilder` snapshots configuration into the internal `Tables/ConfiguredTable` adapter, which uses the same `Table` pipelines as consumer subclasses. Source queries and array-based column/action/export definitions are cloned when building; lazy callbacks resolve during use. Inline configured tables omit endpoint references and reject executable actions or exports until registered.

`TableRegistry` delegates to an application-container singleton holding factory definitions, never table instances. Each lookup invokes the factory through the container, accepts a builder or table, and returns a cloned table carrying a `registry:<key>` reference. Duplicate keys, invalid keys, missing definitions, invalid factory returns, and circular resolution fail explicitly. The registry key and table query namespace are independent. Define factories during application boot so HTTP requests and workers have the same definitions; defer context-sensitive queries and definitions to lazy callbacks.

`Tables/TableResolver` handles both legacy table class names and registry references for action preparation, export execution, and spreadsheet reconstruction. Endpoint pipelines authorize the resolved table before querying. Direct references to the internal configured adapter are rejected. Queued exports serialize the stable reference in the existing `tableClass` field, preserving legacy payloads without serializing factory closures or queries.

PHP classes use the `Wtroiano\TableBuilder` namespace with subnamespaces matching their directories:

| Classes | Namespace |
| --- | --- |
| `Action` | `Wtroiano\TableBuilder\Actions` |
| `Export` | `Wtroiano\TableBuilder\Exports` |
| `TableRequest`, `ExportRequest` | `Wtroiano\TableBuilder\Http\Requests` |
| `TableSearchBuilder`, `TableSortBuilder`, `TableQueryParameters`, `TableQueryMetadata`, `TableQueryMetadataResolver`, `ResolvedTableQueryColumn`, `RelationFieldResolver`, `SearchTermParser` | `Wtroiano\TableBuilder\Query` |
| `TableQueryBuilder` | `Wtroiano\TableBuilder\Pipelines\TableQuery` |
| `PaginationConfiguration`, `TablePaginationBuilder` | `Wtroiano\TableBuilder\Pagination` |
| `TableState`, `TableNavigation`, `TablePayloadBuilder`, `TableEmptyState` | `Wtroiano\TableBuilder\Tables` |
| `Image`, `Url` | `Wtroiano\TableBuilder\Support` |

`Table`, `TableBuilder`, and `TableRegistry` live directly under `Wtroiano\TableBuilder`. Column classes use `Wtroiano\TableBuilder\Columns`, enums use `Wtroiano\TableBuilder\Enums`, and the state contract is `Wtroiano\TableBuilder\Contracts\TableState`.

The package uses Laravel pipelines with typed context objects:

- `Pipelines/TableQuery/TableQueryBuilder`: `ApplySearch` → `ApplySort`, carrying `TableQueryContext` with the query, metadata, normalized parameters, and optional search callback.
- `Pipelines/ExportQuery/ExportQueryBuilder`: `ApplyTableFilters` → `RestrictToSelectedRows` → `EnsureDeterministicOrder`, carrying `ExportQueryContext` with the query, table, export definition, request state, and selected keys.

- `Pipelines/RowSerialization/RowSerializationPipeline`: `ResolveIdentity` → `ResolveColumns` → `AttachSelectionAndActions` → `TransformRow`, carrying `RowContext` with the table, record, and accumulated row. Column values, URLs, and images resolve together in one pass. Model transforms run last; non-model records retain their existing selection/action behavior. Already serialized array results pass through unchanged.
- `Pipelines/ActionChunk/ActionChunkPipeline`: `RunBeforeHook` → `ExecuteEligibleModels` → `RunAfterHook`, carrying `ActionChunkContext`. Globally unauthorized actions return 403 before querying or invoking hooks. Authorized action hooks receive the full selection for explicitly selected records and each database chunk for select-all requests, including skipped records. After hooks receive results from their own invocation. A shared `ActionExecutionResult` retains aggregate results and counts, including partial progress on exceptions. Exceptions propagate to the controller's existing JSON/Inertia error handling; after hooks run only when execution succeeds.

- `Pipelines/ExportExecution/ExportExecutionPipeline`: `ResolveExport` → `AuthorizeExport` → `PrepareQuery` → `TryCustomExporter` → `DispatchExport` → `BuildResponse`, carrying `ExportExecutionContext`. A non-null custom result ends the pipeline, including `false`, `0`, and an empty string. Null continues to built-in dispatch. Dispatch selects queue, download, or store; downloads retain their response and queued exports and successfully stored exports receive the existing JSON/Inertia processing response. A false storage result is reported and returns JSON status 500 with an error payload, or an Inertia error redirect without a success dialog. Queue hooks and export dialogs remain supported.
- `Pipelines/ActionPreparation/ActionPreparationPipeline`: `ResolveTable` → `ResolveAction` → `ValidateBulkSelection` → `ResolveSelection`, carrying `ActionRequestContext`. Validation uses the raw requested key count before empty keys are removed. Selected records resolve using the configured selection key. Select-all preparation never creates a record query; the controller continues to stream database chunks. Preparation remains outside the action execution error handler, preserving validation statuses and selection-query error behavior.

The export controller and Excel exports use the same preparation service. Synchronous exports reuse the prepared query, and each Excel query call receives a clone so chunking or caller changes cannot alter the cached definition. A custom exporter returning null retains its query changes for synchronous built-in dispatch. Built-in queued exports skip redundant package preparation; query builders are discarded during serialization and reconstructed from the serialized inputs. Queued exports rebuild the query after deserialization, using the original table class or registry reference, explicit table name, export key, request state, and selected keys. Named tables are restored before their state is decoded, so filters remain scoped to the correct table. Legacy unnamed serialized exports remain readable. Each invocation creates its own pipeline and context; no query state is stored in the shared builder services. An empty selection produces no rows when selected-row restriction is enabled. Pagination retains its existing strategy selection.

State conversion and navigation share `Tables/TableStateSerializer`. The existing six-field `TableState::toArray()` transport shape is unchanged; the internal serializer also carries hidden columns and explicit sticky overrides, including an empty sticky list.

URL state is compared with the table's actual default state. Explicit resets use empty scalar query values (`search=`, `sort=`, `columns=`, `sticky=`), since URL encoding drops empty arrays. This preserves cleared settings and page-size overrides through navigation and pagination links, including named tables. Consumers building URLs should pass the generated query values through unchanged.

Payload rendering resolves column/action/export definitions once per render and authorizes exports once. The snapshot is released when rendering completes or throws; it is never cached across renders or copied into cloned tables.

Serialized column definitions are also cached inside that render snapshot, so labels run once and metadata, headers, visibility, and sticky settings share the same values. Paginator metadata is read without converting the underlying models to arrays; only the table row pipeline serializes each record, avoiding unrelated appended accessors and loaded relations. The rendered state includes the sort direction. Pagination links retain unrelated request parameters, replace the current table's state, and discard stale current-table pagination parameters.

`results()` shares that snapshot across query preparation and payload serialization. Search and column-toggle capability flags are derived from the actual definitions.

Relation sorting uses constrained subqueries for `BelongsTo` and `HasOneOrMany` relationships, including nested paths. Each parent appears once; ascending collection sorts use the lowest non-null matching value, and descending sorts use the highest. Relation constraints and related-model global scopes apply. Parents without a matching value use the database's normal null ordering. Qualified SQL columns such as `users.name` and existing join aliases are recognized separately from relationship paths. Selection restrictions qualify the configured key against the base model. `Query/QuerySource` resolves the base query source and alias consistently for search, sorting, selection restrictions, ordering, and cursor pagination. Correlated relation queries use a separate model clone; hydrated records keep their real table name for updates and deletes.

Cursor pagination uses `Pagination/TableCursorPaginationBuilder`: computed sort values are selected under aliases and filtered through a derived query, preserving subquery bindings. A primary-key tie-breaker makes ordering unique, and null values always sort last in both ascending and descending cursor pages. Forward and backward navigation preserve the same ordering. The outer cursor query clones the incoming Eloquent builder, retaining `afterQuery()` callbacks and eager loads. Partial projections receive the actual primary-key field for model identity as well as the cursor sort values. Internal `_inertia_` aliases are reserved and omitted from table rows. Custom raw sort expressions must be selected under an alias and ordered by that alias. Unrecognized cursor formats and malformed parameters restart at the first page; existing cursor URLs should be regenerated after this change.

The raw `_selection_key` transport field carries selection identity independently of formatted columns. The configured `id` database selection field aliases the model's primary key, even when the database column is named `uuid` or another name. `Query/SelectionKeyResolver` applies that mapping consistently to rows, actions, and selected exports. Explicit custom selection keys retain their configured mapping.

Resource limits and offsets run inside the cursor subquery with the original ordering and a primary-key tie-breaker, so cursor navigation stays within that dataset. Export query preparation applies global scopes once before adding its primary-key tie-breaker; synchronous and reconstructed exports retain scoped ordering and constraints. Action handler validation exceptions pass through to Laravel, preserving JSON field errors and Inertia validation redirects.

Select-all action execution applies scopes once and captures eligible primary keys for resource queries with limits or offsets before invoking handlers. Key capture reads database chunks without hydrating models or running Eloquent callbacks; the captured keys stay in memory while records are processed. Unrestricted queries retain their existing streaming behavior. Column values resolve SQL table qualifiers and aliases against hydrated model attributes without replacing real relationship or nested attribute paths. Singular relation sorting retains the relation's ordering and bindings, qualifies self-relation ordering against the correlated alias, and includes the chosen row even when its value or nested relation is null. Collection sorting retains its minimum/maximum non-null behavior.

Action identifiers are validated by `Http/Requests/ActionRequest` before table or action resolution. Array, boolean, and numeric identifiers return 422. Action requests accept `selectionMode` values `page` (the default) and `all`, plus an array of integer or string `keys`. Null and empty-string keys remain accepted for compatibility and are removed after the bulk-selection count check. Malformed modes and key types return 422. Unexpected execution failures are reported through Laravel and return `Action failed.` by default for both JSON and Inertia responses. Custom error callbacks still receive the original exception and can supply a user-facing message; HTTP exceptions retain their status.

ID-based select-all actions apply root scopes once, retain their filters, and traverse records in ascending primary-key order. Existing display or scope ordering is removed before chunking, preventing skipped rows. Table aliases and partial projections are supported; hydrated models retain their physical table for handlers that update or delete them. The explicit `chunk` strategy retains offset-based behavior. Explicit user sorting replaces resource ordering; exports and cursor pagination retain primary-key tie-breakers.

Use `Action::make('Rebuild')->asBulkAction()->collectResults(false)` for large select-all operations that do not need aggregate results. Counts remain accurate and the response contains an empty `results` array. After hooks still receive results for the current chunk, so memory for package-managed results is bounded by the chunk size. Result collection stays enabled by default for compatibility.

Export redirects accept callable arrays as well as closures; route configuration arrays keep their existing meaning.

Search, sort, and export callbacks share `Support/Callback`: fixed-argument callbacks receive the supported prefix of arguments, variadic callbacks receive every argument, and invocation preserves strict scalar checks.

Column export metadata is derived from the actual configuration. `export(['enabled' => false, 'format' => '0.00', 'style' => [...]])` now controls Excel behavior as well as metadata. Fluent setters remain available and override earlier configuration. Cleared formats/styles are omitted from metadata; `enabled` and `custom` are derived flags. Known option types are validated. Callable-array export styles are normalized into closures alongside other callable forms. `toggleable(true)` can re-enable a column, and serial-number columns remain non-toggleable by default.

Use `make()` and fluent setters for new code. Existing `create()` calls remain available through a shared deprecated alias, including named arguments and strict parameter checks. `TableEmptyState::create()` keeps its legacy null-title default. `Query/TableSearchBuilder` and `Query/TableSortBuilder` are deprecated compatibility adapters; query execution uses the search/sort pipes directly. Resolve the pipeline through Laravel's container rather than constructing its dependencies manually.


Queued exports also serialize the trusted context returned by `Table::exportContext()`. `withExportContext()` scopes Excel callbacks and queue middleware; root global scopes are materialized before returning a query. Applications must capture their actor/tenant identity explicitly and restore it with cleanup. See the [export context examples](consumers/exports.md#queued-tenant-context).
