#!/bin/sh
set -eu

directory=$(mktemp -d)
trap 'rm -rf "$directory"' EXIT HUP INT TERM
composer archive --format=zip --dir="$directory" --file=package --no-interaction
php -r '
$archive = new ZipArchive;
if ($archive->open($argv[1]) !== true) {
    fwrite(STDERR, "Unable to open distribution archive.\n");
    exit(1);
}
for ($index = 0; $index < $archive->numFiles; $index++) {
    echo $archive->getNameIndex($index), "\n";
}
$archive->close();
' "$directory/package.zip" > "$directory/files"
for required in composer.json LICENSE src/Table.php src/TableBuilder.php src/TableRegistry.php src/Tables/ConfiguredTable.php src/Tables/TableConfiguration.php src/Tables/TableRegistryStore.php src/Tables/TableResolver.php src/Providers/TableBuilderServiceProvider.php config/table-builder.php routes/web.php resources/stubs/table.stub resources/react/index.ts resources/react/Table.tsx resources/react/TablePage.tsx resources/react/Cell.tsx resources/react/ConfirmationDialog.tsx resources/react/TableLink.tsx resources/react/requests.ts resources/react/navigation.ts resources/react/types.ts resources/react/table.css resources/stubs/table-builder-page.tsx.stub; do
    if ! grep -Fxq "$required" "$directory/files"; then
        echo "Missing distribution file: $required" >&2
        exit 1
    fi
done
for required in README.md CONTRIBUTING.md llms.txt docs/README.md docs/boost.md docs/architecture.md docs/consumers/getting-started.md docs/consumers/tables.md docs/consumers/columns.md docs/consumers/react.md docs/consumers/state-and-pagination.md docs/consumers/actions.md docs/consumers/exports.md docs/consumers/http-contract.md docs/consumers/troubleshooting.md docs/development/contributing.md docs/development/testing.md docs/development/releasing.md docs/development/agent-workflow.md resources/boost/guidelines/core.blade.php resources/boost/skills/table-builder-development/SKILL.md resources/boost/skills/table-builder-react/SKILL.md resources/boost/skills/table-builder-actions-exports/SKILL.md resources/boost/skills/table-builder-actions-exports/references/queued-context.md; do
    if ! grep -Fxq "$required" "$directory/files"; then
        echo "Missing distribution documentation: $required" >&2
        exit 1
    fi
done
for required in docs/demo.md docs/images/customers-light.png docs/images/customers-dark.png docs/images/invoices-light.png docs/images/customers-actions.png docs/images/customers-mobile.png; do
    if ! grep -Fxq "$required" "$directory/files"; then
        echo "Missing demo documentation: $required" >&2
        exit 1
    fi
done
if grep -Eq '^(vite\.demo\.config\.ts|playwright\.demo\.config\.ts)$' "$directory/files"; then
    echo 'Demo tooling leaked into the distribution.' >&2
    exit 1
fi
if grep -Eq '(^|/)(\._[^/]*|\.DS_Store)$|^(vendor/|node_modules/|tests/|\.github/|\.idea/|\.ijfw/|ijfw/|\.planning/|tmp/|temp/|\.phpstan/|\.phpunit.cache/|\.sloppy-baseline\.json$|AGENTS\.md$|DESIGN\.md$|composer\.lock$|composer\.local\.(json|lock)$|package(-lock)?\.json$|tsconfig\.json$|vitest\.config\.ts$|vite\.preview\.config\.ts$|playwright\.config\.ts$|playwright-report/|test-results/)' "$directory/files"; then
    echo 'Development files leaked into the distribution.' >&2
    exit 1
fi
echo 'Distribution contains runtime, documentation and Boost resources and excludes development files.'
