import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { afterEach, vi } from "vitest";

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});
HTMLDialogElement.prototype.showModal = function () {
    this.setAttribute("open", "");
};
HTMLDialogElement.prototype.close = function () {
    this.removeAttribute("open");
};
