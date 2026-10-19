# React component and page

In an application already configured for Inertia and React, publish the component sources and optional page:

```sh
php artisan vendor:publish --tag=table-builder-react
php artisan vendor:publish --tag=table-builder-react-page
npm run build
```

The component lives in `resources/js/vendor/table-builder`. It includes TypeScript types and scoped CSS, imports the application's `react` and `@inertiajs/react`, and needs no additional UI library or separate npm table package. Vite compiles the published TSX, including in JavaScript applications.

The page is published to `resources/js/pages/vendor/table-builder.tsx`. If your Inertia page resolver uses `Pages` instead of `pages`, move that file into `resources/js/Pages/vendor/`; its relative import remains valid. Return the page from your authenticated controller after checking table authorization:

```php
use Inertia\Inertia;
use Wtroiano\TableBuilder\TableRegistry;

$table = TableRegistry::get('users');
abort_unless($table->authorize($request), 403);

return Inertia::render('vendor/table-builder', [
    'table' => $table,
    'title' => 'Users',
    'description' => 'Manage the people in your organization.',
]);
```

Define the [registered table](tables.md#registered-fluent-tables) in an application provider. A table built inline also works for rendering. The page accepts `table`, `title`, and `description` props. The React `TablePage` component additionally accepts a `headerActions` slot when imported into your own page.

To embed just the table in an existing layout, create an application page such as `resources/js/pages/users/index.tsx`:

```tsx
import { Table, type TablePayload } from '../../vendor/table-builder';

export default function UsersIndex({ table }: { table: TablePayload }) {
    return <Table table={table} caption="Users" />;
}
```

Both views provide debounced search, sortable headers, column visibility, page-size controls, full/simple/cursor pagination, eligible row selection, bulk/row actions with confirmations, and filtered/selected/queued exports. Native table semantics, focus handling, loading/error/empty states, and light/dark styling are included. Table commands use same-origin HTTP requests with Laravel's current XSRF cookie (or a CSRF meta token); file responses download directly and JSON command responses display status. Actions refresh the current table prop through Inertia.

### React customization

The `Table` component accepts `propName` (default `table`) for partial reloads, `caption`, `className`, `toolbar`, `renderCell`, `renderIcon`, `onCustomAction`, `onActionSuccess`, `onExportSuccess`, and `onError`. For example:

```tsx
<Table
    table={table}
    caption="Users"
    propName="users"
    className="users-table"
    renderCell={({ column, value }) =>
        column.attribute === 'email' ? <a href={`mailto:${String(value)}`}>{String(value)}</a> : undefined
    }
/>
```

Return `undefined` from `renderCell` to use the built-in cell, or `null` for an empty cell. Custom cells own their link markup. The built-in cells render escaped text, badges, booleans, numbers, PHP-style date formats (`Y y m n d j H G h g i s a A M F D l`, with backslash escapes), images, serial numbers, and actions. Dates use UTC unless `DateTimeColumn::timezone()` is set. Use a custom renderer for other formats or application widgets. `renderIcon(name)` maps server icon names to your application's icon components. A row URL links the first visible data cell; column URLs take precedence. Relative and same-origin links use Inertia; external GET links, new tabs, and downloads use ordinary browser navigation. Application-specific modal/prefetch behavior can be supplied in custom cells.

Selection uses the raw `row[table.rowSelectionKey]`, preserves disabled row rules, and posts `selectionMode: "page"`. Selection resets when results change unless `persistRowSelectionAcrossPages()` is enabled; search and table-identity changes always clear it. Selected exports require explicit selected keys. A `custom` action delegates to `onCustomAction`; custom exporters used with the supplied view should return JSON or a file response.

Bulk actions appear in the header's Actions menu beside Columns and Export. Open the menu to see available actions; select eligible rows to enable them. The selection count and Clear selection control appear after selecting rows. Row actions appear in the Actions column, with `ActionColumn` supporting explicit placement or a row dropdown. To display icon buttons, configure an action with `icon()`, `hideLabel()`, `variant('outline')`, and `variantColor('red')` or `variantColor('amber')`, then resolve the icon with `renderIcon`. Labels remain available to assistive technology and stay visible in menus or when an icon cannot be rendered. Applications with published copies should republish both the React sources and stylesheet to pick up these changes.

Styles use `tb-*` classes and `--tb-*` CSS variables. The system color scheme is automatic; wrap a view in an element with `data-theme="light"` or `data-theme="dark"` to override it. Customize the palette after the component stylesheet:

```css
.tb-table.users-table {
    --tb-accent: #3158a0;
    --tb-accent-soft: #eef3fc;
}
```

Published files belong to the application and can be customized. Publishing again preserves existing files; review changes before using `--force` to replace them during upgrades. The package does not register a data page route; application controllers and middleware continue to own page access.

## Typed row data

Use a row type when custom cells need application attributes. Include `TableRow` so the reserved selection/action fields remain available:

```tsx
import { Table, type TablePayload, type TableRow } from '../../vendor/table-builder';

type UserRow = TableRow & { name: string; email: string };

export default function UsersIndex({ table }: { table: TablePayload<UserRow> }) {
    return (
        <Table<UserRow>
            table={table}
            caption="Users"
            renderCell={({ column, row }) =>
                column.attribute === 'email'
                    ? <a href={`mailto:${row.email}`}>{row.email}</a>
                    : undefined
            }
        />
    );
}
```

The type describes the PHP payload you configured; it does not validate server data at runtime. Keep it consistent with column keys and `transformModel()` output. The complete prop and callback signatures are exported from the published `types.ts` and [shipped types](../../resources/react/types.ts).
