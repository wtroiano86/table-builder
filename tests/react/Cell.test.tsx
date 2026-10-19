import { render, screen } from "@testing-library/react";
import { expect, it } from "vitest";
import { Cell } from "../../resources/react/Cell";

it("formats PHP date tokens in the declared timezone and retains escaped literals", () => {
    render(
        <Cell
            value="2026-01-02T01:30:00Z"
            row={{}}
            column={{
                attribute: "joined",
                header: "Joined",
                type: "date-time",
                meta: {
                    timezone: "America/New_York",
                    format: "Y-m-d \\a\\t H:i",
                },
            }}
            index={0}
        />,
    );
    expect(screen.getByText("2026-01-01 at 20:30")).toBeInTheDocument();
});

it("honors numeric precision, separators and prefixes", () => {
    render(
        <Cell
            value={12345.5}
            row={{}}
            column={{
                attribute: "amount",
                header: "Amount",
                type: "numeric",
                meta: {
                    precision: 2,
                    thousandsSeparator: ".",
                    decimalSeparator: ",",
                    prefix: "€",
                },
            }}
            index={0}
        />,
    );
    expect(screen.getByText("€12.345,50")).toBeInTheDocument();
});

it("renders null boolean labels and offsets serial numbers", () => {
    const { rerender } = render(
        <Cell
            value={null}
            row={{}}
            column={{
                attribute: "active",
                header: "Active",
                type: "boolean",
                meta: { nullLabel: "Unknown" },
            }}
            index={0}
        />,
    );
    expect(screen.getByText("Unknown")).toBeInTheDocument();
    rerender(
        <Cell
            value={null}
            row={{}}
            column={{ attribute: "serial", header: "#", type: "serial-number" }}
            index={2}
            offset={20}
        />,
    );
    expect(screen.getByText("23")).toBeInTheDocument();
});

it("uses the host icon renderer for image placeholders and rejects unsafe image URLs", () => {
    const row = { _column_images: { avatar: { url: null, icon: "person" } } };
    const { rerender } = render(
        <Cell
            value={null}
            row={row}
            column={{ attribute: "avatar", header: "Avatar", type: "image" }}
            index={0}
            renderIcon={(name) => <span>{name} icon</span>}
        />,
    );
    expect(screen.getByText("person icon")).toBeInTheDocument();
    rerender(
        <Cell
            value={null}
            row={{ _column_images: { avatar: { url: "javascript:alert(1)" } } }}
            column={{
                attribute: "avatar",
                header: "Avatar",
                type: "image",
                meta: { placeholder: "No photo" },
            }}
            index={0}
        />,
    );
    expect(screen.queryByRole("img")).not.toBeInTheDocument();
    expect(screen.getByText("No photo")).toBeInTheDocument();
});
