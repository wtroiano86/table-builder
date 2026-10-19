import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { TablePage, type TablePageProps } from "../../resources/react";

function PreviewPage(props: TablePageProps) {
    return (
        <TablePage
            {...props}
            renderIcon={(name) => {
                const path = {
                    trash: "M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7",
                    "user-minus":
                        "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m11-14a4 4 0 1 1-8 0 4 4 0 0 1 8 0m3 5h6",
                }[name];
                if (!path) return undefined;
                return (
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
                );
            }}
        />
    );
}

const page = await fetch("/__page" + window.location.search).then((response) =>
    response.json(),
);
await createInertiaApp({
    page,
    resolve: () => PreviewPage,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: false,
});
