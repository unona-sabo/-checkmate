import { watch } from 'vue';

/**
 * Automatically clears per-field validation errors when the field value changes.
 * Also clears nested errors (e.g. "attachments.0") when the parent field changes.
 */
export function useClearErrorsOnInput(form: ReturnType<typeof import('@inertiajs/vue3').useForm>): void {
    const keys = Object.keys(form.data());

    for (const key of keys) {
        watch(
            () => form[key as keyof typeof form],
            () => {
                const errorKeys = Object.keys(form.errors).filter(
                    (e) => e === key || e.startsWith(key + '.'),
                );
                if (errorKeys.length > 0) {
                    form.clearErrors(...errorKeys);
                }
            },
            { deep: true },
        );
    }
}
