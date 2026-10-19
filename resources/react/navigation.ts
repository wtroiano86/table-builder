import type { SelectionKey, TablePayload, TableRow } from "./types";

type Changes = Partial<{
    search: string;
    sort: string;
    direction: "asc" | "desc";
    perPage: number;
    columns: string[];
    sticky: string[];
    page: number;
    cursor: string;
}>;

/** Preserve the host page and other table namespaces; empty scalars reset defaults. */
export function tableUrl(
    table: TablePayload,
    currentUrl: string,
    changes: Changes,
    resetPage = true,
): string {
    const url = new URL(currentUrl, "http://localhost");
    const values: Changes = {
        search: table.state.search ?? "",
        sort: table.state.sort ?? "",
        direction: table.state.direction,
        perPage: table.state.perPage,
        columns: Object.keys(table.state.columns).filter(
            (key) => !table.state.columns[key],
        ),
        sticky: table.state.sticky,
        ...(resetPage ? { page: 1, cursor: "" } : {}),
        ...changes,
    };

    for (const [field, value] of Object.entries(values)) {
        const key =
            table.meta.queryString[field] ??
            (table.meta.queryString.namespace
                ? `${table.meta.queryString.namespace}[${field}]`
                : field);
        for (const existing of [...url.searchParams.keys()]) {
            if (existing === key || existing.startsWith(`${key}[`))
                url.searchParams.delete(existing);
        }
        if (Array.isArray(value) && value.length > 0) {
            for (const item of value) url.searchParams.append(`${key}[]`, item);
        } else {
            url.searchParams.set(
                key,
                Array.isArray(value) ? "" : String(value),
            );
        }
    }
    return `${url.pathname}${url.search}${url.hash}`;
}

export function selectionKey(
    row: TableRow,
    key: string | null,
): SelectionKey | null {
    const value = key === null ? null : row[key];
    return (typeof value === "number" && Number.isSafeInteger(value)) ||
        (typeof value === "string" && value.trim() !== "")
        ? value
        : null;
}

export function keyIdentity(key: SelectionKey): string {
    return `${typeof key}:${key}`;
}

export function exportState(table: TablePayload) {
    return {
        ...table.state,
        columns: Object.keys(table.state.columns).filter(
            (key) => !table.state.columns[key],
        ),
    };
}

export function safeHref(
    value: string | null | undefined,
    base = "http://localhost",
): string | null {
    if (!value?.trim()) return null;
    try {
        const url = new URL(value, base);
        return ["http:", "https:", "mailto:", "tel:"].includes(url.protocol)
            ? value
            : null;
    } catch {
        return null;
    }
}
