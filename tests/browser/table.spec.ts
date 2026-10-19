import { expect, test } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";

test.beforeEach(async ({ request }) => {
    await request.get("/__reset");
});

test("searches, sorts, paginates and restores browser history through real Inertia requests", async ({
    page,
}) => {
    await page.goto("/records?tab=people&orders[page]=3");
    await expect(
        page.getByRole("heading", { name: "People", exact: true }),
    ).toBeVisible();
    await page.getByRole("searchbox").fill("Ada");
    await expect(page).toHaveURL(/people%5Bsearch%5D=Ada/);
    await expect(page.getByText("Grace Hopper", { exact: true })).toBeHidden();
    expect(new URL(page.url()).searchParams.get("orders[page]")).toBe("3");
    await page.getByRole("searchbox").clear();
    await expect(page.getByText("Grace Hopper", { exact: true })).toBeVisible();
    await page.getByRole("button", { name: "Name", exact: true }).click();
    await expect(
        page.getByRole("columnheader", { name: "Name" }),
    ).toHaveAttribute("aria-sort", "descending");
    await page.getByRole("button", { name: "Next page" }).click();
    await expect(page.getByText("Ada Lovelace", { exact: true })).toBeVisible();
    await expect(page.getByText("Page 2 of 2")).toBeVisible();
    await page.goBack();
    await expect(page.getByText("Page 1 of 2")).toBeVisible();
});

test("confirms raw-key commands, refreshes results, downloads files and queues exports", async ({
    page,
}) => {
    await page.goto("/records");
    const actionsMenu = page.locator(".tb-actions-menu");
    await actionsMenu.locator("summary").click();
    await expect(
        actionsMenu.getByRole("button", { name: "Delete", exact: true }),
    ).toBeDisabled();
    await page.keyboard.press("Escape");
    await expect(actionsMenu).not.toHaveAttribute("open");
    await page
        .getByRole("checkbox", { name: "Select row 1", exact: true })
        .check();
    await actionsMenu.locator("summary").click();
    await actionsMenu
        .getByRole("button", { name: "Delete", exact: true })
        .click();
    await expect(page.getByRole("dialog")).toBeVisible();
    await expect(
        page.getByRole("button", { name: "Keep records" }),
    ).toBeFocused();
    await page.keyboard.press("Escape");
    await expect(page.getByRole("dialog")).toBeHidden();
    await expect(actionsMenu.locator("summary")).toBeFocused();
    await actionsMenu.locator("summary").click();
    await actionsMenu
        .getByRole("button", { name: "Delete", exact: true })
        .click();
    const actionRequest = page.waitForRequest((request) =>
        request.url().endsWith("/table-builder/actions"),
    );
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Delete", exact: true })
        .click();
    expect((await actionRequest).postDataJSON()).toMatchObject({
        table: "registry:react-records",
        action: "delete",
        keys: ["acct-001"],
    });
    await expect(page.getByText("Ada Lovelace", { exact: true })).toBeHidden();
    await expect(page.getByText("Delete completed.")).toBeVisible();
    await page.getByText("Export", { exact: true }).click();
    const download = page.waitForEvent("download");
    await page
        .getByRole("button", { name: "Download records", exact: true })
        .click();
    expect((await download).suggestedFilename()).toBe("people.csv");
    await page
        .getByRole("button", { name: "Queue export", exact: true })
        .click();
    await expect(page.getByText("Export is being processed.")).toBeVisible();
});

test("runs the outlined row actions without a bulk selection", async ({
    page,
}) => {
    await page.goto("/records");
    const row = page.getByRole("row").filter({ hasText: "Ada Lovelace" });
    await expect(
        page.getByRole("columnheader", { name: "Actions", exact: true }),
    ).toHaveCount(1);
    const deactivate = row.getByRole("button", {
        name: "Deactivate",
        exact: true,
    });
    await expect(deactivate).toHaveClass(/tb-action-icon/);
    await expect(deactivate).toHaveAttribute("data-variant", "outline");
    await expect(deactivate).toHaveAttribute("title", "Deactivate");
    await deactivate.click();
    const request = page.waitForRequest((request) =>
        request.url().endsWith("/table-builder/actions"),
    );
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Deactivate", exact: true })
        .click();
    expect((await request).postDataJSON()).toMatchObject({
        action: "deactivate",
        keys: ["acct-001"],
    });
    await expect(row.getByText("Inactive", { exact: true })).toBeVisible();
    await expect(deactivate).toBeDisabled();
    await row.getByRole("button", { name: "Delete", exact: true }).click();
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Delete", exact: true })
        .click();
    await expect(row).toHaveCount(0);
});

test("has accessible desktop, dark, mobile, empty and cursor views", async ({
    page,
}, testInfo) => {
    await page.goto("/records");
    await expect(page.getByRole("table")).toBeVisible();
    expect(
        (
            await new AxeBuilder({ page })
                .withTags(["wcag2a", "wcag2aa"])
                .analyze()
        ).violations,
    ).toEqual([]);
    await page.screenshot({
        path: testInfo.outputPath("desktop.png"),
        fullPage: true,
    });
    await page.emulateMedia({ colorScheme: "dark" });
    expect(
        (
            await new AxeBuilder({ page })
                .withTags(["wcag2a", "wcag2aa"])
                .analyze()
        ).violations,
    ).toEqual([]);
    await page.screenshot({
        path: testInfo.outputPath("dark.png"),
        fullPage: true,
    });
    await page.evaluate(() => {
        document.documentElement.dataset.theme = "light";
    });
    await expect(page.locator(".tb-page")).toHaveCSS(
        "background-color",
        "rgb(247, 248, 250)",
    );
    await expect(page.locator(".tb-table")).toHaveCSS(
        "background-color",
        "rgb(255, 255, 255)",
    );
    await page.evaluate(() => {
        document.documentElement.dataset.theme = "dark";
    });
    await page.emulateMedia({ colorScheme: "light" });
    await expect(page.locator(".tb-page")).toHaveCSS(
        "background-color",
        "rgb(17, 21, 28)",
    );
    await expect(page.locator(".tb-table")).toHaveCSS(
        "background-color",
        "rgb(23, 28, 36)",
    );
    await page.evaluate(() => {
        delete document.documentElement.dataset.theme;
    });
    await page.setViewportSize({ width: 375, height: 812 });
    await page.emulateMedia({ colorScheme: "light" });
    await expect(page.getByRole("searchbox")).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: testInfo.outputPath("mobile.png"),
        fullPage: true,
    });
    await page.setViewportSize({ width: 320, height: 812 });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    const actionsMenu = page.locator(".tb-actions-menu");
    await actionsMenu.locator("summary").click();
    const menuBounds = await actionsMenu
        .locator(".tb-menu-panel")
        .boundingBox();
    expect(menuBounds).not.toBeNull();
    expect(menuBounds!.x).toBeGreaterThanOrEqual(0);
    expect(menuBounds!.x + menuBounds!.width).toBeLessThanOrEqual(320);
    await expect(
        actionsMenu.getByRole("button", { name: "Delete", exact: true }),
    ).toBeVisible();
    await page.keyboard.press("Escape");
    await page.goto("/records?demo=empty");
    await expect(
        page.getByRole("heading", { name: "No matching people" }),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath("empty.png"),
        fullPage: true,
    });
    await page.goto("/records?demo=cursor");
    await page.getByRole("button", { name: "Next page" }).click();
    await expect(
        page.getByText("Linus Torvalds", { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole("button", { name: "Next page" }),
    ).toBeDisabled();
    await page.getByRole("button", { name: "Previous page" }).click();
    await expect(page.getByText("Ada Lovelace", { exact: true })).toBeVisible();
});
