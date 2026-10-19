# Agent workflow for package contributors

Read the repository's canonical `AGENTS.md` and the [contributor guide](contributing.md) before editing. Preserve existing user changes and IJFW-managed instruction regions. The [Boost resources](../boost.md) guide agents integrating the package in applications; they do not replace repository instructions.

## Establish the contract

Read the relevant consumer guide, public entry point and existing behavior tests. Use local source as the authority for this checkout; verify current external framework APIs in official documentation when a change depends on them. Do not invent fluent methods, callbacks, publish tags, HTTP fields or version support. Treat internal query/render pipeline classes as implementation details unless the change deliberately exposes a new extension point.

Distinguish what the application owns (models, policies, tenancy, page routes and published UI copies) from what the package owns (definitions, payloads, command endpoints and shipped sources). Continue using the existing API when it already supports the requested behavior.

## Implement and verify

| Change | Useful checks and evidence |
| --- | --- |
| PHP definitions or queries | Focused Pest regression; fluent/subclass parity; SQLite and affected database behavior; static analysis |
| State or payload | Default/reset/namespace round trips; PHP/React fixture agreement; TypeScript checks |
| Action or export | HTTP denial/validation paths, eligibility, raw keys, partial failures and queued reconstruction as relevant |
| React interaction or styling | Component tests, production build, keyboard/accessibility and narrow-screen browser scenarios |
| Documentation or skills | Links, PHP/JSON snippet syntax, skill metadata, Blade rendering and archive contents |

The exact commands are in [Testing and quality checks](testing.md). Run relevant checks after edits; avoid changing fixture/server source during browser runs because hot reload can invalidate navigation evidence. If a PHP payload intentionally changes, update the shared fixture through `ReactAssetsTest`, then review its diff.

For Boost changes, use a disposable Laravel app with the package as a direct path dependency and a released Boost version. Exercise discovery, guideline rendering, metadata parsing and skill/reference copying into a fixture-only destination. Do not run an installer against a developer's global agent configuration as part of a documentation check.

## Report evidence

Report what changed and why, which checks actually ran, their result, and any untested boundary. Link relevant source, docs or artifacts. Do not turn a syntax check into a claim that application-dependent examples executed successfully. Do not claim browser fixture coverage proves integration with every consuming app. Keep local dependencies, generated reports, temporary fixtures and credentials out of distribution archives.
