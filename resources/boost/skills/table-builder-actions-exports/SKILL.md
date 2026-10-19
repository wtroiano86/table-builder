---
name: table-builder-actions-exports
description: Implement policy-checked actions and exports for wtroiano/table-builder. Use for row or bulk handlers, selection and chunking, HTTP command responses, spreadsheet safety, filtered/selected downloads, custom exporters or tenant-aware queued export context.
---

# Add actions and exports

Read `actions.md`, `exports.md` and `http-contract.md` in `vendor/wtroiano/table-builder/docs/consumers/`, relative to the application root. Confirm installed callback signatures in source. For tenant-aware workers, read [the context recipe](references/queued-context.md) before implementing a queue.

## Establish reconstruction and access

Use a concrete `Table` subclass or a fresh `TableRegistry` factory registered during provider boot. Inline rendering tables cannot expose executable commands. Resolve request/tenant-sensitive resources and definitions lazily, and pass `meta.table` unchanged. Enforce resource scoping independently of client filters.

Protect the application's page route and check table authorization before rendering. Package commands default to `web`/`auth` middleware and check table authorization again. Evaluate action-level policy into `Action::authorize(bool)`; use lazy action definitions when it depends on the current request. `Export::authorize()` accepts a boolean or callback. Handlers must authorize each model for the requested mutation. Do not use hidden columns or disabled buttons as access control.

## Implement actions

Import `Wtroiano\TableBuilder\Actions\Action`. Give actions stable `key()` values. Add `handle()` for executable mutations, `asBulkAction()` for row and bulk display, or `onlyAsBulkAction()` for bulk-only display. Use `collectResults(false)` when handler return values are unnecessary. Use `confirm()` for app-appropriate mutation confirmation.

Send raw selection keys, not formatted columns, to `POST /table-builder/actions` with `table`, `action`, `selectionMode` and `keys`. The supplied React view uses explicit page-mode keys. An `all` action streams the base resource query and ignores client search. Limited/offset base queries capture eligible keys in memory before mutation; ordinary queries stream. Prefer the default `chunkById` strategy when mutation changes query membership.

Globally denied actions do not run lifecycle hooks. Authorized `before($models, $action)` and `after($models, $results, $action)` see the selection including skipped rows, once for explicit selections or per chunk for select-all. Row eligibility and action disabled/hidden rules skip handlers. Execution has no automatic transaction: choose atomicity deliberately in the application's handler/service.

Default JSON results include processed/skipped counts and handler results. Preserve partial counts on errors; do not automatically retry mutations. Handler policy and validation exceptions retain Laravel behavior. Inertia command requests redirect; the supplied view uses ordinary JSON HTTP instead.

## Implement exports

Import `Wtroiano\TableBuilder\Exports\Export`. Give exports stable keys and filenames. Use `limitToFilteredRows()` to apply supplied search/sort and `limitToSelectedRows()` to constrain raw keys; an empty selected list exports no rows. `queue(filename, disk)` queues storage; `asDownload(false)` stores synchronously. Authorize the export and keep its resource scoped.

Column `dontExport()`, `exportLabel()`, `exportAs()`, `exportFormat()` and `exportStyle()` customize spreadsheets independently of React. Strings are literal text; CSV formula-control prefixes are escaped. Enable `exportFormulas()` only for intentionally trusted formulas.

A custom `using($table, $export, $request, $query)` callback returns a non-null response to end dispatch, or null to continue. Query mutations from a null-returning callback persist in synchronous dispatch only. Return JSON or a file response for the packaged view. Send unwrapped export state; `state.columns` is a hidden-key list, not the rendered visibility map.

Queue workers do not inherit request/auth/tenant state. Capture trusted scalar/array context, validate and restore it around all callbacks with cleanup. Use the context recipe rather than serializing requests, closures or live queries into jobs.

## Validate the boundary

Test unauthenticated/denied access, unknown definitions, malformed keys, per-record denials, skipped rows and successful effects. Include partial failures, chunk mutation or selected/filtered output when relevant. For queues, serialize and execute under a fresh worker context, then assert restoration and cleanup on both success and failure. Verify literal text/formula behavior if changing exported values.
