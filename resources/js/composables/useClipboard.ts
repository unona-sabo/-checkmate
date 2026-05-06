import { ref } from 'vue';

/**
 * Copy text to clipboard with fallback for non-HTTPS contexts (e.g. .test domains).
 */
export function writeToClipboard(text: string): Promise<void> {
    if (navigator.clipboard?.writeText) {
        return navigator.clipboard.writeText(text);
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);

    return Promise.resolve();
}

export function useClipboard() {
    const copiedKey = ref<string | null>(null);

    const copy = (text: string, key: string) => {
        writeToClipboard(text).then(() => {
            copiedKey.value = key;
            setTimeout(() => {
                copiedKey.value = null;
            }, 2000);
        });
    };

    return { copiedKey, copy };
}
