# HTTP and payload contract

Use the supplied React view unless the application needs a custom renderer. Its public TypeScript shapes are in [types.ts](../../resources/react/types.ts); the PHP table and endpoint behavior described here is the server contract.

## Page requests and payloads

The application page controller authorizes the table and returns it as an Inertia prop. Serialization reads the current query string. A named table such as `users` reads `users[search]`, `users[sort]`, `users[direction]`, `users[perPage]`, `users[page]`, `users[cursor]`, `users[columns]` and `users[sticky]`.

| Payload field | Meaning |
| --- | --- |
| `name` | Table query namespace/identifier |
| `results` | Paginator object with `data`, or a row array when pagination is disabled |
| `columns` | Column definitions for a renderer: attribute, header, type, visibility and metadata |
| `state` | Current search, sort, direction, page size, column visibility map and sticky keys |
| `meta.queryString` | Actual query parameter names and namespace |
| `meta.table` | Opaque reference for commands: a class name or `registry:<key>` |
| `meta.actionEndpoint`, `meta.exportEndpoint` | Available command endpoints |
| `actions` | Bulk action definitions; row actions are in each row's `_actions` |
| `exports` | Authorized export definitions |
| `rowSelectionKey` | Field containing each row's raw selection key, normally `_selection_key` |
| `selectable`, `persistRowSelectionAcrossPages` | Selection behavior |
| `pagination`, `paginationType`, `perPageOptions` | Pagination configuration |
| `emptyState`, `stickyHeader` and capability flags | Presentation controls |

The pagination type is `full`, `simple` or `cursor`. A full paginator includes totals and page positions; a simple paginator has no total; cursor navigation uses opaque server-provided URLs. Preserve those links and unrelated URL parameters. [State and pagination](state-and-pagination.md) details defaults and explicit resets.

Rows contain `_primary_key`, optional `_selection_key`, `_selectable`, `_actions`, `_column_urls`, `_column_images`, optional `_url`, and the configured column values. Transform callbacks can add fields. `_column_urls` and `_column_images` are maps; PHP may serialize an empty map as `[]`. Treat both empty maps and empty arrays as having no entries.

Inline fluent tables omit executable endpoint references and reject executable actions/exports until registered. Pass `meta.table` unchanged; do not derive it from a React component name or table query namespace.

## Authentication and transport

Both command routes use the configured middleware, by default `web` and `auth`, and authorize the resolved table before querying. The application owns page authorization separately. Browser requests need the application's session cookie and CSRF protection.

Use ordinary JSON HTTP requests with `Accept: application/json` for command responses and download handling. An `X-Inertia` request expects a page/redirect response; do not send it when expecting JSON or a binary workbook. The packaged client sends JSON with same-origin credentials and a fresh Laravel XSRF header. A deliberately custom controller/export response may define its own shape.

## Actions

`POST /table-builder/actions`, named `table-builder.actions.execute`:

```json
{
  "table": "registry:users",
  "action": "deactivate",
  "selectionMode": "page",
  "keys": [1, "user-uuid"]
}
```

| Field | Contract |
| --- | --- |
| `table` | Required string identifying a registered factory or concrete table class |
| `action` | Action key; unknown or missing action definitions return 404 |
| `selectionMode` | `page` (default) or `all` |
| `keys` | Array of integer/string selection keys for page mode |

The server validates key types and bulk limits before discarding legacy null/empty-string entries. Use raw keys from `row[table.rowSelectionKey]`, including custom selection columns. Client-side eligibility is a display aid; server handlers still enforce application policy.

`all` runs over the base resource query and ignores client search state. The packaged React view sends page mode, optionally retaining explicit keys across pages. [Actions](actions.md) explains chunking and mutation behavior.

The default JSON success body is:

```json
{
  "ok": true,
  "status": "success",
  "action": "deactivate",
  "processed": 1,
  "skipped": 0,
  "results": []
}
```

Handler return values populate `results` unless result collection is disabled. `success()`/`error()` callbacks may replace the response body. Default Inertia success responses redirect back with `table_action` session data; the host application decides how to share session data with its page.

## Exports

`POST /table-builder/exports`, named `table-builder.exports.execute`:

```json
{
  "table": "registry:users",
  "export": "users",
  "state": {
    "search": "Ada",
    "sort": "name",
    "direction": "asc",
    "perPage": 15,
    "columns": ["email"],
    "sticky": []
  },
  "keys": [1],
  "data": {}
}
```

`state` is the table's unwrapped state even for a named table: send `state.search`, not `state.users.search`. Its `columns` list contains hidden keys, unlike the page payload's visibility map. `keys` restrict rows only when `limitToSelectedRows()` is enabled. The explicit `data` object is available to custom exporters through `ExportRequest::data()`; export/action definition `data` metadata does not itself change built-in query behavior.

Synchronous downloads return a file response with its content type and `Content-Disposition` filename. Successful queued or stored exports return this default JSON body:

```json
{
  "ok": true,
  "status": "processing",
  "export": "users",
  "message": "Export is being processed."
}
```

A custom exporter returning any non-null value ends built-in dispatch. Null continues to built-in export execution. Inertia non-download responses use the export's redirect and `table_export` session data; configured dialogs use `table_export_dialog`. See [Exports](exports.md) for selection, callbacks and queue context.

## Errors

| Status | Typical cause |
| --- | --- |
| 401 | JSON request is unauthenticated under the configured auth middleware |
| 403 | Table/action/export authorization denied |
| 404 | Unknown action/export or an empty action selection |
| 419 | Application CSRF/session expiry |
| 422 | Invalid action input/reference, malformed selection or handler validation failure |
| 500 | Unexpected action execution failure or failed built-in export storage |

Handler authorization exceptions retain the application's denial response, including custom statuses such as 404. Handler validation exceptions retain Laravel's JSON field errors or Inertia validation redirect. Unexpected default action errors include `ok: false`, `status: "error"`, `message: "Action failed."`, and partial processed/skipped counts. Execution is not automatically transactional. Storage failures use `Export could not be stored.`; other application failures follow Laravel's exception handling.

For recovery, show field errors, preserve the server's counts and let the user assess partial mutations before retrying. The [troubleshooting guide](troubleshooting.md) maps common symptoms to configuration and contract checks.
