import payload from "./fixtures/table.json";
import type { TablePayload } from "../../resources/react/types";

export function fixture(): TablePayload {
    return structuredClone(payload) as unknown as TablePayload;
}
