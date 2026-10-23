import { expect, test } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";
import { readFile } from "node:fs/promises";

test.beforeEach(async ({ page }) => {
    await page.goto("/customers");
    await page.getByRole("button", { name: "Reset demo" }).click();
    await expect(
        page.getByRole("button", { name: "Reset demo" }),
    ).toBeEnabled();
    await expect(page.getByText("Page 1 of 3")).toBeVisible();
});

test("queries SQLite through search, sort, visibility, pagination and browser history", async ({
    page,
}) => {
    await page.getByRole("searchbox").fill("Orbit");
    await expect(page.getByText("Grace Chen", { exact: true })).toBeVisible();
    await expect(page.getByText("Amara Okafor", { exact: true })).toBeHidden();
    await page.getByRole("searchbox").clear();
    await expect(page.getByText("Amara Okafor", { exact: true })).toBeVisible();
    await page.getByRole("button", { name: "Revenue", exact: true }).click();
    await expect(
        page.getByRole("columnheader", { name: "Revenue" }),
    ).toHaveAttribute("aria-sort", "ascending");
    await page.getByRole("button", { name: "Next page" }).click();
    await expect(page.getByText("Page 2 of 3")).toBeVisible();
    await page.goBack();
    await expect(page.getByText("Page 1 of 3")).toBeVisible();
    await page.getByText("Columns", { exact: true }).click();
    await page.getByRole("checkbox", { name: "Email", exact: true }).click();
    await expect(
        page.getByRole("checkbox", { name: "Email", exact: true }),
    ).toBeChecked();
    await expect(
        page.getByRole("columnheader", { name: "Email" }),
    ).toBeVisible();
    await page.getByText("Columns", { exact: true }).click();
    await page.getByRole("searchbox").fill("no-such-customer");
    await expect(
        page.getByRole("heading", { name: "No matching customers" }),
    ).toBeVisible();
});

test("persists actions and downloads real filtered and selected exports", async ({
    page,
}) => {
    const row = page.getByRole("row").filter({ hasText: "Amara Okafor" });
    await row.getByRole("button", { name: "Deactivate" }).click();
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Deactivate", exact: true })
        .click();
    await expect(row.getByText("Inactive", { exact: true })).toBeVisible();
    await page.reload();
    await expect(row.getByText("Inactive", { exact: true })).toBeVisible();
    await page.getByRole("searchbox").fill("Amara");
    await expect(page.getByText("Page 1 of 1")).toBeVisible();
    await page.getByText("Export", { exact: true }).click();
    const csvDownload = page.waitForEvent("download");
    await page
        .getByRole("button", { name: "Download CSV", exact: true })
        .click();
    const csv = await csvDownload;
    expect(csv.suggestedFilename()).toBe("customers.csv");
    const contents = await readFile((await csv.path())!, "utf8");
    expect(contents).toContain("Amara Okafor");
    expect(contents).not.toContain("Ben Carter");
    await page.getByText("Export", { exact: true }).click();
    await row.getByRole("checkbox").check();
    await page.getByText("Export", { exact: true }).click();
    const excelDownload = page.waitForEvent("download");
    await page.getByRole("button", { name: /Export selected/ }).click();
    const excel = await excelDownload;
    expect(excel.suggestedFilename()).toBe("customers-selected.xlsx");
    expect(
        (await readFile((await excel.path())!)).subarray(0, 2).toString(),
    ).toBe("PK");
    await page.getByText("Export", { exact: true }).click();
    await row.getByRole("button", { name: "Delete", exact: true }).click();
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Delete", exact: true })
        .click();
    await expect(row).toHaveCount(0);
    await page.getByRole("button", { name: "Reset demo" }).click();
    await expect(page.getByText("Amara Okafor", { exact: true })).toBeVisible();
});

test("switches tables, runs bulk actions and supports dark and narrow screens", async ({
    page,
}) => {
    await page.getByRole("link", { name: "Invoices", exact: true }).click();
    await expect(
        page.getByRole("heading", { name: "Invoices", exact: true }),
    ).toBeVisible();
    const pending = page.getByRole("row").filter({ hasText: "Northstar" });
    await pending.getByRole("checkbox").check();
    await page.locator(".tb-actions-menu summary").click();
    await page
        .locator(".tb-actions-menu")
        .getByRole("button", { name: "Mark paid", exact: true })
        .click();
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Mark paid", exact: true })
        .click();
    await expect(pending.getByText("Paid", { exact: true })).toBeVisible();
    for (const theme of ["light", "dark"] as const) {
        if (theme === "dark")
            await page.getByRole("button", { name: "Dark theme" }).click();
        expect(
            (
                await new AxeBuilder({ page })
                    .withTags(["wcag2a", "wcag2aa"])
                    .analyze()
            ).violations,
        ).toEqual([]);
    }
    for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 844 });
        await expect(page.getByRole("searchbox")).toBeVisible();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
    }
});
