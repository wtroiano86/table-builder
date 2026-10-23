# Contributing

This guide is for changes to the package repository. To use the package in an application, start with the [consumer guide](../consumers/getting-started.md).

## Set up

Use PHP 8.5 with Composer and the extensions required by Laravel and Laravel Excel. SQLite is sufficient for the default tests. Use Node.js 22.12 or later for the frontend tooling and install from the lockfile:

```sh
composer install
npm ci
composer test:unit
npm test
```

Composer's install hooks prepare the Orchestra Testbench skeleton. No consuming application or database credentials are needed for the default suites. Coverage additionally needs PCOV or Xdebug. See [Testing and quality checks](testing.md) for the full commands and database matrix.

## Repository map

| Location | Responsibility |
| --- | --- |
| `src/Table.php`, `src/TableBuilder.php`, `src/TableRegistry.php` | Public PHP definition and resolution APIs |
| `src/Columns`, `src/Actions`, `src/Exports`, `src/Support` | Column/action/export definitions and public helpers |
| `src/Pipelines`, `src/Query`, `src/Tables` | Query, normalization, rendering and reconstruction internals |
| `src/Http`, `routes`, `config` | HTTP validation, execution, endpoint middleware and defaults |
| `resources/react`, `resources/stubs` | Published React sources, optional page and table generator |
| `resources/boost` | Consumer agent guidelines and skills |
| `tests/Feature`, `tests/Unit` | PHP behavior, HTTP, query, export and distribution contracts |
| `tests/react`, `tests/browser` | Component tests and browser fixture app |
| `tests/Demo` | Demo PHP definitions, browser application and documentation captures |
| `docs/consumers`, `docs/development` | Application usage and package maintenance documentation |

Read [architecture](../architecture.md) before extending a pipeline or importing an internal class. Application integrations should use documented public types rather than depending on pipeline contexts or stores.

Run `npm run demo` for the [interactive Customers and Invoices demo](../demo.md). Use `npm run demo:screenshots` to refresh its checked-in documentation images after visual changes.

## Make a change

Trace the behavior from a public entry point and preserve both fluent and subclass table paths. For query work, consider qualified fields, relationships, resource scopes, defaults and all pagination modes. For actions and exports, consider page/table/record authorization, selection identity, workers and serialization. For frontend work, preserve the PHP payload contract, Inertia partial reloads, keyboard behavior and responsive themes.

Add a regression test for a behavior change at the narrowest useful boundary. When changing payloads, update PHP serialization and TypeScript definitions together, then intentionally regenerate the shared fixture. Published React files become application-owned copies, so document any consumer upgrade steps. Avoid unrelated formatting or dependency updates.

Use [the testing guide](testing.md) to choose checks. Add or update the consumer guide alongside changes to public methods, defaults or HTTP behavior. Update Boost guidance when the agent workflow or supported API changes. Run `composer test:docs` for documentation and skill edits and `composer test:distribution` when adding shipped resources.

## Review and release

Describe the trigger, resulting behavior, validation evidence and any remaining limitation in the pull request. Include database-specific evidence for database-specific changes and browser evidence for interaction or layout changes. Distinguish fixture/browser checks from tests against a full consuming Laravel application.

Check that Composer archives include runtime assets, consumer documentation and Boost resources while excluding development fixtures and local artifacts. Creating a release, publishing a package and changing external agent configuration are separate actions from editing this repository.

Follow [Releasing](releasing.md) for the validation commands, archive and publishing procedure.
