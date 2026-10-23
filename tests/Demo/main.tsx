import { createInertiaApp, Link, router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import { TablePage, type TablePageProps } from "../../resources/react";
import { tableRequest } from "../../resources/react/requests";
import "./demo.css";

function DemoPage({ demo, ...props }: TablePageProps & { demo: string }) {
    const [theme, setTheme] = useState<"light" | "dark">(() => {
        const current = document.documentElement.dataset.theme;
        if (current === "light" || current === "dark") return current;
        return window.matchMedia("(prefers-color-scheme: dark)").matches
            ? "dark"
            : "light";
    });
    const [resetting, setResetting] = useState(false);
    const [error, setError] = useState("");
    useEffect(() => {
        document.documentElement.dataset.theme = theme;
    }, [theme]);

    async function reset() {
        setResetting(true);
        setError("");
        try {
            await tableRequest("/demo/reset", {});
            router.visit(`/${demo}`, {
                preserveState: false,
                onFinish: () => setResetting(false),
            });
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : "Reset failed.");
            setResetting(false);
        }
    }

    return (
        <>
            <nav className="demo-nav" aria-label="Demo navigation">
                <Link className="demo-brand" href="/customers">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="16" rx="3" />
                        <path d="M3 10h18M3 15h18M10 10v10" />
                    </svg>
                    Table Builder
                    <span className="demo-tag">LIVE DEMO</span>
                </Link>
                <div className="demo-tabs">
                    {["customers", "invoices"].map((name) => (
                        <Link
                            key={name}
                            href={`/${name}`}
                            aria-current={demo === name ? "page" : undefined}
                        >
                            {name === "customers" ? "Customers" : "Invoices"}
                        </Link>
                    ))}
                </div>
                <button
                    className="demo-theme"
                    onClick={() =>
                        setTheme(theme === "light" ? "dark" : "light")
                    }
                >
                    {theme === "light" ? "Dark theme" : "Light theme"}
                </button>
            </nav>
            <TablePage
                {...props}
                headerActions={
                    <button
                        className="demo-reset"
                        disabled={resetting}
                        onClick={() => void reset()}
                    >
                        {resetting ? "Resetting…" : "Reset demo"}
                    </button>
                }
                renderIcon={(name) => {
                    const path = {
                        trash: "M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7",
                        "user-minus":
                            "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m11-14a4 4 0 1 1-8 0 4 4 0 0 1 8 0m3 5h6",
                        check: "m5 12 4 4L19 6",
                    }[name];
                    return path ? (
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.6"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            aria-hidden="true"
                        >
                            <path d={path} />
                        </svg>
                    ) : undefined;
                }}
            />
            <footer className="demo-footer">
                <span>Table Builder / Laravel + Inertia + React</span>
                <span>Sample data. Real tables. Make yourself at home.</span>
                {error && <p role="alert">{error}</p>}
            </footer>
        </>
    );
}

const response = await fetch(
    window.location.pathname + window.location.search,
    {
        headers: { "X-Inertia": "true", Accept: "application/json" },
    },
);
if (!response.ok) {
    document.getElementById("app")!.textContent =
        "The demo could not load. Check the terminal running npm run demo, then reload.";
    throw new Error(`Demo request failed (${response.status}).`);
}
await createInertiaApp({
    page: await response.json(),
    resolve: () => DemoPage,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: false,
});
