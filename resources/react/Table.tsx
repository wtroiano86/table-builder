import { router } from "@inertiajs/react";
import { useCallback, useEffect, useId, useRef, useState } from "react";
import { Cell, plainText } from "./Cell";
import { ConfirmationDialog } from "./ConfirmationDialog";
import { exportState, keyIdentity, selectionKey, tableUrl } from "./navigation";
import { tableRequest, TableRequestError } from "./requests";
import { followTableLink, TableLink } from "./TableLink";
import type {
    JsonValue,
    SelectionKey,
    TableAction,
    TableColumn,
    TableExport,
    TableProps,
    TableRow,
} from "./types";
import "./table.css";

function Icon({
    name,
}: {
    name:
        | "search"
        | "sort"
        | "asc"
        | "desc"
        | "previous"
        | "next"
        | "columns"
        | "export"
        | "actions";
}) {
    const paths = {
        search: "m21 21-4.5-4.5M19 10.5a8.5 8.5 0 1 1-17 0 8.5 8.5 0 0 1 17 0",
        sort: "m8 9 4-4 4 4m-8 6 4 4 4-4",
        asc: "m7 14 5-5 5 5",
        desc: "m7 10 5 5 5-5",
        previous: "m14 6-6 6 6 6",
        next: "m10 6 6 6-6 6",
        columns:
            "M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12m13 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0",
        export: "M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5",
        actions: "M12 4h.01M12 12h.01M12 20h.01",
    };
    return (
        <svg
            className="tb-icon"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={name === "actions" ? 3 : 1.6}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d={paths[name]} />
        </svg>
    );
}

function SelectPage({
    checked,
    mixed,
    disabled,
    onChange,
}: {
    checked: boolean;
    mixed: boolean;
    disabled: boolean;
    onChange: () => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    useEffect(() => {
        if (input.current) input.current.indeterminate = mixed;
    }, [mixed]);
    return (
        <input
            ref={input}
            type="checkbox"
            aria-label="Select all eligible rows on this page"
            checked={checked}
            disabled={disabled}
            onChange={onChange}
        />
    );
}

export function Table<Row extends TableRow = TableRow>({
    table,
    propName = "table",
    caption = "Records",
    className = "",
    toolbar,
    renderCell,
    renderIcon,
    onCustomAction,
    onActionSuccess,
    onExportSuccess,
    onError,
}: TableProps<Row>) {
    const id = useId();
    const rows = Array.isArray(table.results)
        ? table.results
        : table.results.data;
    const paginator = Array.isArray(table.results) ? null : table.results;
    const columns = table.columns.filter(
        (column) =>
            table.state.columns[column.attribute] ??
            column.visibleByDefault ??
            true,
    );
    const [search, setSearch] = useState(table.state.search ?? "");
    const [selected, setSelected] = useState<Map<string, SelectionKey>>(
        new Map(),
    );
    const [loading, setLoading] = useState(false);
    const [commandBusy, setCommandBusy] = useState(false);
    const [notice, setNotice] = useState<{
        message: string;
        error: boolean;
    } | null>(null);
    const [pending, setPending] = useState<{
        action: TableAction;
        keys: SelectionKey[];
    } | null>(null);
    const [stickyOffsets, setStickyOffsets] = useState<Record<string, number>>(
        {},
    );
    const head = useRef<HTMLTableRowElement>(null);
    const bulkMenu = useRef<HTMLDetailsElement>(null);
    const request = useRef<AbortController | null>(null);
    const busy = useRef(false);
    const visitSequence = useRef(0);
    const cancelVisit = useRef<(() => void) | null>(null);
    const latest = useRef(table);
    latest.current = table;
    const draft = useRef(search);
    draft.current = search;
    const submittedSearch = useRef(search);
    const searchSource = useRef({
        name: table.name,
        reference: table.meta.table,
        value: table.state.search,
    });
    const isBusy = loading || commandBusy;
    const keys = [...selected.values()];
    const canSelect = table.selectable && table.rowSelectionKey !== null;
    const bulkActions = table.actions.filter(
        (action) =>
            !action.hidden &&
            action.authorized !== false &&
            !(typeof action.url === "object" && action.url?.hidden),
    );
    const eligible = rows.flatMap((row) => {
        const key = selectionKey(row, table.rowSelectionKey);
        return row._selectable !== false && key !== null ? [key] : [];
    });
    const pageSelected = eligible.filter((key) =>
        selected.has(keyIdentity(key)),
    ).length;
    const extraActions =
        !table.columns.some((column) => column.type === "action") &&
        rows.some((row) => row._actions?.some((action) => !action.hidden));
    const selectionScope = JSON.stringify([
        table.name,
        table.meta.table,
        table.state.search,
    ]);

    useEffect(() => {
        setSelected(new Map());
    }, [selectionScope]);
    useEffect(() => {
        const dismiss = (event: PointerEvent) => {
            const menu = bulkMenu.current;
            if (
                menu?.open &&
                event.target instanceof Node &&
                !menu.contains(event.target)
            ) {
                menu.open = false;
            }
        };
        document.addEventListener("pointerdown", dismiss);
        return () => document.removeEventListener("pointerdown", dismiss);
    }, []);
    useEffect(() => {
        if (!table.persistRowSelectionAcrossPages) setSelected(new Map());
    }, [table.results, table.persistRowSelectionAcrossPages]);
    useEffect(
        () => () => {
            request.current?.abort();
            cancelVisit.current?.();
        },
        [],
    );

    const fail = useCallback(
        (error: unknown) => {
            if (error instanceof Error && error.name === "AbortError") return;
            const normalized =
                error instanceof Error
                    ? error
                    : new Error("The request could not be completed.");
            const fields =
                normalized instanceof TableRequestError
                    ? Object.values(normalized.errors).flat().join(" ")
                    : "";
            setNotice({ error: true, message: fields || normalized.message });
            onError?.(normalized);
        },
        [onError],
    );

    const visit = useCallback(
        (url: string, replace = false) => {
            const sequence = ++visitSequence.current;
            setLoading(true);
            router.get(
                url,
                {},
                {
                    replace,
                    preserveState: true,
                    preserveScroll:
                        latest.current.scrollPositionAfterPageChange ===
                        "preserve",
                    only: [
                        ...new Set([propName, ...latest.current.reloadProps]),
                    ],
                    onCancelToken: (token) => {
                        cancelVisit.current = () => token.cancel();
                    },
                    onError: (errors) =>
                        fail(new Error(Object.values(errors).join(" "))),
                    onFinish: () => {
                        if (sequence === visitSequence.current) {
                            setLoading(false);
                            cancelVisit.current = null;
                        }
                    },
                },
            );
        },
        [propName, fail],
    );

    const navigate = (
        changes: Parameters<typeof tableUrl>[2],
        resetPage = true,
    ) => visit(tableUrl(table, window.location.href, changes, resetPage));

    // Keep back/forward navigation in sync without replacing a newer local draft.
    useEffect(() => {
        const previous = searchSource.current;
        const changedTable =
            previous.name !== table.name ||
            previous.reference !== table.meta.table;
        if (!changedTable && previous.value === table.state.search) return;
        if (changedTable || draft.current === submittedSearch.current)
            setSearch(table.state.search ?? "");
        submittedSearch.current = table.state.search ?? "";
        searchSource.current = {
            name: table.name,
            reference: table.meta.table,
            value: table.state.search,
        };
    }, [table.state.search, table.name, table.meta.table]);
    useEffect(() => {
        if (search === (table.state.search ?? "")) return;
        const timeout = window.setTimeout(() => {
            submittedSearch.current = search;
            visit(
                tableUrl(latest.current, window.location.href, { search }),
                true,
            );
        }, table.debounceTime);
        return () => window.clearTimeout(timeout);
    }, [search, table.state.search, table.debounceTime, visit]);

    const columnSignature = JSON.stringify([
        columns.map((column) => column.attribute),
        table.state.sticky,
        canSelect,
    ]);
    useEffect(() => {
        const element = head.current;
        if (!element) return;
        const measure = () => {
            let offset = 0;
            const offsets: Record<string, number> = {};
            for (const cell of element.cells) {
                if (cell.dataset.sticky === "true" && cell.dataset.key) {
                    offsets[cell.dataset.key] = offset;
                    offset += cell.getBoundingClientRect().width;
                }
            }
            setStickyOffsets(offsets);
        };
        measure();
        const observer =
            typeof ResizeObserver === "undefined"
                ? null
                : new ResizeObserver(measure);
        observer?.observe(element);
        for (const cell of element.cells) observer?.observe(cell);
        return () => observer?.disconnect();
    }, [columnSignature]);

    const toggleKey = (key: SelectionKey) =>
        setSelected((current) => {
            const next = new Map(current);
            if (next.has(keyIdentity(key))) next.delete(keyIdentity(key));
            else next.set(keyIdentity(key), key);
            return next;
        });
    const togglePage = () =>
        setSelected((current) => {
            const next = new Map(current);
            for (const key of eligible) {
                if (pageSelected === eligible.length)
                    next.delete(keyIdentity(key));
                else next.set(keyIdentity(key), key);
            }
            return next;
        });

    async function execute(action: TableAction, actionKeys: SelectionKey[]) {
        if (busy.current) return;
        busy.current = true;
        setCommandBusy(true);
        setNotice(null);
        request.current = new AbortController();
        try {
            if (action.type === "link" && action.url) {
                followTableLink(action.url, action.data);
                return;
            } else if (action.type === "custom") {
                await onCustomAction?.(action, actionKeys);
            } else {
                const endpoint = action.endpoint ?? table.meta.actionEndpoint;
                if (!endpoint || !table.meta.table)
                    throw new Error("This action requires a registered table.");
                const result = await tableRequest(
                    endpoint,
                    {
                        table: table.meta.table,
                        action: action.key,
                        keys: actionKeys,
                        selectionMode: "page",
                        data: action.data ?? {},
                    },
                    request.current.signal,
                );
                onActionSuccess?.(result.data, action);
            }
            setSelected(new Map());
            setNotice({ error: false, message: `${action.label} completed.` });
            router.reload({
                only: [...new Set([propName, ...table.reloadProps])],
            });
        } catch (error) {
            fail(error);
        } finally {
            busy.current = false;
            setCommandBusy(false);
            request.current = null;
        }
    }

    async function exportRows(definition: TableExport) {
        if (busy.current) return;
        busy.current = true;
        setCommandBusy(true);
        setNotice(null);
        request.current = new AbortController();
        try {
            const endpoint = definition.endpoint ?? table.meta.exportEndpoint;
            if (!endpoint || !table.meta.table)
                throw new Error("This export requires a registered table.");
            const result = await tableRequest(
                endpoint,
                {
                    table: table.meta.table,
                    export: definition.key,
                    state: exportState(table) as unknown as JsonValue,
                    keys,
                    data: definition.data ?? {},
                },
                request.current.signal,
            );
            const message = result.downloaded
                ? "Your download is ready."
                : result.data &&
                    typeof result.data === "object" &&
                    "message" in result.data
                  ? plainText(result.data.message)
                  : "Export is being processed.";
            setNotice({ error: false, message });
            onExportSuccess?.(result.data, definition);
        } catch (error) {
            fail(error);
        } finally {
            busy.current = false;
            setCommandBusy(false);
            request.current = null;
        }
    }

    function actionButton(
        action: TableAction,
        actionKeys: SelectionKey[],
        bulk = false,
        inMenu = bulk,
    ) {
        if (action.hidden || action.authorized === false) return null;
        const urlDefinition =
            typeof action.url === "object" && action.url ? action.url : null;
        if (urlDefinition?.hidden) return null;
        const disabled =
            isBusy ||
            action.disabled ||
            urlDefinition?.disabled ||
            (bulk && actionKeys.length === 0) ||
            (action.type === "custom" && !onCustomAction) ||
            (action.type === "action" && actionKeys.length === 0);
        const icon = action.icon ? renderIcon?.(action.icon) : null;
        const iconOnly = !inMenu && action.showLabel === false && !!icon;
        const content = (
            <>
                {icon}
                <span className={iconOnly ? "tb-sr-only" : undefined}>
                    {action.label}
                </span>
            </>
        );
        const buttonClass = `tb-button tb-action ${iconOnly ? "tb-action-icon" : ""} ${action.class ?? ""}`;
        if (action.type === "link" && action.url && !action.confirm) {
            const url =
                typeof action.url === "string"
                    ? { url: action.url }
                    : action.url;
            return (
                <TableLink
                    key={action.key}
                    url={{ ...url, disabled: disabled || url.disabled }}
                    data={action.data}
                    className={buttonClass}
                    title={
                        action.tooltip ?? (iconOnly ? action.label : undefined)
                    }
                    variant={action.variant}
                    color={action.variantColor}
                >
                    {content}
                </TableLink>
            );
        }
        return (
            <button
                key={action.key}
                type="button"
                className={buttonClass}
                disabled={disabled}
                title={action.tooltip ?? (iconOnly ? action.label : undefined)}
                data-color={action.variantColor}
                data-variant={action.variant}
                onClick={() => {
                    if (action.confirm)
                        setPending({ action, keys: actionKeys });
                    else void execute(action, actionKeys);
                }}
            >
                {content}
            </button>
        );
    }

    function rowActions(row: Row, column?: TableColumn) {
        const definitions = column?.meta?.actions as TableAction[] | undefined;
        const actions = (
            definitions?.length ? definitions : (row._actions ?? [])
        ).filter((action) => !action.hidden && action.authorized !== false);
        const key = selectionKey(row, table.rowSelectionKey);
        const content = actions.map((action) =>
            actionButton(
                {
                    ...action,
                    disabled:
                        action.disabled ||
                        (action.type === "action" && row._selectable === false),
                },
                key === null ? [] : [key],
                false,
                column?.meta?.asDropdown === true,
            ),
        );
        if (column?.meta?.asDropdown && content.length)
            return (
                <details className="tb-menu">
                    <summary
                        className="tb-button"
                        aria-label={`Actions for row ${plainText(row._primary_key)}`}
                    >
                        Actions
                    </summary>
                    <div className="tb-menu-panel">{content}</div>
                </details>
            );
        return <div className="tb-row-actions">{content}</div>;
    }

    const stickyStyle = (key: string) => ({ left: stickyOffsets[key] ?? 0 });
    const hasSticky = table.state.sticky.length > 0;
    const currentPage = paginator?.current_page ?? 1;
    const offset =
        paginator?.from != null
            ? paginator.from - 1
            : (currentPage - 1) * table.state.perPage;
    const totalColumns = Math.max(
        1,
        columns.length + Number(canSelect) + Number(extraActions),
    );

    return (
        <section
            className={`tb-table ${className}`}
            aria-label={caption}
            aria-busy={isBusy}
            data-sticky-header={table.stickyHeader}
        >
            <div className="tb-toolbar">
                {table.hasSearch && (
                    <label className="tb-search" htmlFor={`${id}-search`}>
                        <Icon name="search" />
                        <span className="tb-sr-only">Search {caption}</span>
                        <input
                            id={`${id}-search`}
                            type="search"
                            value={search}
                            placeholder="Search records…"
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </label>
                )}
                <div className="tb-toolbar-actions">
                    {toolbar}
                    {table.hasToggleableColumns && (
                        <details className="tb-menu">
                            <summary className="tb-button">
                                <Icon name="columns" />
                                Columns
                            </summary>
                            <div className="tb-menu-panel tb-column-menu">
                                <p className="tb-menu-title">Visible columns</p>
                                {table.columns
                                    .filter((column) => column.toggleable)
                                    .map((column) => (
                                        <label key={column.attribute}>
                                            <input
                                                type="checkbox"
                                                checked={
                                                    table.state.columns[
                                                        column.attribute
                                                    ] ??
                                                    column.visibleByDefault ??
                                                    true
                                                }
                                                disabled={isBusy}
                                                onChange={() => {
                                                    const hidden = table.columns
                                                        .filter(
                                                            (item) =>
                                                                !(
                                                                    table.state
                                                                        .columns[
                                                                        item
                                                                            .attribute
                                                                    ] ??
                                                                    item.visibleByDefault ??
                                                                    true
                                                                ),
                                                        )
                                                        .map(
                                                            (item) =>
                                                                item.attribute,
                                                        );
                                                    navigate({
                                                        columns:
                                                            hidden.includes(
                                                                column.attribute,
                                                            )
                                                                ? hidden.filter(
                                                                      (key) =>
                                                                          key !==
                                                                          column.attribute,
                                                                  )
                                                                : [
                                                                      ...hidden,
                                                                      column.attribute,
                                                                  ],
                                                    });
                                                }}
                                            />
                                            {column.header}
                                        </label>
                                    ))}
                            </div>
                        </details>
                    )}
                    {table.exports.some(
                        (definition) =>
                            !definition.hidden &&
                            definition.authorized !== false,
                    ) && (
                        <details className="tb-menu">
                            <summary className="tb-button">
                                <Icon name="export" />
                                Export
                            </summary>
                            <div className="tb-menu-panel">
                                {table.exports
                                    .filter(
                                        (definition) =>
                                            !definition.hidden &&
                                            definition.authorized !== false,
                                    )
                                    .map((definition) => (
                                        <button
                                            type="button"
                                            className="tb-button"
                                            key={definition.key}
                                            disabled={
                                                isBusy ||
                                                definition.disabled ||
                                                (definition.limitToSelectedRows &&
                                                    keys.length === 0)
                                            }
                                            onClick={() =>
                                                void exportRows(definition)
                                            }
                                        >
                                            {definition.label}
                                            {definition.limitToSelectedRows && (
                                                <span className="tb-muted">
                                                    Selected rows
                                                </span>
                                            )}
                                        </button>
                                    ))}
                            </div>
                        </details>
                    )}
                    {canSelect && bulkActions.length > 0 && (
                        <details
                            ref={bulkMenu}
                            className="tb-menu tb-actions-menu"
                            onKeyDown={(event) => {
                                if (event.key === "Escape") {
                                    event.preventDefault();
                                    event.currentTarget.open = false;
                                    event.currentTarget
                                        .querySelector("summary")
                                        ?.focus();
                                }
                            }}
                        >
                            <summary className="tb-button">
                                Actions
                                <Icon name="actions" />
                            </summary>
                            <div
                                className="tb-menu-panel"
                                onClick={(event) => {
                                    if (
                                        event.target instanceof Element &&
                                        event.target.closest(
                                            "button:not(:disabled), a[href]",
                                        )
                                    ) {
                                        const menu = bulkMenu.current;
                                        if (menu) {
                                            menu.open = false;
                                            menu.querySelector(
                                                "summary",
                                            )?.focus();
                                        }
                                    }
                                }}
                            >
                                <p className="tb-menu-title">
                                    {keys.length
                                        ? `${keys.length} selected`
                                        : "Select rows to apply an action"}
                                </p>
                                {bulkActions.map((action) =>
                                    actionButton(action, keys, true),
                                )}
                            </div>
                        </details>
                    )}
                </div>
            </div>

            {canSelect && keys.length > 0 && (
                <div className="tb-selection-toolbar">
                    <span role="status">{keys.length} selected</span>
                    <div className="tb-row-actions">
                        <button
                            type="button"
                            className="tb-button tb-button-quiet"
                            onClick={() => setSelected(new Map())}
                            disabled={isBusy}
                        >
                            Clear selection
                        </button>
                    </div>
                </div>
            )}
            {notice && (
                <div
                    className={`tb-notice ${notice.error ? "tb-notice-error" : ""}`}
                    role={notice.error ? "alert" : "status"}
                >
                    <span>{notice.message}</span>
                    <button
                        className="tb-dismiss"
                        type="button"
                        aria-label="Dismiss notification"
                        onClick={() => setNotice(null)}
                    >
                        ×
                    </button>
                </div>
            )}
            <div className="tb-progress" role="status" aria-live="polite">
                {isBusy && (
                    <span>
                        {commandBusy ? "Processing…" : "Updating table…"}
                    </span>
                )}
            </div>

            <div
                className="tb-scroll-region"
                role="region"
                aria-label={`${caption} table`}
                tabIndex={0}
            >
                <table className="tb-grid">
                    <caption className="tb-sr-only">{caption}</caption>
                    <thead>
                        <tr ref={head}>
                            {canSelect && (
                                <th
                                    scope="col"
                                    className="tb-select-cell"
                                    data-key="_select"
                                    data-sticky={hasSticky}
                                    style={stickyStyle("_select")}
                                >
                                    <SelectPage
                                        checked={
                                            eligible.length > 0 &&
                                            pageSelected === eligible.length
                                        }
                                        mixed={
                                            pageSelected > 0 &&
                                            pageSelected < eligible.length
                                        }
                                        disabled={
                                            isBusy || eligible.length === 0
                                        }
                                        onChange={togglePage}
                                    />
                                </th>
                            )}
                            {columns.map((column) => {
                                const active =
                                    table.state.sort === column.attribute;
                                const sizing = table.meta.columns?.find(
                                    (item) => item.key === column.attribute,
                                );
                                return (
                                    <th
                                        key={column.attribute}
                                        scope="col"
                                        className={
                                            column.headerClass ?? undefined
                                        }
                                        data-key={column.attribute}
                                        data-align={column.alignment}
                                        data-sticky={table.state.sticky.includes(
                                            column.attribute,
                                        )}
                                        aria-sort={
                                            column.sortable
                                                ? active
                                                    ? table.state.direction ===
                                                      "asc"
                                                        ? "ascending"
                                                        : "descending"
                                                    : "none"
                                                : undefined
                                        }
                                        style={{
                                            ...stickyStyle(column.attribute),
                                            width: sizing?.width ?? undefined,
                                            minWidth:
                                                sizing?.minWidth ?? undefined,
                                            maxWidth:
                                                sizing?.maxWidth ?? undefined,
                                        }}
                                    >
                                        {column.sortable ? (
                                            <button
                                                className="tb-sort"
                                                type="button"
                                                disabled={isBusy}
                                                onClick={() =>
                                                    navigate({
                                                        sort: column.attribute,
                                                        direction:
                                                            active &&
                                                            table.state
                                                                .direction ===
                                                                "asc"
                                                                ? "desc"
                                                                : "asc",
                                                    })
                                                }
                                            >
                                                {column.header}
                                                <Icon
                                                    name={
                                                        active
                                                            ? table.state
                                                                  .direction
                                                            : "sort"
                                                    }
                                                />
                                            </button>
                                        ) : (
                                            column.header
                                        )}
                                    </th>
                                );
                            })}
                            {extraActions && (
                                <th scope="col" className="tb-actions-heading">
                                    Actions
                                </th>
                            )}
                            {columns.length === 0 &&
                                !canSelect &&
                                !extraActions && <th scope="col">Records</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => {
                            const key = selectionKey(
                                row,
                                table.rowSelectionKey,
                            );
                            const selectedRow =
                                key !== null && selected.has(keyIdentity(key));
                            return (
                                <tr
                                    key={keyIdentity(
                                        row._primary_key ?? key ?? index,
                                    )}
                                    data-selected={selectedRow}
                                >
                                    {canSelect && (
                                        <td
                                            className="tb-select-cell"
                                            data-sticky={hasSticky}
                                            style={stickyStyle("_select")}
                                        >
                                            <input
                                                type="checkbox"
                                                aria-label={`Select row ${plainText(row._primary_key ?? index + 1)}`}
                                                disabled={
                                                    isBusy ||
                                                    row._selectable === false ||
                                                    key === null
                                                }
                                                checked={selectedRow}
                                                onChange={() => {
                                                    if (key !== null)
                                                        toggleKey(key);
                                                }}
                                            />
                                        </td>
                                    )}
                                    {columns.map((column, columnIndex) => {
                                        const context = {
                                            column,
                                            row,
                                            value: row[column.attribute],
                                            index,
                                        };
                                        const custom = renderCell?.(context);
                                        const content =
                                            custom !== undefined ? (
                                                custom
                                            ) : column.type === "action" ? (
                                                rowActions(row, column)
                                            ) : (
                                                <Cell
                                                    {...context}
                                                    offset={offset}
                                                    renderIcon={renderIcon}
                                                />
                                            );
                                        const url =
                                            (
                                                row._column_urls as
                                                    | Record<
                                                          string,
                                                          | import("./types").TableUrl
                                                          | string
                                                      >
                                                    | undefined
                                            )?.[column.attribute] ??
                                            (columnIndex === 0 &&
                                            column.type !== "action"
                                                ? row._url
                                                : undefined);
                                        return (
                                            <td
                                                key={column.attribute}
                                                data-align={column.alignment}
                                                data-sticky={table.state.sticky.includes(
                                                    column.attribute,
                                                )}
                                                style={stickyStyle(
                                                    column.attribute,
                                                )}
                                                className={
                                                    column.cellClass ??
                                                    undefined
                                                }
                                                title={
                                                    column.tooltip ?? undefined
                                                }
                                            >
                                                {url && custom === undefined ? (
                                                    <TableLink
                                                        url={url}
                                                        className="tb-cell-link"
                                                    >
                                                        {content}
                                                    </TableLink>
                                                ) : (
                                                    content
                                                )}
                                            </td>
                                        );
                                    })}
                                    {extraActions && (
                                        <td className="tb-actions-cell">
                                            {rowActions(row)}
                                        </td>
                                    )}
                                    {columns.length === 0 &&
                                        !canSelect &&
                                        !extraActions && (
                                            <td className="tb-muted">
                                                No visible columns
                                            </td>
                                        )}
                                </tr>
                            );
                        })}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={totalColumns}>
                                    <div className="tb-empty">
                                        {table.emptyState &&
                                            table.emptyState.icon &&
                                            renderIcon?.(table.emptyState.icon)}
                                        <h2>
                                            {(table.emptyState &&
                                                table.emptyState.title) ||
                                                "No records found"}
                                        </h2>
                                        <p>
                                            {(table.emptyState &&
                                                table.emptyState.message) ||
                                                "Try changing your search or adjusting the table settings."}
                                        </p>
                                        {table.emptyState &&
                                            table.emptyState.action &&
                                            actionButton(
                                                table.emptyState.action,
                                                [],
                                            )}
                                    </div>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <footer className="tb-footer">
                <p aria-live="polite" className="tb-result-count">
                    {paginator?.total !== undefined
                        ? rows.length
                            ? `Showing ${paginator.from ?? offset + 1}–${paginator.to ?? offset + rows.length} of ${paginator.total} records`
                            : "No records"
                        : `${rows.length} ${rows.length === 1 ? "record" : "records"}`}
                    {keys.length > 0 && ` · ${keys.length} selected`}
                </p>
                {table.pagination && (
                    <div className="tb-pagination-controls">
                        <label htmlFor={`${id}-per-page`}>
                            Rows per page
                            <select
                                id={`${id}-per-page`}
                                value={table.state.perPage}
                                disabled={isBusy}
                                onChange={(event) =>
                                    navigate({
                                        perPage: Number(event.target.value),
                                    })
                                }
                            >
                                {[
                                    ...new Set([
                                        ...table.perPageOptions,
                                        table.state.perPage,
                                    ]),
                                ]
                                    .sort((a, b) => a - b)
                                    .map((option) => (
                                        <option key={option} value={option}>
                                            {option}
                                        </option>
                                    ))}
                            </select>
                        </label>
                        <nav
                            aria-label={`${caption} pagination`}
                            className="tb-pagination"
                        >
                            <button
                                type="button"
                                className="tb-button tb-page-button"
                                aria-label="Previous page"
                                disabled={isBusy || !paginator?.prev_page_url}
                                onClick={() => {
                                    if (paginator?.prev_page_url)
                                        visit(paginator.prev_page_url);
                                }}
                            >
                                <Icon name="previous" />
                            </button>
                            {paginator?.last_page !== undefined ? (
                                <span className="tb-page-position">
                                    Page {currentPage} of {paginator.last_page}
                                </span>
                            ) : table.paginationType === "simple" ? (
                                <span className="tb-page-position">
                                    Page {currentPage}
                                </span>
                            ) : null}
                            <button
                                type="button"
                                className="tb-button tb-page-button"
                                aria-label="Next page"
                                disabled={isBusy || !paginator?.next_page_url}
                                onClick={() => {
                                    if (paginator?.next_page_url)
                                        visit(paginator.next_page_url);
                                }}
                            >
                                <Icon name="next" />
                            </button>
                        </nav>
                    </div>
                )}
            </footer>
            {pending && (
                <ConfirmationDialog
                    action={pending.action}
                    onCancel={() => setPending(null)}
                    onConfirm={() => {
                        const command = pending;
                        setPending(null);
                        void execute(command.action, command.keys);
                    }}
                />
            )}
        </section>
    );
}
