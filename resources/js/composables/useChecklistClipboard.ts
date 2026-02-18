import { ref, computed } from 'vue';
import type { ColumnConfig } from '@/types';

const CLIPBOARD_KEY = 'checkmate-row-clipboard';

export interface ClipboardData {
    rows: Array<{
        data: Record<string, unknown>;
        row_type: string;
        background_color: string | null;
        font_color: string | null;
        font_weight: string;
    }>;
    source_columns_config: ColumnConfig[];
    source_checklist_name: string;
    timestamp: number;
}

export function useChecklistClipboard() {
    const clipboardData = ref<ClipboardData | null>(null);
    const copiedRows = ref(false);

    const hasClipboardRows = computed(() => clipboardData.value !== null && clipboardData.value.rows.length > 0);

    const loadClipboard = () => {
        try {
            const saved = localStorage.getItem(CLIPBOARD_KEY);
            if (saved) {
                clipboardData.value = JSON.parse(saved);
            }
        } catch (e) {
            clipboardData.value = null;
        }
    };

    const clearClipboard = () => {
        localStorage.removeItem(CLIPBOARD_KEY);
        clipboardData.value = null;
    };

    const saveToClipboard = (data: ClipboardData) => {
        try {
            localStorage.setItem(CLIPBOARD_KEY, JSON.stringify(data));
            clipboardData.value = data;
            copiedRows.value = true;
            setTimeout(() => { copiedRows.value = false; }, 2000);
        } catch (e) {
            console.error('Failed to save to clipboard:', e);
        }
    };

    const getClipboardBackup = (): string | null => {
        return localStorage.getItem(CLIPBOARD_KEY);
    };

    const restoreClipboard = (backup: string) => {
        localStorage.setItem(CLIPBOARD_KEY, backup);
        loadClipboard();
    };

    return {
        clipboardData,
        copiedRows,
        hasClipboardRows,
        loadClipboard,
        clearClipboard,
        saveToClipboard,
        getClipboardBackup,
        restoreClipboard,
        CLIPBOARD_KEY,
    };
}
