# Local demo and screenshots

The demo renders the shipped React `TablePage` and `Table` components with real payloads from the PHP `TableBuilder`. Customers and Invoices each start with 24 fictional records in SQLite. Search, sorting, pagination, visibility, row/bulk actions, and CSV/Excel exports execute through the package.

## Run locally

Clone the repository and use PHP 8.5 with SQLite and the package's required extensions, Composer, and Node.js 22.12 or later:

```sh
git clone https://github.com/wtroiano86/table-builder.git
cd table-builder
composer install
npm ci
npm run demo
```

Open [http://127.0.0.1:4176](http://127.0.0.1:4176). Stop the server with Ctrl+C. If the port is occupied, use `npm run demo -- --port 4180` and open that port instead.

No separate Laravel application, database service, `.env`, or queue worker is needed. The demo boots Laravel using the repository's Testbench dependency. A development-only Vite bridge invokes `php` for each request, so native PHP and container-backed CLI wrappers such as Lerd can both serve it without exposing another port. PHP must be available on `PATH`, and container wrappers must mount the checkout at the same path and forward stdin.

SQLite data and Laravel storage persist under `tmp/demo-4176/` (or the chosen port). **Reset demo** restores both tables and clears the current table's URL state. The browser test and screenshot commands use their own server and data directory on port 4177, leaving the interactive demo alone.

This is a local sample application with deliberately open access to fictional data. It uses Laravel's web middleware and the package's command endpoints; it does not demonstrate application authentication, tenancy or queued workers. A small page-response adapter supplies Inertia's page envelope. Application integrations should follow [Getting started](consumers/getting-started.md) for the full authenticated setup.

## Things to try

| Table | Explore |
| --- | --- |
| Customers | Search `Orbit` to match a company; sort Revenue; reveal Email in Columns; select customers and deactivate or delete them after confirmation |
| Invoices | Sort Amount or Due; inspect Paid, Pending, Overdue and Draft badges; select invoices and mark them paid |
| Both | Change page size; move between pages; export filtered rows to CSV/Excel or selected rows to Excel; switch between light and dark themes |

Table definitions are in [`tests/Demo/DemoTables.php`](https://github.com/wtroiano86/table-builder/blob/main/tests/Demo/DemoTables.php), with deterministic records in [`DemoData.php`](https://github.com/wtroiano86/table-builder/blob/main/tests/Demo/DemoData.php). The application shell adds navigation, a theme control and reset; the table styling comes from the package. The original `npm run dev` contract fixture remains available on port 4173 for focused React development.

## Screenshot gallery

These are PNG captures of the running local demo in Chromium. They show the actual package components, using the sample data above and local system fonts. They are not mockups or generated illustrations.

### Customers, light

![Customer table in light mode with plans, active status, revenue, join dates and row actions](images/customers-light.png)

### Customers, dark

![The same customer table in dark mode](images/customers-dark.png)

### Invoices

![Invoice table showing payment status badges, amounts, issue dates, due dates and Mark paid actions](images/invoices-light.png)

### Selection and bulk actions

![A selected customer with the bulk action menu open](images/customers-actions.png)

### Mobile

The toolbar wraps and the table scrolls horizontally to retain readable columns on small screens.

![Customer table on a 390 pixel wide mobile viewport](images/customers-mobile.png)

## Refresh the screenshots

After installing the dependencies above:

```sh
npx playwright install chromium
npm run test:demo
npm run demo:screenshots
```

Both commands start and stop their own demo server. Captures reset the sample data and use fixed viewports (1440 × 900 desktop, 390 × 844 mobile), the `en-US` locale and UTC timezone. The command overwrites the five PNG files in `docs/images/`; review and commit those images alongside visual changes. Rendering can differ slightly with operating system fonts and Chromium versions.

`npm run test:demo` covers SQLite-backed search, sorting, pagination, visibility, browser history, persisted actions, filtered CSV and selected Excel downloads, empty results, themes, accessibility and narrow layouts. `npm run build:demo` checks that the demo frontend compiles; its build output alone is not a standalone application because the PHP bridge runs in the development server.

Screenshots and this guide ship in Composer archives. Demo source, browser tooling, local databases and generated reports stay in the repository or ignored `tmp/` directory, outside package distributions.
