import { watch } from 'vue';
import type { InertiaForm } from '@inertiajs/vue3';

export function useFormDraft<T extends Record<string, unknown>>(
    form: InertiaForm<T>,
    storageKey: string,
    options: { exclude?: (keyof T)[] } = {},
) {
    const exclude = new Set(options.exclude ?? []);

    const saveDraft = () => {
        const data: Record<string, unknown> = {};
        for (const key of Object.keys(form.data()) as (keyof T)[]) {
            if (!exclude.has(key)) {
                data[key as string] = form[key];
            }
        }
        if (!data.title || !(data.title as string).trim()) {
            localStorage.removeItem(storageKey);
            return;
        }
        localStorage.setItem(storageKey, JSON.stringify(data));
    };

    const loadDraft = (): boolean => {
        try {
            const saved = localStorage.getItem(storageKey);
            if (!saved) return false;
            const draft = JSON.parse(saved);
            for (const [key, value] of Object.entries(draft)) {
                if (key in form && !exclude.has(key as keyof T)) {
                    (form as Record<string, unknown>)[key] = value;
                }
            }
            return true;
        } catch {
            return false;
        }
    };

    const deleteDraft = () => {
        localStorage.removeItem(storageKey);
    };

    const getDraft = (): Record<string, unknown> | null => {
        try {
            const saved = localStorage.getItem(storageKey);
            if (!saved) return null;
            return JSON.parse(saved);
        } catch {
            return null;
        }
    };

    watch(() => form.data(), saveDraft, { deep: true });

    return { loadDraft, deleteDraft, getDraft };
}
