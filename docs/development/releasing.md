# Releasing

The Composer package is `wtroiano/table-builder`, hosted at [wtroiano86/table-builder](https://github.com/wtroiano86/table-builder). The first stable release is `v1.0.0`. Composer reads versions from Git tags; keep the `version` field out of `composer.json`, following [Composer's library versioning guidance](https://getcomposer.org/doc/02-libraries.md#library-versioning). Releases are distributed through GitHub; Packagist registration is deferred.

## Validate the candidate

From the repository checkout, install dependencies and run:

```sh
composer install --prefer-dist --no-interaction
npm ci
composer test:release
npm run check
npm run test:browser
```

`test:release` validates the manifest, audits runtime dependencies, runs PHP lint/static analysis/type coverage/line coverage, validates documentation, and checks distribution contents. `npm run check` covers TypeScript, formatting, component tests and the production preview build. Browser tests cover Inertia navigation, actions, downloads, keyboard interaction, accessibility, themes and mobile layouts. Install Chromium with `npx playwright install chromium` if needed.

Run the [Boost smoke test](testing.md#boost-compatibility-smoke-test) in its disposable fixture. Before tagging, require all GitHub Actions jobs to pass on the exact candidate commit: highest/lowest PHP dependencies, SQLite/MySQL/PostgreSQL, all three supported React/Inertia combinations, and Boost discovery. A successful local SQLite run alone does not establish the complete compatibility matrix.

Review `composer audit` and `npm audit` as well. The runtime audit is the release gate; report development-tool advisories or abandoned transitive packages separately instead of silently ignoring them.

## Inspect the distribution

```sh
composer archive --format=zip --dir=tmp/release --file=table-builder-1.0.0 --no-interaction
```

The archive includes PHP runtime files, configuration, routes, React sources, stubs, documentation, the license, and Boost resources. Development dependencies, tests, CI files, local agent state, preview tooling and filesystem metadata are excluded. Git archives use `.gitattributes`; Composer archives additionally use `archive.exclude`. Keep both exclusion lists consistent. The CI dependency job also uploads a `table-builder-package` artifact for review.

Install the archive into a disposable Laravel 13 application, check automatic provider discovery, publish all three `table-builder-*` tags, generate a table with `make:table-builder`, and compile the published React page with the application's Inertia/Vite setup. This is a separate check from the repository's simulated browser server.

## Publish the first release

1. Review and commit the complete package on `main`, then push it to the repository. The first release must include the CI workflows and every runtime/resource/documentation file.
2. Wait for all checks on that commit to pass. Inspect its package artifact and confirm the installation checks above.
3. Create an annotated `v1.0.0` tag on that exact commit and push the tag. Do not move a published tag; fixes require a new version.
4. Verify installation from the GitHub tag in a fresh application using the VCS repository commands in [Getting started](../consumers/getting-started.md#install).
5. If publishing a GitHub release page, create it from the existing tag. Describe the initial package features, PHP 8.5/Laravel 13 requirements, supported React/Inertia combinations, and installation commands. Attach the reviewed archive if offering a direct download.

Packagist registration is a separate future step. If enabled later, submit the repository through [Packagist](https://packagist.org/about), configure its GitHub update integration, and verify installation without a custom VCS repository.

Preparing files and archives does not publish a release. Tags, GitHub releases and Packagist registration are performed when the maintainer is ready to publish.
