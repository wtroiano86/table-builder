import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
    testDir: "./tests/Demo",
    fullyParallel: false,
    workers: 1,
    reporter: "list",
    outputDir: "tmp/demo-browser-results",
    use: {
        ...devices["Desktop Chrome"],
        baseURL: "http://127.0.0.1:4177",
        colorScheme: "light",
        locale: "en-US",
        timezoneId: "UTC",
        trace: "retain-on-failure",
    },
    projects: [
        { name: "demo", testMatch: "demo.spec.ts" },
        { name: "screenshots", testMatch: "screenshots.ts" },
    ],
    webServer: {
        command: "npm run demo -- --port 4177",
        url: "http://127.0.0.1:4177/customers",
        // Keep browser tests and captures separate from the interactive demo's data.
        reuseExistingServer: false,
        timeout: 60_000,
    },
});
