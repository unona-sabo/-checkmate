import { ref } from 'vue';

function execCommandCopy(text: string): boolean {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    let succeeded = false;
    try {
        succeeded = document.execCommand('copy');
    } finally {
        document.body.removeChild(textarea);
    }
    return succeeded;
}

/**
 * Synchronous clipboard copy via the legacy execCommand API. Prefer this
 * (over the async writeToClipboard below) when the copy is triggered from
 * inside a dropdown/menu item click — those don't always carry enough
 * "user activation" for navigator.clipboard.writeText, which can then
 * reject or hang with no visible error.
 */
export function writeToClipboardSync(text: string): boolean {
    return execCommandCopy(text);
}

/**
 * Copy text to clipboard with fallback for non-HTTPS contexts (e.g. .test
 * domains) and for calls that don't count as "user activation" in the
 * Clipboard API's eyes (e.g. a dropdown menu item's click handler), which
 * make navigator.clipboard.writeText reject silently.
 */
export function writeToClipboard(text: string): Promise<void> {
    if (navigator.clipboard?.writeText) {
        return navigator.clipboard.writeText(text).catch(() => {
            if (!execCommandCopy(text)) {
                throw new Error('Failed to copy to clipboard');
            }
        });
    }

    if (!execCommandCopy(text)) {
        return Promise.reject(new Error('Failed to copy to clipboard'));
    }

    return Promise.resolve();
}

export function useClipboard() {
    const copiedKey = ref<string | null>(null);

    const copy = (text: string, key: string) => {
        writeToClipboard(text)
            .then(() => {
                copiedKey.value = key;
                setTimeout(() => {
                    copiedKey.value = null;
                }, 2000);
            })
            .catch(() => {
                // Clipboard write failed — leave copiedKey unset so the UI
                // doesn't falsely claim success.
            });
    };

    return { copiedKey, copy };
}
