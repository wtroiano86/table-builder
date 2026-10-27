# Testing and quality checks

Run with PHP 8.5:

```sh
composer install
composer lint                  # Apply Rector and Pint
composer test                  # Lint, types, type coverage, 100% line coverage
composer test:unit             # Architecture and behavior tests
composer test:docs             # Local links, anchors, PHP/JSON snippets, skill metadata
composer test:distribution     # Validate distribution contents
composer test:release          # Manifest, runtime audit, PHP checks, docs, distribution
npm ci
npm run dev                   # Interactive preview at http://127.0.0.1:4173/records
npm run demo                  # Laravel/SQLite demo at http://127.0.0.1:4176
npm run check                 # Types, formatting, React tests and production build
npm run typecheck
npm run build
npm test                      # React behavior and transport contracts
npx playwright install chromium
npm run test:browser          # Real Inertia navigation, downloads, accessibility
npm run test:demo             # Real Laravel/SQLite queries, actions and exports
npm run build:demo            # Compile the demo frontend
npm run demo:screenshots      # Refresh the five shipped documentation PNGs
npm run format:check
```

Coverage requires Xdebug or PCOV. The unit and coverage scripts set a 1 GiB PHP memory limit for the complete suite, including spreadsheet exports. See [architecture and namespaces](../architecture.md) for internal pipelines and imports.

When the standalone `sloppy` command is installed, run `sloppy --all --fail-on=medium` to check code structure. The repository's `.sloppy-baseline.json` accepts only SL113 for the existing `make()` signatures on `Action`, `Export`, `Column`, `ActionColumn`, and `SerialNumberColumn`. Their positional and named parameters are public compatibility contracts, including through the deprecated `create()` alias. New code should use `make()` with fluent setters. Keep new options in fluent methods; remove these exceptions only alongside an intentional breaking factory migration. Run `sloppy --all --no-baseline` to include these five accepted findings in a review. All other rules retain their default settings.

CI runs SQLite checks with both the highest and lowest resolvable dependencies, plus the full suite against MySQL 8.4 and PostgreSQL 17. To run against a dedicated local test database, set `TABLE_TEST_DB_DRIVER` (`mysql` or `pgsql`), `TABLE_TEST_DB_HOST`, `TABLE_TEST_DB_PORT`, `TABLE_TEST_DB_DATABASE`, `TABLE_TEST_DB_USERNAME`, and `TABLE_TEST_DB_PASSWORD`, then run `composer test:unit`. Tests create and remove their own fixture tables. Without these variables they use an in-memory SQLite database. Shared schemas live in `tests/Support/DatabaseFixtures.php`; scenario data stays in each suite.

Frontend CI covers React 18/Inertia 2, React 19/Inertia 2 and React 19/Inertia 3. The browser fixture app exercises the real Inertia client against a local contract server; the shared payload is checked against PHP serialization in `ReactAssetsTest`. Run that test with `UPDATE_REACT_CONTRACT=1` when intentionally updating `tests/react/fixtures/table.json`. The fixture server is development-only and is excluded from Composer distributions.

For an interactive preview, run `npm run dev` and open `http://127.0.0.1:4173/records`. It uses the shared payload with demo Delete and Deactivate actions in the header menu and an explicit Actions column. Changes are kept in memory; visit `/__reset` and reload the table to restore the sample records. These endpoints simulate application handlers and do not execute Laravel policies or database writes.

For a demo backed by real PHP definitions, run `npm run demo`. Its Customers and Invoices tables use Laravel, Eloquent and SQLite, including the package's action and export handlers. The [demo guide](../demo.md) explains setup, persistence, resets, screenshots and the application's boundaries. `test:demo` and `demo:screenshots` start an isolated instance on port 4177; they do not reuse the interactive server or its database. Screenshot regeneration is explicit and is not part of normal tests.

## Choose checks for the change

Use focused Pest tests while developing, then the full PHP checks for behavior changes. Query changes need the affected pagination modes and database drivers; frontend changes need typechecking, unit tests and a production build, with browser checks for interactions or layout. Documentation-only changes need `composer test:docs` and distribution checks when shipped files change.

`test:docs` checks local Markdown targets and heading anchors, balanced fences, PHP syntax, JSON syntax and skill frontmatter. PHP fences marked `class-body` are method fragments wrapped in a synthetic class; `chain` fences are continuations attached to a synthetic variable. These checks do not execute application models, policies or database queries. TSX examples should also be checked against the public types when changing frontend APIs.

## Boost compatibility smoke test

To check discovery against released Boost 2.10.3 without changing this repository's dependencies, use the isolated Composer fixture included in the repository. CI runs these commands too:

```sh
composer install --working-dir=.github/fixtures/boost --prefer-dist --no-interaction --no-scripts
php .github/scripts/test-boost.php .github/fixtures/boost
```

Alternatively, create a disposable Laravel app outside the package's distribution and install this package as a direct Composer path dependency, then install the Boost version being verified. Run the repository helper with the fixture root:

```sh
php .github/scripts/test-boost.php /absolute/path/to/fixture
```

The helper bootstraps the fixture, uses Boost's real discovery/composers to render the package guideline and parse the three skills, and uses its writer to copy them into the fixture's `.test-agent/skills` directory. It checks the nested queue-context reference too. It creates fixture cache/storage directories; it does not run an agent installer or write global configuration. Use a dedicated disposable directory, never a working application, for this check. Normal `test:docs` does not require Boost.

Resource discovery and copying were verified with Laravel Boost 2.10.3. Review [Boost conventions](../boost.md) and rerun this smoke test when changing agent assets or upgrading the supported Boost workflow. A successful smoke test does not establish compatibility with every agent client or with future Boost releases.
