import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import fixture from "./tests/react/fixtures/table.json" with { type: "json" };
import type {
    TableAction,
    TableImage,
    TablePagination,
    TablePayload,
} from "./resources/react/types.ts";

const demoActions: TableAction[] = [
    {
        key: "delete",
        label: "Delete",
        type: "action",
        icon: "trash",
        showLabel: false,
        variant: "outline",
        variantColor: "red",
        confirm: {
            title: "Delete records?",
            message: "The selected records will be removed from this preview.",
            confirmButton: "Delete",
            cancelButton: "Keep records",
        },
    },
    {
        key: "deactivate",
        label: "Deactivate",
        type: "action",
        icon: "user-minus",
        showLabel: false,
        variant: "outline",
        variantColor: "amber",
        confirm: {
            title: "Deactivate records?",
            message: "The selected records will be marked inactive.",
            confirmButton: "Deactivate",
            cancelButton: "Cancel",
        },
    },
];

/** Test-only application: exercise real Inertia visits against the PHP contract. */
export default defineConfig({
    plugins: [
        react(),
        {
            name: "table-contract-app",
            configureServer(server) {
                let removed = new Set<string>();
                const inactive = new Set<string>();
                server.middlewares.use(async (request, response, next) => {
                    const url = new URL(request.url ?? "/", "http://localhost");
                    const page = () => {
                        const table = structuredClone(
                            fixture,
                        ) as unknown as TablePayload;
                        const original = table.results as TablePagination;
                        const third = structuredClone(original.data[0]);
                        third._primary_key = 3;
                        third._selection_key = "acct-003";
                        third.name = "Linus Torvalds";
                        (
                            third._column_images as Record<string, TableImage>
                        ).name.alt = String(third.name);
                        let records = [...original.data, third].filter(
                            (row) => !removed.has(String(row._selection_key)),
                        );
                        table.actions = structuredClone(demoActions);
                        table.columns.push({
                            attribute: "actions",
                            header: "Actions",
                            type: "action",
                            alignment: "right",
                            toggleable: false,
                        });
                        table.state.columns.actions = true;
                        for (const row of records) {
                            row.active =
                                Boolean(row.active) &&
                                !inactive.has(String(row._selection_key));
                            row._actions = demoActions.map((action) => ({
                                ...action,
                                disabled:
                                    action.key === "deactivate" && !row.active,
                            }));
                        }
                        const search =
                            url.searchParams.get("people[search]") ?? "";
                        table.state.search = search || null;
                        table.state.direction =
                            url.searchParams.get("people[direction]") === "desc"
                                ? "desc"
                                : "asc";
                        table.state.sort =
                            url.searchParams.get("people[sort]") ?? "name";
                        table.state.perPage = Math.max(
                            1,
                            Number(
                                url.searchParams.get("people[perPage]") ?? 2,
                            ),
                        );
                        if (search)
                            records = records.filter((row) =>
                                String(row.name)
                                    .toLowerCase()
                                    .includes(search.toLowerCase()),
                            );
                        records.sort(
                            (a, b) =>
                                String(a.name).localeCompare(String(b.name)) *
                                (table.state.direction === "desc" ? -1 : 1),
                        );
                        if (
                            url.searchParams.has("people[columns]") ||
                            url.searchParams.has("people[columns][]")
                        ) {
                            const hidden =
                                url.searchParams.getAll("people[columns][]");
                            for (const key of Object.keys(table.state.columns))
                                table.state.columns[
                                    key as keyof typeof table.state.columns
                                ] = !hidden.includes(key);
                        }
                        const cursor = Number(
                            url.searchParams.get("people[cursor]") ?? 0,
                        );
                        const current = Math.max(
                            1,
                            Number(
                                url.searchParams.get("people[page]") ??
                                    (cursor ? cursor + 1 : 1),
                            ),
                        );
                        const makeUrl = (page: number) => {
                            const target = new URL(url);
                            target.pathname = "/records";
                            target.searchParams.set(
                                "people[page]",
                                String(page),
                            );
                            target.searchParams.set(
                                "people[cursor]",
                                String(page - 1),
                            );
                            return target.pathname + target.search;
                        };
                        table.results = {
                            ...table.results,
                            total: records.length,
                            current_page: current,
                            last_page: Math.max(
                                1,
                                Math.ceil(records.length / table.state.perPage),
                            ),
                            per_page: table.state.perPage,
                            from: (current - 1) * table.state.perPage + 1,
                            to: Math.min(
                                records.length,
                                current * table.state.perPage,
                            ),
                            data: records.slice(
                                (current - 1) * table.state.perPage,
                                current * table.state.perPage,
                            ),
                            prev_page_url:
                                current > 1 ? makeUrl(current - 1) : null,
                            next_page_url:
                                current * table.state.perPage < records.length
                                    ? makeUrl(current + 1)
                                    : null,
                            on_first_page: current === 1,
                            on_last_page:
                                current * table.state.perPage >= records.length,
                        };
                        const demo = url.searchParams.get("demo");
                        if (demo === "empty") table.results.data = [];
                        const payload: unknown =
                            demo === "unpaginated"
                                ? {
                                      ...table,
                                      results: records,
                                      pagination: false,
                                  }
                                : demo === "simple" || demo === "cursor"
                                  ? {
                                        ...table,
                                        paginationType: demo,
                                        results: {
                                            ...table.results,
                                            total: undefined,
                                            last_page: undefined,
                                            ...(demo === "cursor"
                                                ? {
                                                      current_page: undefined,
                                                      from: undefined,
                                                      to: undefined,
                                                  }
                                                : {}),
                                        },
                                    }
                                  : table;
                        return {
                            component: "Table",
                            props: {
                                table: payload,
                                title: "People",
                                description:
                                    "A clear view of the people who make things happen.",
                            },
                            url: "/records" + url.search,
                            version: "test",
                            clearHistory: false,
                            encryptHistory: false,
                        };
                    };
                    if (url.pathname === "/__reset") {
                        removed = new Set();
                        inactive.clear();
                        response.end("ok");
                        return;
                    }
                    if (url.pathname === "/avatar.svg") {
                        response.setHeader("Content-Type", "image/svg+xml");
                        response.end(
                            '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" rx="20" fill="#e1e8f5"/><circle cx="20" cy="15" r="6" fill="#687fa8"/><path d="M8 36a12 12 0 0 1 24 0" fill="#687fa8"/></svg>',
                        );
                        return;
                    }
                    if (
                        url.pathname === "/table-builder/actions" ||
                        url.pathname === "/table-builder/exports"
                    ) {
                        let body = "";
                        for await (const chunk of request) body += chunk;
                        const command = JSON.parse(body) as {
                            action?: string;
                            keys?: string[];
                            export?: string;
                            state?: { search?: string };
                        };
                        if (
                            command.keys?.some((key) => !/^acct-\d+$/.test(key))
                        ) {
                            response.statusCode = 422;
                            response.setHeader(
                                "Content-Type",
                                "application/json",
                            );
                            response.end(
                                JSON.stringify({
                                    message: "Expected raw selection keys.",
                                }),
                            );
                            return;
                        }
                        if (url.pathname.endsWith("/actions")) {
                            if (
                                !demoActions.some(
                                    (action) => action.key === command.action,
                                ) ||
                                !command.keys?.length
                            ) {
                                response.statusCode = 422;
                                response.setHeader(
                                    "Content-Type",
                                    "application/json",
                                );
                                response.end(
                                    JSON.stringify({
                                        message:
                                            "Choose an action and select at least one record.",
                                    }),
                                );
                                return;
                            }
                            for (const key of command.keys) {
                                if (command.action === "delete")
                                    removed.add(key);
                                else inactive.add(key);
                            }
                            response.setHeader(
                                "Content-Type",
                                "application/json",
                            );
                            response.end(
                                JSON.stringify({
                                    ok: true,
                                    processed: command.keys?.length ?? 0,
                                }),
                            );
                        } else if (command.export === "background") {
                            response.setHeader(
                                "Content-Type",
                                "application/json",
                            );
                            response.end(
                                JSON.stringify({
                                    ok: true,
                                    message: "Export is being processed.",
                                }),
                            );
                        } else {
                            response.setHeader("Content-Type", "text/csv");
                            response.setHeader(
                                "Content-Disposition",
                                'attachment; filename="people.csv"',
                            );
                            response.end("Name\nAda Lovelace\n");
                        }
                        return;
                    }
                    if (
                        url.pathname === "/__page" ||
                        (url.pathname === "/records" &&
                            request.headers["x-inertia"])
                    ) {
                        response.setHeader("Content-Type", "application/json");
                        response.setHeader("X-Inertia", "true");
                        response.end(JSON.stringify(page()));
                        return;
                    }
                    if (url.pathname === "/records")
                        request.url =
                            "/tests/browser/preview.html" + url.search;
                    next();
                });
            },
        },
    ],
    server: {
        host: "127.0.0.1",
        port: 4173,
        strictPort: true,
        // PHP test dependencies include a symlink back to vendor; only watch UI sources.
        watch: { ignored: ["**/vendor/**", "**/tmp/**", "**/.ijfw/**"] },
    },
    build: {
        outDir: "tmp/react-table-checks/build",
        rolldownOptions: { input: "tests/browser/preview.html" },
    },
});
