import { Head } from "@inertiajs/react";
import { Table } from "./Table";
import type { TablePageProps, TableRow } from "./types";

export function TablePage<Row extends TableRow = TableRow>({
    title = "Records",
    description,
    headerActions,
    ...props
}: TablePageProps<Row>) {
    return (
        <main className="tb-page">
            <Head title={title} />
            <header className="tb-page-header">
                <div>
                    <p className="tb-eyebrow">Overview</p>
                    <h1>{title}</h1>
                    {description && (
                        <p className="tb-description">{description}</p>
                    )}
                </div>
                {headerActions && (
                    <div className="tb-page-actions">{headerActions}</div>
                )}
            </header>
            <Table caption={title} {...props} />
        </main>
    );
}
