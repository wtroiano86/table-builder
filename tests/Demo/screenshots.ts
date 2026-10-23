import { expect, test } from "@playwright/test";
import { mkdir } from "node:fs/promises";

test("capture the running Laravel demo for the documentation", async ({
    page,
}) => {
    await mkdir("docs/images", { recursive: true });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/customers");
    await page.getByRole("button", { name: "Reset demo" }).click();
    await expect(page.getByText("Page 1 of 3")).toBeVisible();
    await expect(
        page.getByRole("button", { name: "Reset demo" }),
    ).toBeEnabled();

    async function capture(name: string) {
        await page.evaluate(async () => {
            await document.fonts.ready;
            if (document.activeElement instanceof HTMLElement)
                document.activeElement.blur();
        });
        await page.screenshot({
            path: `docs/images/${name}.png`,
            fullPage: true,
            animations: "disabled",
        });
    }

    await capture("customers-light");
    await page.getByRole("button", { name: "Dark theme" }).click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await capture("customers-dark");
    await page.getByRole("button", { name: "Light theme" }).click();
    await page.getByRole("link", { name: "Invoices", exact: true }).click();
    await expect(
        page.getByText("INV-2026-1048", { exact: true }),
    ).toBeVisible();
    await capture("invoices-light");
    await page.getByRole("link", { name: "Customers", exact: true }).click();
    await expect(page.getByText("Amara Okafor", { exact: true })).toBeVisible();
    await page
        .getByRole("row")
        .filter({ hasText: "Amara Okafor" })
        .getByRole("checkbox")
        .check();
    await page.locator(".tb-actions-menu summary").click();
    await expect(page.locator(".tb-actions-menu .tb-menu-panel")).toBeVisible();
    await capture("customers-actions");
    await page.goto("/customers");
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByRole("table")).toBeVisible();
    await capture("customers-mobile");
});
