import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { renderToString } from "react-dom/server";
import { beforeEach, expect, it, vi } from "vitest";
import { Table } from "../../resources/react/Table";
import type {
    TablePayload,
    TablePagination,
} from "../../resources/react/types";
import { fixture } from "./fixture";

const inertia = vi.hoisted(() => ({
    get: vi.fn(),
    reload: vi.fn(),
    visit: vi.fn(),
}));
vi.mock("@inertiajs/react", () => ({ router: inertia }));

beforeEach(() => {
    for (const mock of Object.values(inertia)) mock.mockReset();
    inertia.get.mockImplementation((_url, _data, options) => {
        options?.onFinish?.();
    });
});

function rowCheckbox(index = 1) {
    return screen.getByRole("checkbox", { name: `Select row ${index}` });
}
function paginator(table: TablePayload) {
    return table.results as TablePagination;
}

it("renders the real backend payload, safe typed cells, visibility, images and sortable headers", () => {
    render(<Table table={fixture()} caption="People" />);
    expect(screen.getByRole("table", { name: "People" })).toBeInTheDocument();
    expect(screen.getByText("Ada Lovelace")).toBeInTheDocument();
    expect(screen.getByText("Active", { selector: "span" })).toHaveAttribute(
        "data-value",
        "true",
    );
    expect(screen.getAllByText("$3,475.50")).toHaveLength(2);
    expect(screen.getAllByText("Jan 2, 2026")).toHaveLength(2);
    expect(screen.getByRole("img", { name: "Ada Lovelace" })).toHaveAttribute(
        "src",
        "/avatar.svg",
    );
    expect(
        screen.queryByRole("columnheader", { name: "Account" }),
    ).not.toBeInTheDocument();
    expect(screen.getByRole("columnheader", { name: "Name" })).toHaveAttribute(
        "aria-sort",
        "ascending",
    );
    expect(rowCheckbox(2)).toBeDisabled();
});

it("debounces scoped search and preserves unrelated state, then resets defaults when cleared", async () => {
    const user = userEvent.setup();
    const table = fixture();
    table.debounceTime = 10;
    const { rerender } = render(<Table table={table} caption="People" />);
    await user.type(screen.getByRole("searchbox"), "Ada");
    await waitFor(() => expect(inertia.get).toHaveBeenCalled());
    let url = new URL(inertia.get.mock.lastCall![0], "http://localhost");
    expect(url.searchParams.get("people[search]")).toBe("Ada");
    expect(url.searchParams.get("people[page]")).toBe("1");
    expect(url.searchParams.get("orders[page]")).toBe("3");
    expect(inertia.get.mock.lastCall![2]).toMatchObject({
        preserveState: true,
        replace: true,
        only: ["table"],
    });
    const updated = fixture();
    updated.state.search = "Ada";
    updated.debounceTime = 10;
    rerender(<Table table={updated} caption="People" />);
    await user.clear(screen.getByRole("searchbox"));
    await waitFor(() =>
        expect(
            new URL(
                inertia.get.mock.lastCall![0],
                "http://localhost",
            ).searchParams.get("people[search]"),
        ).toBe(""),
    );
});

it("sorts, changes page size and toggles hidden columns through the server", async () => {
    const user = userEvent.setup();
    render(<Table table={fixture()} propName="people" />);
    await user.click(screen.getByRole("button", { name: "Name" }));
    let url = new URL(inertia.get.mock.lastCall![0], "http://localhost");
    expect(url.searchParams.get("people[direction]")).toBe("desc");
    expect(url.searchParams.get("people[cursor]")).toBe("");
    expect(inertia.get.mock.lastCall![2].only).toEqual(["people"]);
    await user.selectOptions(screen.getByLabelText("Rows per page"), "10");
    expect(
        new URL(
            inertia.get.mock.lastCall![0],
            "http://localhost",
        ).searchParams.get("people[perPage]"),
    ).toBe("10");
    await user.click(screen.getByText("Columns", { selector: "summary" }));
    await user.click(screen.getByLabelText("Account"));
    url = new URL(inertia.get.mock.lastCall![0], "http://localhost");
    expect(url.searchParams.get("people[columns]")).toBe("");
});

it.each(["full", "simple", "cursor"] as const)(
    "uses server pagination links for %s pagination",
    async (mode) => {
        const user = userEvent.setup();
        const table = fixture();
        table.paginationType = mode;
        paginator(table).next_page_url =
            "/records?people[cursor]=opaque-token&orders[page]=3";
        if (mode !== "full") delete paginator(table).last_page;
        render(<Table table={table} />);
        expect(
            screen.getByRole("button", { name: "Previous page" }),
        ).toBeDisabled();
        await user.click(screen.getByRole("button", { name: "Next page" }));
        expect(inertia.get).toHaveBeenLastCalledWith(
            paginator(table).next_page_url,
            {},
            expect.any(Object),
        );
    },
);

it("uses raw selection keys and waits for confirmation before executing a bulk action", async () => {
    const user = userEvent.setup();
    const fetcher = vi.spyOn(globalThis, "fetch").mockResolvedValue(
        new Response(JSON.stringify({ ok: true, processed: 1 }), {
            headers: { "Content-Type": "application/json" },
        }),
    );
    render(<Table table={fixture()} />);
    await user.click(
        screen.getByRole("checkbox", {
            name: "Select all eligible rows on this page",
        }),
    );
    expect(rowCheckbox()).toBeChecked();
    expect(rowCheckbox(2)).not.toBeChecked();
    const menu = screen.getByText("Actions", { selector: "summary" });
    await user.click(menu);
    await user.click(
        within(menu.parentElement!).getByRole("button", { name: "Archive" }),
    );
    expect(fetcher).not.toHaveBeenCalled();
    const dialog = screen.getByRole("dialog", { name: "Archive records?" });
    await user.click(
        within(dialog).getByRole("button", { name: "Keep records" }),
    );
    expect(fetcher).not.toHaveBeenCalled();
    expect(menu).toHaveFocus();
    await user.click(menu);
    await user.click(
        within(menu.parentElement!).getByRole("button", { name: "Archive" }),
    );
    await user.click(
        within(screen.getByRole("dialog")).getByRole("button", {
            name: "Archive",
        }),
    );
    await waitFor(() => expect(fetcher).toHaveBeenCalledOnce());
    expect(JSON.parse(fetcher.mock.calls[0][1]!.body as string)).toMatchObject({
        table: "registry:react-records",
        keys: ["acct-001"],
        selectionMode: "page",
        action: "archive",
    });
    expect(rowCheckbox()).not.toBeChecked();
    expect(inertia.reload).toHaveBeenCalledWith({ only: ["table"] });
    expect(screen.getByText("Archive completed.")).toBeInTheDocument();
});

it.each([false, true])(
    "honors cross-page selection persistence: %s",
    async (persist) => {
        const user = userEvent.setup();
        const table = fixture();
        table.persistRowSelectionAcrossPages = persist;
        const { rerender } = render(<Table table={table} />);
        await user.click(rowCheckbox());
        expect(rowCheckbox()).toBeChecked();
        const next = fixture();
        next.persistRowSelectionAcrossPages = persist;
        paginator(next).data = [paginator(next).data[0]];
        rerender(<Table table={next} />);
        await waitFor(() =>
            expect(rowCheckbox()).toHaveProperty("checked", persist),
        );
    },
);

it("clears even persistent selection when the search dataset changes", async () => {
    const user = userEvent.setup();
    const table = fixture();
    table.persistRowSelectionAcrossPages = true;
    const { rerender } = render(<Table table={table} />);
    await user.click(rowCheckbox());
    const next = fixture();
    next.persistRowSelectionAcrossPages = true;
    next.state.search = "Ada";
    rerender(<Table table={next} />);
    expect(rowCheckbox()).not.toBeChecked();
});

it("requires explicit selection for selected exports and sends the current normalized state", async () => {
    const user = userEvent.setup();
    const fetcher = vi
        .spyOn(globalThis, "fetch")
        .mockResolvedValue(
            new Response(
                JSON.stringify({ ok: true, message: "Queued for delivery." }),
                { headers: { "Content-Type": "application/json" } },
            ),
        );
    const table = fixture();
    table.state.search = "Ada";
    render(<Table table={table} />);
    await user.click(screen.getByText("Export", { selector: "summary" }));
    expect(
        screen.getByRole("button", { name: /Download selected/ }),
    ).toBeDisabled();
    await user.click(rowCheckbox());
    await user.click(screen.getByRole("button", { name: /Download selected/ }));
    await waitFor(() => expect(fetcher).toHaveBeenCalledOnce());
    expect(JSON.parse(fetcher.mock.calls[0][1]!.body as string)).toMatchObject({
        table: "registry:react-records",
        export: "selected",
        keys: ["acct-001"],
        state: {
            search: "Ada",
            sort: "name",
            direction: "asc",
            columns: ["code"],
            sticky: [],
        },
    });
    expect(screen.getByText("Queued for delivery.")).toBeInTheDocument();
});

it("presents field errors and prevents duplicate commands while a request is pending", async () => {
    const user = userEvent.setup();
    let resolve!: (value: Response) => void;
    const fetcher = vi.spyOn(globalThis, "fetch").mockImplementation(
        () =>
            new Promise((done) => {
                resolve = done;
            }),
    );
    const table = fixture();
    const action = paginator(table).data[0]._actions![1];
    action.confirm = false;
    render(<Table table={table} />);
    const button = within(screen.getAllByRole("row")[1]).getByRole("button", {
        name: "Archive",
    });
    await user.click(button);
    expect(button).toBeDisabled();
    await user.click(button);
    expect(fetcher).toHaveBeenCalledOnce();
    await act(async () =>
        resolve(
            new Response(
                JSON.stringify({
                    errors: { keys: ["You cannot archive this record."] },
                }),
                {
                    status: 422,
                    headers: { "Content-Type": "application/json" },
                },
            ),
        ),
    );
    expect(await screen.findByRole("alert")).toHaveTextContent(
        "You cannot archive this record.",
    );
    expect(button).toBeEnabled();
});

it("escapes arbitrary cell values and supports custom rendering, empty states and disabled pagination", () => {
    const table = fixture();
    table.pagination = false;
    table.results = [
        {
            _primary_key: 1,
            _selection_key: "safe",
            name: "<script>alert(1)</script>",
            team: "Custom",
        },
    ];
    const { container, rerender } = render(
        <Table
            table={table}
            renderCell={({ column, value }) =>
                column.attribute === "team" ? (
                    <strong>{String(value)}</strong>
                ) : undefined
            }
        />,
    );
    expect(container.querySelector("script")).toBeNull();
    expect(screen.getByText("<script>alert(1)</script>")).toBeInTheDocument();
    expect(screen.getByText("Custom").tagName).toBe("STRONG");
    expect(screen.queryByRole("navigation")).not.toBeInTheDocument();
    const empty = fixture();
    paginator(empty).data = [];
    rerender(<Table table={empty} />);
    expect(
        screen.getByRole("heading", { name: "No matching people" }),
    ).toBeInTheDocument();
    expect(
        screen.getByText("Try a different name or clear your search."),
    ).toBeInTheDocument();
});

it("omits unauthorized actions and unsafe URLs and disables ineligible row commands", () => {
    const table = fixture();
    paginator(table).data[0]._actions![0].url = "javascript:alert(1)";
    paginator(table).data[0]._actions!.push({
        key: "secret",
        label: "Secret",
        type: "action",
        authorized: false,
    });
    render(<Table table={table} />);
    expect(screen.queryByText("Secret")).not.toBeInTheDocument();
    expect(screen.getAllByText("Edit")[0].closest("a")).toBeNull();
    expect(
        within(screen.getAllByRole("row")[2]).getByRole("button", {
            name: "Archive",
        }),
    ).toBeDisabled();
});

it("allows custom actions and follows relative links with Inertia", async () => {
    const user = userEvent.setup();
    const custom = vi.fn();
    const table = fixture();
    paginator(table).data[0]._actions!.push({
        key: "inspect",
        label: "Inspect",
        type: "custom",
    });
    render(<Table table={table} onCustomAction={custom} />);
    await user.click(screen.getByRole("button", { name: "Inspect" }));
    expect(custom).toHaveBeenCalledWith(
        expect.objectContaining({ key: "inspect" }),
        ["acct-001"],
    );
    await user.click(screen.getAllByRole("link", { name: "Edit" })[0]);
    expect(inertia.visit).toHaveBeenCalledWith(
        "http://localhost/records/1",
        expect.objectContaining({ method: "get" }),
    );
});

it("lets custom cells own their links and intentionally render empty content", () => {
    const table = fixture();
    paginator(table).data[0]._url = "/records/1";
    const { container } = render(
        <Table
            table={table}
            renderCell={({ column, value }) =>
                column.attribute === "name" ? (
                    <a href="mailto:test@example.com">{String(value)}</a>
                ) : column.attribute === "balance" ? null : undefined
            }
        />,
    );
    expect(container.querySelector("a a")).toBeNull();
    expect(screen.queryByText("$3,475.50")).not.toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Ada Lovelace" })).toHaveAttribute(
        "href",
        "mailto:test@example.com",
    );
});

it("confirms method-based link actions before navigating", async () => {
    const user = userEvent.setup();
    const table = fixture();
    paginator(table).data[0]._actions![0] = {
        key: "revoke",
        label: "Revoke",
        type: "link",
        url: { url: "/records/1/revoke", method: "delete" },
        confirm: true,
    };
    render(<Table table={table} />);
    await user.click(screen.getByRole("button", { name: "Revoke" }));
    expect(inertia.visit).not.toHaveBeenCalled();
    await user.click(
        within(screen.getByRole("dialog")).getByRole("button", {
            name: "Confirm",
        }),
    );
    expect(inertia.visit).toHaveBeenCalledWith(
        "http://localhost/records/1/revoke",
        expect.objectContaining({ method: "delete" }),
    );
});

it("keeps bulk actions in the header, requires selection, and supports keyboard and outside dismissal", async () => {
    const user = userEvent.setup();
    const table = fixture();
    table.actions[0].icon = "archive";
    table.actions[0].showLabel = false;
    table.actions.push(
        { key: "secret", label: "Secret", type: "action", authorized: false },
        { key: "hidden", label: "Hidden", type: "action", hidden: true },
        {
            key: "hidden-link",
            label: "Hidden link",
            type: "link",
            url: { url: "/hidden", hidden: true },
        },
    );
    render(
        <Table table={table} renderIcon={() => <svg aria-hidden="true" />} />,
    );
    const trigger = screen.getByText("Actions", { selector: "summary" });
    const menu = trigger.parentElement!;
    await user.click(trigger);
    expect(menu).toHaveAttribute("open");
    expect(
        within(menu).getByText("Select rows to apply an action"),
    ).toBeVisible();
    expect(
        within(menu).getByRole("button", { name: "Archive" }),
    ).toBeDisabled();
    expect(screen.queryByText("Secret")).not.toBeInTheDocument();
    expect(screen.queryByText("Hidden")).not.toBeInTheDocument();
    expect(screen.queryByText("Hidden link")).not.toBeInTheDocument();
    await user.keyboard("{Escape}");
    expect(menu).not.toHaveAttribute("open");
    expect(trigger).toHaveFocus();
    await user.click(rowCheckbox());
    await user.click(trigger);
    expect(within(menu).getByRole("button", { name: "Archive" })).toBeEnabled();
    expect(within(menu).getByText("Archive")).not.toHaveClass("tb-sr-only");
    await user.click(screen.getByRole("searchbox"));
    expect(menu).not.toHaveAttribute("open");
    await user.click(screen.getByRole("button", { name: "Clear selection" }));
    await user.click(trigger);
    expect(
        within(menu).getByRole("button", { name: "Archive" }),
    ).toBeDisabled();
});

it("omits the bulk menu when there are no usable actions or selection is unavailable", () => {
    const table = fixture();
    table.actions = [
        { key: "secret", label: "Secret", type: "action", hidden: true },
    ];
    const { rerender } = render(<Table table={table} />);
    expect(
        screen.queryByText("Actions", { selector: "summary" }),
    ).not.toBeInTheDocument();
    const unselectable = fixture();
    unselectable.selectable = false;
    rerender(<Table table={unselectable} />);
    expect(
        screen.queryByText("Actions", { selector: "summary" }),
    ).not.toBeInTheDocument();
});

it("renders accessible outlined row icons and keeps labels visible when icons are unavailable or in menus", async () => {
    const user = userEvent.setup();
    const table = fixture();
    const row = paginator(table).data[0];
    Object.assign(row._actions![0], {
        icon: "edit",
        showLabel: false,
        variant: "outline",
        variantColor: "amber",
    });
    Object.assign(row._actions![1], {
        icon: "archive",
        showLabel: false,
        variant: "outline",
        variantColor: "red",
    });
    table.columns.push({
        attribute: "actions",
        header: "Actions",
        type: "action",
        alignment: "right",
    });
    const renderIcon = () => <svg aria-hidden="true" />;
    const { rerender } = render(
        <Table table={table} renderIcon={renderIcon} />,
    );
    expect(
        screen.getAllByRole("columnheader", { name: "Actions" }),
    ).toHaveLength(1);
    const actions = within(screen.getAllByRole("row")[1]);
    const edit = actions.getByRole("link", { name: "Edit" });
    expect(edit).toHaveClass("tb-action-icon");
    expect(edit).toHaveAttribute("title", "Edit");
    expect(edit).toHaveAttribute("data-variant", "outline");
    expect(edit).toHaveAttribute("data-color", "amber");
    expect(actions.getByRole("button", { name: "Archive" })).toHaveAttribute(
        "data-color",
        "red",
    );
    expect(actions.getByText("Edit")).toHaveClass("tb-sr-only");
    rerender(<Table table={table} />);
    expect(actions.getByText("Edit")).not.toHaveClass("tb-sr-only");
    table.columns.at(-1)!.meta = { asDropdown: true };
    rerender(<Table table={table} renderIcon={renderIcon} />);
    await user.click(actions.getByText("Actions", { selector: "summary" }));
    expect(actions.getByText("Edit")).not.toHaveClass("tb-sr-only");
});

it("can render a table during SSR without a browser", () => {
    vi.stubGlobal("window", undefined);
    try {
        expect(renderToString(<Table table={fixture()} />)).toContain(
            "Ada Lovelace",
        );
    } finally {
        vi.unstubAllGlobals();
    }
});
