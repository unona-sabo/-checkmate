import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { AppPageProps } from '@/types';

export function useCanEdit() {
    const page = usePage<AppPageProps>();

    const canEdit = computed(() => {
        const role = page.props.currentWorkspace?.role;
        return role !== 'viewer';
    });

    return { canEdit };
}
