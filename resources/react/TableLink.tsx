import { router } from "@inertiajs/react";
import type { ReactNode } from "react";
import { safeHref } from "./navigation";
import type { JsonValue, TableUrl } from "./types";

export function followTableLink(
    url: TableUrl | string,
    data?: Record<string, JsonValue> | [],
) {
    const definition = typeof url === "string" ? { url } : url;
    const href = safeHref(definition.url);
    if (!href || definition.hidden || definition.disabled) return;
    const target = new URL(href, window.location.href);
    const method = definition.method ?? "get";
    if (method !== "get" && target.origin !== window.location.origin)
        throw new Error("Table commands must use the application origin.");
    if (method === "get" && (definition.download || definition.target)) {
        const anchor = document.createElement("a");
        anchor.href = href;
        anchor.target = definition.target ?? "_self";
        anchor.rel = "noopener noreferrer";
        if (definition.download) anchor.download = "";
        document.body.append(anchor);
        anchor.click();
        anchor.remove();
    } else if (target.origin === window.location.origin) {
        router.visit(target.href, {
            method,
            data: Array.isArray(data) ? {} : data,
            preserveState: definition.preserveState ?? false,
            preserveScroll: definition.preserveScroll ?? false,
        });
    } else {
        window.location.assign(href);
    }
}

export function TableLink({
    url,
    children,
    className,
    data,
    title,
    variant,
    color,
}: {
    url: TableUrl | string;
    children: ReactNode;
    className?: string;
    data?: Record<string, JsonValue> | [];
    title?: string;
    variant?: string | null;
    color?: string | null;
}) {
    const definition: TableUrl = typeof url === "string" ? { url } : url;
    const href = safeHref(definition.url);
    if (definition.hidden) return null;
    if (!href || definition.disabled)
        return (
            <span
                className={className}
                aria-disabled="true"
                title={title}
                data-variant={variant}
                data-color={color}
            >
                {children}
            </span>
        );
    const method = definition.method ?? "get";

    if (method !== "get") {
        return (
            <button
                type="button"
                className={className}
                title={title}
                data-variant={variant}
                data-color={color}
                onClick={() => followTableLink(definition, data)}
            >
                {children}
            </button>
        );
    }
    return (
        <a
            href={href}
            className={className}
            title={title}
            data-variant={variant}
            data-color={color}
            target={definition.target ?? undefined}
            rel={
                definition.target === "_blank"
                    ? "noopener noreferrer"
                    : undefined
            }
            download={definition.download || undefined}
            onClick={(event) => {
                if (
                    event.defaultPrevented ||
                    event.button !== 0 ||
                    event.metaKey ||
                    event.ctrlKey ||
                    event.shiftKey ||
                    event.altKey ||
                    definition.target ||
                    definition.download
                )
                    return;
                const target = new URL(href, window.location.href);
                if (
                    target.origin === window.location.origin &&
                    ["http:", "https:"].includes(target.protocol)
                ) {
                    event.preventDefault();
                    followTableLink(definition, data);
                }
            }}
        >
            {children}
        </a>
    );
}
