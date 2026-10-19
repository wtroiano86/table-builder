import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
    testDir: "./tests/browser",
    testMatch: "**/*.spec.ts",
    fullyParallel: false,
    workers: 1,
    reporter: "list",
    outputDir: "tmp/react-table-checks/browser-results",
    use: { baseURL: "http://127.0.0.1:4173", trace: "retain-on-failure" },
    projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
    webServer: {
        command: "npx vite --force --config vite.preview.config.ts",
        url: "http://127.0.0.1:4173/records",
        reuseExistingServer: !process.env.CI,
    },
});
