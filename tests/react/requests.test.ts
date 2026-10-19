import { beforeEach, expect, it, vi } from "vitest";
import {
    tableRequest,
    TableRequestError,
} from "../../resources/react/requests";

beforeEach(() => {
    document.cookie = "XSRF-TOKEN=; Max-Age=0";
    document.head.innerHTML = "";
});

it("sends authenticated JSON commands with the fresh Laravel XSRF cookie and no Inertia header", async () => {
    document.cookie = "XSRF-TOKEN=encrypted%3Dtoken";
    const fetcher = vi.spyOn(globalThis, "fetch").mockImplementation(
        async () =>
            new Response(JSON.stringify({ ok: true }), {
                headers: { "Content-Type": "application/json" },
            }),
    );
    const result = await tableRequest("/table-builder/actions", {
        table: "registry:people",
        keys: [0, "uuid"],
    });
    expect(result).toEqual({ data: { ok: true }, downloaded: false });
    expect(fetcher).toHaveBeenCalledWith(
        "http://localhost/table-builder/actions",
        expect.objectContaining({
            credentials: "same-origin",
            redirect: "error",
            headers: expect.objectContaining({
                "X-XSRF-TOKEN": "encrypted=token",
                Accept: "application/json",
            }),
        }),
    );
    expect(fetcher.mock.calls[0][1]?.headers).not.toHaveProperty("X-Inertia");
    document.cookie = "XSRF-TOKEN=new-token";
    await tableRequest("/table-builder/actions", {});
    expect(fetcher.mock.lastCall?.[1]?.headers).toHaveProperty(
        "X-XSRF-TOKEN",
        "new-token",
    );
});

it("supports meta CSRF tokens and surfaces field errors and expired sessions", async () => {
    document.head.innerHTML =
        '<meta name="csrf-token" content="trusted-token">';
    const fetcher = vi.spyOn(globalThis, "fetch").mockResolvedValue(
        new Response(
            JSON.stringify({
                message: "Invalid selection.",
                errors: { keys: ["Select at least one record."] },
            }),
            {
                status: 422,
                headers: { "Content-Type": "application/json" },
            },
        ),
    );
    await expect(
        tableRequest("/table-builder/actions", {}),
    ).rejects.toMatchObject({
        status: 422,
        errors: { keys: ["Select at least one record."] },
    });
    expect(fetcher.mock.lastCall?.[1]?.headers).toHaveProperty(
        "X-CSRF-TOKEN",
        "trusted-token",
    );
    fetcher.mockResolvedValue(new Response("Page expired", { status: 419 }));
    await expect(tableRequest("/table-builder/actions", {})).rejects.toThrow(
        "Your session expired",
    );
});

it("never sends CSRF credentials to a different origin", async () => {
    const fetcher = vi.spyOn(globalThis, "fetch");
    await expect(
        tableRequest("https://other.test/action", {}),
    ).rejects.toBeInstanceOf(TableRequestError);
    expect(fetcher).not.toHaveBeenCalled();
});

it("downloads binary responses with UTF-8 filenames and releases their object URLs", async () => {
    vi.useFakeTimers();
    const create = vi.fn().mockReturnValue("blob:download");
    const revoke = vi.fn();
    vi.stubGlobal(
        "URL",
        class extends URL {
            static createObjectURL = create;
            static revokeObjectURL = revoke;
        },
    );
    const click = vi
        .spyOn(HTMLAnchorElement.prototype, "click")
        .mockImplementation(function (this: HTMLAnchorElement) {
            expect(this.download).toBe("people résumé.xlsx");
            expect(this.href).toBe("blob:download");
        });
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
        new Response("workbook", {
            headers: {
                "Content-Type":
                    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                "Content-Disposition":
                    "attachment; filename*=UTF-8''people%20r%C3%A9sum%C3%A9.xlsx",
            },
        }),
    );
    expect((await tableRequest("/table-builder/exports", {})).downloaded).toBe(
        true,
    );
    expect(click).toHaveBeenCalledOnce();
    expect(document.querySelector("a")).toBeNull();
    await vi.advanceTimersByTimeAsync(1000);
    expect(revoke).toHaveBeenCalledWith("blob:download");
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

it("handles queued JSON exports and rejects disguised success errors or HTML pages", async () => {
    const fetcher = vi
        .spyOn(globalThis, "fetch")
        .mockResolvedValue(
            new Response(
                JSON.stringify({ status: "processing", message: "Queued." }),
                { headers: { "Content-Type": "application/json" } },
            ),
        );
    expect((await tableRequest("/table-builder/exports", {})).downloaded).toBe(
        false,
    );
    fetcher.mockResolvedValue(
        new Response(
            JSON.stringify({ ok: false, message: "Storage failed." }),
            { headers: { "Content-Type": "application/json" } },
        ),
    );
    await expect(tableRequest("/table-builder/exports", {})).rejects.toThrow(
        "Storage failed.",
    );
    fetcher.mockResolvedValue(
        new Response("<html>Log in</html>", {
            headers: { "Content-Type": "text/html" },
        }),
    );
    await expect(tableRequest("/table-builder/exports", {})).rejects.toThrow(
        "server returned a page",
    );
});

it("handles empty and non-object error bodies without losing the HTTP status", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
        new Response("null", {
            status: 403,
            headers: { "Content-Type": "application/json" },
        }),
    );
    await expect(
        tableRequest("/table-builder/actions", {}),
    ).rejects.toMatchObject({
        status: 403,
        message: "The request could not be completed (403).",
    });
});
