---
name: table-builder-react
description: Install, render and customize the React Table or TablePage shipped by wtroiano/table-builder. Use for Inertia pages, published TSX/CSS, typed custom cells, selection, command transport, responsive styling, themes and accessible table interactions.
---

# Integrate the React view

Read `react.md` and `http-contract.md` in `vendor/wtroiano/table-builder/docs/consumers/`, relative to the application root. Inspect the installed `resources/react/types.ts` for current props. Follow the application's existing Inertia resolver, layout and styling conventions.

## Publish and render

Run in the application:

```sh
php artisan vendor:publish --tag=table-builder-react
php artisan vendor:publish --tag=table-builder-react-page
```

The first tag installs sources at `resources/js/vendor/table-builder`; the second installs `resources/js/pages/vendor/table-builder.tsx`. Move the page into `resources/js/Pages/vendor` for uppercase resolvers. The page name is `vendor/table-builder`; the app supplies its authenticated route, model, policy and authorized table prop. No separate npm table package or UI library is needed. Build with the application's Vite setup.

For an existing page at `resources/js/pages/users/index.tsx`:

```tsx
import { Table, type TablePayload } from '../../vendor/table-builder';

export default function UsersIndex({ table }: { table: TablePayload }) {
    return <Table table={table} caption="Users" />;
}
```

Published files are application-owned copies. Inspect local modifications before replacing them. Publishing without `--force` preserves existing files; compare upstream changes during upgrades.

## Customize deliberately

Use `caption`, `className`, `toolbar`, `renderCell`, `renderIcon`, `onCustomAction`, `onActionSuccess`, `onExportSuccess` and `onError` on `Table`. Set `propName` to the actual Inertia prop key when it is not `table`. Import `TablePage` for a page shell with `title`, `description` and a `headerActions` slot.

Bulk actions use the header Actions menu and require eligible selected rows. Row actions use the Actions column. For outlined icon buttons, configure `icon()`, `hideLabel()`, `variant('outline')`, and `variantColor()` in PHP and map the icon name with `renderIcon`. Menus keep visible labels; missing icons fall back to text.

Return `undefined` from `renderCell` for the built-in cell and `null` for empty content; custom cells own link markup. `renderIcon` maps server icon names into the app's icon system. Use existing app components for formats or widgets the built-in renderer does not implement. Keep table semantics, labels and keyboard focus behavior when customizing.

Theme through scoped `tb-*` classes and `--tb-*` CSS variables loaded after the table stylesheet. System light/dark mode is automatic; an ancestor with `data-theme="light"` or `data-theme="dark"` overrides it. Preserve horizontal table scrolling on narrow screens.

## Preserve state and commands

- Page rows are `results.data`, or directly `results` without pagination. Preserve column definition keys and the raw `row[table.rowSelectionKey]` identity. JavaScript selection keys must be strings or safe integers; cast oversized integer IDs to strings on the model.
- Preserve named query parameters, unrelated page parameters, explicit empty reset values and server cursor URLs. Use the package navigation helpers when extending controls.
- The view posts explicit `selectionMode: "page"` keys. Cross-page persistence is opt-in; search and table identity changes clear selection.
- Commands use same-origin JSON HTTP with session/CSRF protection; file downloads do not use `X-Inertia`. Prefer the shipped request helpers when extending this behavior. Navigation and partial reloads use Inertia.
- A `custom` action needs `onCustomAction`. A custom exporter intended for this view should return JSON or a file response. Use the actions/exports skill for server behavior.

## Validate in the app

Run the app's typecheck and production build. Exercise search/reset, sorting, visibility, pagination, selected-key commands and download errors for affected behavior. Check keyboard operation, confirmation focus, light/dark contrast and a narrow viewport when changing markup or styles. Use the app's SSR build if it enables SSR; keep browser-only APIs inside client execution paths.
