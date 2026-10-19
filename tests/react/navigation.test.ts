import { describe, expect, it } from "vitest";
import {
    exportState,
    keyIdentity,
    safeHref,
    selectionKey,
    tableUrl,
} from "../../resources/react/navigation";
import { fixture } from "./fixture";

describe("table URL contract", () => {
    it("preserves other namespaces and explicitly resets named defaults and pagination", () => {
        const table = fixture();
        const result = new URL(
            tableUrl(
                table,
                "https://app.test/records?tab=activity&people[page]=5&people[cursor]=old&people[columns][0]=name&orders[page]=3#results",
                { search: "", sort: "", columns: [], sticky: [] },
            ),
            "https://app.test",
        );
        expect(result.pathname).toBe("/records");
        expect(result.hash).toBe("#results");
        expect(result.searchParams.get("tab")).toBe("activity");
        expect(result.searchParams.get("orders[page]")).toBe("3");
        for (const key of ["search", "sort", "columns", "sticky", "cursor"])
            expect(result.searchParams.get(`people[${key}]`)).toBe("");
        expect(result.searchParams.get("people[page]")).toBe("1");
        expect(result.searchParams.has("people[columns][0]")).toBe(false);
    });

    it("encodes hidden columns rather than the visibility map for queries and exports", () => {
        const table = fixture();
        table.state.columns = { name: true, team: false, code: false };
        const result = new URL(
            tableUrl(table, "/records", { sort: "name", direction: "desc" }),
            "http://localhost",
        );
        expect(result.searchParams.getAll("people[columns][]")).toEqual([
            "team",
            "code",
        ]);
        expect(exportState(table).columns).toEqual(["team", "code"]);
        expect(exportState(table).direction).toBe("asc");
    });

    it("supports unnamed tables and custom query keys without changing other table parameters", () => {
        const table = fixture();
        table.meta.queryString = {
            namespace: null,
            page: "page",
            search: "search",
            sort: "sort",
            direction: "direction",
            columns: "columns",
            sticky: "sticky",
            perPage: "perPage",
            cursor: "cursor",
        };
        const result = new URL(
            tableUrl(table, "/records?people[page]=5&page=3", {
                search: "Ada & Grace",
                perPage: 10,
            }),
            "http://localhost",
        );
        expect(result.searchParams.get("search")).toBe("Ada & Grace");
        expect(result.searchParams.get("perPage")).toBe("10");
        expect(result.searchParams.get("page")).toBe("1");
        expect(result.searchParams.get("people[page]")).toBe("5");
    });
});

it("retains raw zero and string identities and rejects invalid keys", () => {
    expect(selectionKey({ _selection_key: 0 }, "_selection_key")).toBe(0);
    expect(selectionKey({ _selection_key: "uuid" }, "_selection_key")).toBe(
        "uuid",
    );
    expect(keyIdentity(1)).not.toBe(keyIdentity("1"));
    for (const value of [
        null,
        undefined,
        "",
        " ",
        true,
        {},
        [],
        Infinity,
        1.5,
        Number.MAX_SAFE_INTEGER + 1,
    ])
        expect(selectionKey({ key: value }, "key")).toBeNull();
    expect(selectionKey({ id: 1 }, null)).toBeNull();
});

it("rejects executable URL schemes while preserving application and external links", () => {
    for (const value of [
        "javascript:alert(1)",
        "data:text/html,test",
        "vbscript:test",
        "",
        null,
    ])
        expect(safeHref(value)).toBeNull();
    for (const value of [
        "/records/1",
        "https://example.com",
        "mailto:hi@example.com",
        "tel:+15555550100",
    ])
        expect(safeHref(value)).toBe(value);
});
