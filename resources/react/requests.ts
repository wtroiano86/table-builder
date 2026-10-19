import type { JsonValue } from "./types";

export class TableRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = "TableRequestError";
    }
}

function csrfHeaders(): Record<string, string> {
    const cookie = document.cookie
        .split("; ")
        .find((value) => value.startsWith("XSRF-TOKEN="));
    if (cookie) {
        try {
            return {
                "X-XSRF-TOKEN": decodeURIComponent(
                    cookie.slice("XSRF-TOKEN=".length),
                ),
            };
        } catch {
            /* Use the application's meta token if the cookie is malformed. */
        }
    }
    const token = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;
    return token ? { "X-CSRF-TOKEN": token } : {};
}

function downloadFilename(disposition: string | null): string {
    const encoded = disposition?.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    let name =
        disposition
            ?.match(/filename="([^"]+)"|filename=([^;]+)/i)
            ?.slice(1)
            .find(Boolean) ?? "export.xlsx";
    if (encoded) {
        try {
            name = decodeURIComponent(encoded);
        } catch {
            /* Retain the fallback filename. */
        }
    }
    return name.replace(/[\\/\x00-\x1f\x7f]/g, "_").trim() || "export.xlsx";
}

/** JSON commands and binary exports are regular HTTP, not Inertia page visits. */
export async function tableRequest(
    endpoint: string,
    data: Record<string, JsonValue>,
    signal?: AbortSignal,
): Promise<{ data: unknown; downloaded: boolean }> {
    const url = new URL(endpoint, window.location.href);
    if (
        url.origin !== window.location.origin ||
        !["http:", "https:"].includes(url.protocol)
    ) {
        throw new TableRequestError(
            "Table endpoints must use the application origin.",
            0,
        );
    }
    const response = await fetch(url.href, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...csrfHeaders(),
        },
        body: JSON.stringify(data),
        signal,
        redirect: "error",
    });
    const isJson = response.headers.get("content-type")?.includes("json");
    if (!response.ok) {
        const payload: unknown = isJson
            ? await response.json().catch(() => ({}))
            : {};
        const body =
            payload !== null && typeof payload === "object"
                ? (payload as Record<string, unknown>)
                : {};
        const errors: Record<string, string[]> = {};
        if (body.errors && typeof body.errors === "object") {
            for (const [key, value] of Object.entries(body.errors)) {
                errors[key] = Array.isArray(value)
                    ? value.filter(
                          (item): item is string => typeof item === "string",
                      )
                    : typeof value === "string"
                      ? [value]
                      : [];
            }
        }
        const defaultMessage =
            response.status === 419
                ? "Your session expired. Refresh the page and try again."
                : `The request could not be completed (${response.status}).`;
        throw new TableRequestError(
            typeof body.message === "string" ? body.message : defaultMessage,
            response.status,
            errors,
        );
    }
    if (isJson) {
        const body: unknown = await response.json();
        if (
            body &&
            typeof body === "object" &&
            "ok" in body &&
            body.ok === false
        ) {
            throw new TableRequestError(
                "message" in body && typeof body.message === "string"
                    ? body.message
                    : "The request could not be completed.",
                response.status,
            );
        }
        return { data: body, downloaded: false };
    }
    // Do not turn a login/error HTML page into a spreadsheet download.
    if (response.headers.get("content-type")?.includes("text/html")) {
        throw new TableRequestError(
            "The server returned a page instead of table data. Refresh the page and try again.",
            response.status,
        );
    }
    const blobUrl = URL.createObjectURL(await response.blob());
    const anchor = document.createElement("a");
    anchor.href = blobUrl;
    anchor.download = downloadFilename(
        response.headers.get("content-disposition"),
    );
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
    // Give browsers time to begin consuming the object URL.
    window.setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
    return { data: null, downloaded: true };
}
