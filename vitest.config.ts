import { defineConfig } from "vitest/config";

export default defineConfig({
    test: {
        environment: "jsdom",
        include: ["tests/react/**/*.test.{ts,tsx}"],
        setupFiles: ["tests/react/setup.ts"],
        environmentOptions: {
            jsdom: {
                url: "http://localhost/records?tab=people&orders[page]=3",
            },
        },
    },
});
