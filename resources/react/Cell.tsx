import type { ReactNode } from "react";
import { safeHref } from "./navigation";
import type { CellContext, TableImage } from "./types";

export function plainText(value: unknown): string {
    if (value === null || value === undefined) return "";
    if (typeof value === "object") return JSON.stringify(value);
    return String(value);
}

function numeric(value: unknown, meta: Record<string, unknown>): string {
    const number =
        typeof value === "number" || typeof value === "string"
            ? Number(value)
            : NaN;
    if (!Number.isFinite(number)) return plainText(value);
    const precision =
        typeof meta.precision === "number"
            ? Math.max(0, Math.min(20, Math.trunc(meta.precision)))
            : undefined;
    const parts = new Intl.NumberFormat("en-US", {
        minimumFractionDigits: precision,
        maximumFractionDigits: precision ?? 20,
    }).formatToParts(number);
    return `${plainText(meta.prefix)}${parts.map((part) => (part.type === "group" ? plainText(meta.thousandsSeparator ?? ",") : part.type === "decimal" ? plainText(meta.decimalSeparator ?? ".") : part.value)).join("")}${plainText(meta.suffix)}`;
}

/** PHP-style date tokens commonly used by DateColumn/DateTimeColumn. */
function dateText(
    value: unknown,
    meta: Record<string, unknown>,
    withTime: boolean,
): string {
    if (typeof value !== "string" && typeof value !== "number")
        return plainText(value);
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return plainText(value);
    try {
        const zone = typeof meta.timezone === "string" ? meta.timezone : "UTC";
        const parts = new Intl.DateTimeFormat("en-US", {
            timeZone: zone,
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
            hourCycle: "h23",
        }).formatToParts(date);
        const fields = Object.fromEntries(
            parts.map((part) => [part.type, part.value]),
        );
        const short = (options: Intl.DateTimeFormatOptions) =>
            new Intl.DateTimeFormat("en-US", {
                timeZone: zone,
                ...options,
            }).format(date);
        const hour = Number(fields.hour);
        const tokens: Record<string, string> = {
            Y: fields.year,
            y: fields.year.slice(-2),
            m: fields.month,
            n: String(Number(fields.month)),
            d: fields.day,
            j: String(Number(fields.day)),
            H: fields.hour,
            G: String(hour),
            h: String(hour % 12 || 12).padStart(2, "0"),
            g: String(hour % 12 || 12),
            i: fields.minute,
            s: fields.second,
            a: hour < 12 ? "am" : "pm",
            A: hour < 12 ? "AM" : "PM",
            M: short({ month: "short" }),
            F: short({ month: "long" }),
            D: short({ weekday: "short" }),
            l: short({ weekday: "long" }),
        };
        const format =
            typeof meta.format === "string"
                ? meta.format
                : withTime
                  ? "M j, Y H:i"
                  : "M j, Y";
        return format.replace(
            /\\(.)|[YymndjHGhgisAaMFDl]/g,
            (token, escaped: string | undefined) =>
                escaped ?? tokens[token] ?? token,
        );
    } catch {
        return plainText(value);
    }
}

function Images({
    image,
    meta,
    renderIcon,
}: {
    image: TableImage;
    meta: Record<string, unknown>;
    renderIcon?: (name: string) => ReactNode;
}) {
    const urls = image.url ? [image.url] : (image.urls ?? []);
    const visible = urls
        .filter((url) => {
            const href = safeHref(url);
            return href !== null && !/^(mailto|tel):/i.test(href);
        })
        .slice(0, Math.max(0, image.limit ?? urls.length));
    if (visible.length === 0)
        return image.icon && renderIcon ? (
            renderIcon(image.icon)
        ) : (
            <span className="tb-muted">
                {plainText(meta.placeholder) || "—"}
            </span>
        );
    const size =
        (
            { small: 24, medium: 32, large: 40, "extra-large": 48 } as Record<
                string,
                number
            >
        )[image.size ?? "medium"] ?? 32;
    return (
        <span className="tb-images">
            {visible.map((url, index) => (
                <img
                    key={`${url}:${index}`}
                    src={url}
                    alt={image.alt || plainText(meta.alt)}
                    title={image.title || undefined}
                    loading="lazy"
                    width={image.width ?? size}
                    height={image.height ?? size}
                    className={`tb-image ${image.class ?? ""}`}
                    style={{
                        width:
                            image.width ??
                            (meta.imageWidth as string | number | undefined) ??
                            size,
                        height:
                            image.height ??
                            (meta.imageHeight as string | number | undefined) ??
                            size,
                        borderRadius:
                            image.rounded || meta.shape === "circle"
                                ? "50%"
                                : meta.shape === "rounded"
                                  ? 6
                                  : 0,
                        objectFit: meta.fit === "contain" ? "contain" : "cover",
                    }}
                />
            ))}
        </span>
    );
}

export function Cell({
    value,
    row,
    column,
    index,
    offset = 0,
    renderIcon,
}: CellContext & {
    offset?: number;
    renderIcon?: (name: string) => ReactNode;
}) {
    const meta = column.meta ?? {};
    const image = (
        row._column_images as Record<string, TableImage> | undefined
    )?.[column.attribute];
    let content: ReactNode;
    if (column.type === "serial-number" || meta.serialNumber) {
        content = offset + index + 1;
    } else if (value === null || value === undefined || value === "") {
        content = (
            <span className="tb-muted">
                {plainText(
                    meta.placeholder ??
                        (column.type === "boolean" ? meta.nullLabel : null),
                ) || "—"}
            </span>
        );
    } else if (column.type === "boolean") {
        const enabled = value === true || value === 1 || value === "1";
        const icon = meta[enabled ? "trueIcon" : "falseIcon"];
        content = (
            <span className="tb-boolean" data-value={enabled}>
                {typeof icon === "string" && renderIcon?.(icon)}
                {plainText(
                    meta[enabled ? "trueLabel" : "falseLabel"] ??
                        (enabled ? "Yes" : "No"),
                )}
            </span>
        );
    } else if (column.type === "badge") {
        content = (Array.isArray(value) ? value : [value]).map((item, key) => {
            const label = plainText(item);
            const color =
                (meta.colors as Record<string, string> | undefined)?.[label] ??
                "neutral";
            const icon =
                (meta.icons as Record<string, string> | undefined)?.[label] ??
                meta.icon;
            return (
                <span
                    key={key}
                    className="tb-badge"
                    data-color={color}
                    data-variant={plainText(meta.variant)}
                >
                    {typeof icon === "string" && renderIcon?.(icon)}
                    {label}
                </span>
            );
        });
    } else if (column.type === "numeric") {
        content = numeric(value, meta);
    } else if (column.type === "date" || column.type === "date-time") {
        content = (
            <time dateTime={plainText(value)}>
                {dateText(value, meta, column.type === "date-time")}
            </time>
        );
    } else {
        content = plainText(value);
    }
    if (column.type === "image" && image)
        return <Images image={image} meta={meta} renderIcon={renderIcon} />;
    return (
        <span className="tb-cell-content" data-image-position={image?.position}>
            {image && (
                <Images image={image} meta={meta} renderIcon={renderIcon} />
            )}
            {content}
        </span>
    );
}
