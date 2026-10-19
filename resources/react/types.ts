import type { ReactNode } from "react";

export type SelectionKey = string | number;
export type JsonValue =
    | string
    | number
    | boolean
    | null
    | JsonValue[]
    | { [key: string]: JsonValue };
export type HttpMethod = "get" | "post" | "put" | "patch" | "delete";

export interface TableUrl {
    url: string | null;
    target?: string | null;
    method?: HttpMethod;
    preserveScroll?: boolean;
    preserveState?: boolean;
    download?: boolean;
    disabled?: boolean;
    hidden?: boolean;
    modal?: boolean;
    prefetch?: { strategy: string; cacheFor: number | null } | null;
}

export interface TableImage {
    url: string | null;
    icon?: string | null;
    urls?: string[];
    alt?: string;
    title?: string;
    width?: number | null;
    height?: number | null;
    rounded?: boolean;
    size?: string;
    position?: string;
    class?: string;
    limit?: number | null;
}

export interface TableAction {
    key: string;
    label: string;
    type: "action" | "link" | "custom";
    url?: TableUrl | string | null;
    endpoint?: string | null;
    authorized?: boolean;
    disabled?: boolean;
    hidden?: boolean;
    confirm?:
        | boolean
        | {
              title?: string | null;
              message?: string | null;
              confirmButton?: string | null;
              cancelButton?: string | null;
          };
    icon?: string | null;
    tooltip?: string | null;
    showLabel?: boolean;
    variant?: string | null;
    variantColor?: string | null;
    class?: string | null;
    data?: Record<string, JsonValue> | [];
    meta?: Record<string, unknown>;
}

export interface TableExport {
    key: string;
    label: string;
    endpoint?: string;
    authorized?: boolean;
    disabled?: boolean;
    hidden?: boolean;
    queued?: boolean;
    asDownload?: boolean;
    limitToFilteredRows?: boolean;
    limitToSelectedRows?: boolean;
    data?: Record<string, JsonValue> | [];
    meta?: Record<string, unknown>;
}

export interface TableColumn {
    attribute: string;
    header: string;
    type: string;
    sortable?: boolean;
    toggleable?: boolean;
    alignment?: "left" | "center" | "right";
    visibleByDefault?: boolean;
    headerClass?: string | null;
    cellClass?: string | null;
    tooltip?: string | null;
    meta?: Record<string, unknown>;
}

export interface TableRow extends Record<string, unknown> {
    _primary_key?: SelectionKey | null;
    _selectable?: boolean;
    _actions?: TableAction[];
    _url?: TableUrl | string;
    _column_urls?: Record<string, TableUrl | string> | [];
    _column_images?: Record<string, TableImage> | [];
}

export interface TablePagination<Row extends TableRow = TableRow> {
    data: Row[];
    current_page?: number;
    last_page?: number;
    total?: number;
    from?: number | null;
    to?: number | null;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    on_first_page: boolean;
    on_last_page: boolean;
}

export interface TableState {
    search: string | null;
    sort: string | null;
    direction: "asc" | "desc";
    perPage: number;
    /** Maps column keys to visibility; the URL carries the hidden keys. */
    columns: Record<string, boolean>;
    sticky: string[];
}

export interface TablePayload<Row extends TableRow = TableRow> {
    name: string;
    results: Row[] | TablePagination<Row>;
    columns: TableColumn[];
    state: TableState;
    meta: {
        table?: string;
        actionEndpoint?: string;
        exportEndpoint?: string;
        queryString: { namespace: string | null } & Record<
            string,
            string | null
        >;
        columns?: Array<{
            key: string;
            width?: string | number | null;
            minWidth?: string | number | null;
            maxWidth?: string | number | null;
        }>;
    };
    actions: TableAction[];
    exports: TableExport[];
    pagination: boolean;
    paginationType: "full" | "simple" | "cursor";
    perPageOptions: number[];
    defaultPerPage: number;
    defaultSort: string | null;
    debounceTime: number;
    reloadProps: string[];
    hasSearch: boolean;
    hasToggleableColumns: boolean;
    hasActions: boolean;
    hasBulkActions: boolean;
    hasExports: boolean;
    stickyHeader: boolean;
    rowSelectionKey: string | null;
    selectable: boolean;
    persistRowSelectionAcrossPages: boolean;
    scrollPositionAfterPageChange: "topOfPage" | "preserve";
    emptyState:
        | false
        | {
              title?: string | null;
              message?: string | null;
              icon?: string | null;
              action?: TableAction | null;
          };
}

export interface CellContext<Row extends TableRow = TableRow> {
    value: unknown;
    row: Row;
    column: TableColumn;
    index: number;
}

export interface TableProps<Row extends TableRow = TableRow> {
    table: TablePayload<Row>;
    /** Inertia prop name for partial reloads, including dotted nested props. */
    propName?: string;
    caption?: string;
    className?: string;
    toolbar?: ReactNode;
    renderCell?: (context: CellContext<Row>) => ReactNode;
    renderIcon?: (name: string) => ReactNode;
    /** Handles application-specific "custom" actions. */
    onCustomAction?: (
        action: TableAction,
        keys: SelectionKey[],
    ) => void | Promise<void>;
    onActionSuccess?: (response: unknown, action: TableAction) => void;
    onExportSuccess?: (response: unknown, definition: TableExport) => void;
    onError?: (error: Error) => void;
}

export interface TablePageProps<
    Row extends TableRow = TableRow,
> extends TableProps<Row> {
    title?: string;
    description?: string;
    headerActions?: ReactNode;
}
