import { useEffect, useId, useRef } from "react";
import type { TableAction } from "./types";

export function ConfirmationDialog({
    action,
    onConfirm,
    onCancel,
}: {
    action: TableAction;
    onConfirm: () => void;
    onCancel: () => void;
}) {
    const dialog = useRef<HTMLDialogElement>(null);
    const id = useId();
    const options = typeof action.confirm === "object" ? action.confirm : {};
    useEffect(() => {
        const previous = document.activeElement;
        const element = dialog.current;
        element?.showModal();
        return () => {
            element?.close();
            if (previous instanceof HTMLElement) previous.focus();
        };
    }, []);
    return (
        <dialog
            className="tb-dialog"
            ref={dialog}
            aria-labelledby={`${id}-title`}
            aria-describedby={options.message ? `${id}-message` : undefined}
            onCancel={(event) => {
                event.preventDefault();
                onCancel();
            }}
        >
            <h2 id={`${id}-title`}>
                {options.title || `Confirm ${action.label.toLowerCase()}`}
            </h2>
            {options.message && <p id={`${id}-message`}>{options.message}</p>}
            <div className="tb-dialog-actions">
                <button
                    type="button"
                    className="tb-button"
                    autoFocus
                    onClick={onCancel}
                >
                    {options.cancelButton || "Cancel"}
                </button>
                <button
                    type="button"
                    className="tb-button tb-button-primary"
                    onClick={onConfirm}
                >
                    {options.confirmButton || "Confirm"}
                </button>
            </div>
        </dialog>
    );
}
