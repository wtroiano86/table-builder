import { spawn } from "node:child_process";
import { mkdir, open, access } from "node:fs/promises";
import { resolve } from "node:path";
import { defineConfig, type Plugin } from "vite";
import react from "@vitejs/plugin-react";

/** Run Laravel through PHP CLI, including container-backed PHP shims such as Lerd. */
function laravelDemo(): Plugin {
    return {
        name: "laravel-table-demo",
        async configureServer(server) {
            await access("vendor/autoload.php").catch(() => {
                throw new Error(
                    "Run composer install before starting the Laravel demo.",
                );
            });
            const directory = resolve(`tmp/demo-${server.config.server.port}`);
            for (const path of [
                "bootstrap/cache",
                "storage/framework/sessions",
                "storage/framework/views",
                "storage/framework/cache",
                "storage/logs",
            ]) {
                await mkdir(resolve(directory, path), { recursive: true });
            }
            await (
                await open(resolve(directory, "database.sqlite"), "a")
            ).close();
            // Serialize requests so first-run schema creation and reset are atomic to visitors.
            let pending = Promise.resolve();
            server.middlewares.use((request, response, next) => {
                const url = new URL(request.url ?? "/", "http://127.0.0.1");
                if (url.pathname === "/") {
                    response.writeHead(302, { Location: "/customers" });
                    response.end();
                    return;
                }
                if (
                    !/^\/(customers|invoices|table-builder\/(actions|exports)|demo\/reset)$/.test(
                        url.pathname,
                    )
                ) {
                    next();
                    return;
                }
                if (
                    ["/customers", "/invoices"].includes(url.pathname) &&
                    !request.headers["x-inertia"]
                ) {
                    request.url = "/tests/Demo/index.html";
                    next();
                    return;
                }
                const body = (async () => {
                    const chunks: Buffer[] = [];
                    for await (const chunk of request)
                        chunks.push(Buffer.from(chunk));
                    return Buffer.concat(chunks).toString();
                })();
                pending = pending
                    .then(async () => {
                        const input = JSON.stringify({
                            url: `http://${request.headers.host}${request.url}`,
                            method: request.method,
                            headers: request.headers,
                            body: await body,
                        });
                        const output = await new Promise<Buffer>(
                            (resolveOutput, reject) => {
                                const php = spawn(
                                    "php",
                                    ["tests/Demo/router.php", directory],
                                    { stdio: ["pipe", "pipe", "pipe"] },
                                );
                                const chunks: Buffer[] = [];
                                let errors = "";
                                const timeout = setTimeout(() => {
                                    php.kill();
                                    reject(
                                        new Error(
                                            "PHP demo request timed out.",
                                        ),
                                    );
                                }, 30_000);
                                php.stdout.on("data", (chunk) =>
                                    chunks.push(chunk),
                                );
                                php.stderr.on("data", (chunk) => {
                                    errors += String(chunk);
                                });
                                php.on("error", reject);
                                php.stdin.on("error", reject);
                                php.on("close", (code) => {
                                    clearTimeout(timeout);
                                    if (code !== 0)
                                        reject(
                                            new Error(
                                                errors ||
                                                    `PHP exited with code ${code}.`,
                                            ),
                                        );
                                    else resolveOutput(Buffer.concat(chunks));
                                });
                                php.stdin.end(input);
                            },
                        );
                        const end = output.indexOf("\n");
                        const frame = JSON.parse(
                            output.subarray(0, end).toString(),
                        );
                        response.writeHead(frame.status, frame.headers);
                        response.end(output.subarray(end + 1));
                    })
                    .catch((error: unknown) => {
                        server.config.logger.error(String(error));
                        if (!response.headersSent)
                            response.writeHead(500, {
                                "Content-Type": "application/json",
                            });
                        response.end(
                            JSON.stringify({
                                message:
                                    "The Laravel demo request failed. Check the terminal for details.",
                            }),
                        );
                    });
            });
        },
    };
}

export default defineConfig({
    plugins: [react(), laravelDemo()],
    server: {
        host: "127.0.0.1",
        port: 4176,
        strictPort: true,
        watch: { ignored: ["**/vendor/**", "**/tmp/**", "**/.ijfw/**"] },
    },
    build: {
        outDir: "tmp/demo-build",
        rolldownOptions: { input: "tests/Demo/index.html" },
    },
});
