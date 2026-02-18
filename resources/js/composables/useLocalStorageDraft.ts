import { ref } from 'vue';

export interface DraftData {
    content: string;
    [key: string]: unknown;
}

export function useLocalStorageDraft<T extends DraftData>(storageKey: string) {
    const hasDraft = ref(false);

    const loadDraft = (): T | null => {
        try {
            const saved = localStorage.getItem(storageKey);
            if (saved) {
                const draft = JSON.parse(saved) as T;
                if (draft.content && draft.content.trim()) {
                    hasDraft.value = true;
                    return draft;
                }
            }
        } catch (e) {
            console.error('Failed to load draft:', e);
        }
        return null;
    };

    const saveDraft = (draft: T) => {
        if (!draft.content.trim()) {
            deleteDraft();
            return;
        }
        try {
            localStorage.setItem(storageKey, JSON.stringify(draft));
            hasDraft.value = true;
        } catch (e) {
            console.error('Failed to save draft:', e);
        }
    };

    const deleteDraft = () => {
        try {
            localStorage.removeItem(storageKey);
            hasDraft.value = false;
        } catch (e) {
            console.error('Failed to delete draft:', e);
        }
    };

    const getDraft = (): T | null => {
        try {
            const saved = localStorage.getItem(storageKey);
            if (saved) {
                return JSON.parse(saved) as T;
            }
        } catch (e) {
            console.error('Failed to get draft:', e);
        }
        return null;
    };

    // Check if draft exists on init
    loadDraft();

    return {
        hasDraft,
        loadDraft,
        saveDraft,
        deleteDraft,
        getDraft,
    };
}
