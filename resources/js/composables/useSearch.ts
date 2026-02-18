import { ref, computed, type Ref } from 'vue';

export function escapeRegExp(str: string): string {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function escapeHtml(str: string): string {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export function useSearch() {
    const searchQuery = ref('');

    const clearSearch = () => {
        searchQuery.value = '';
    };

    const isSearchActive = computed(() => searchQuery.value.trim().length > 0);

    const highlight = (text: string): string => {
        const safe = escapeHtml(text);
        if (!searchQuery.value.trim()) return safe;
        const query = escapeRegExp(searchQuery.value.trim());
        return safe.replace(new RegExp(`(${query})`, 'gi'), '<mark class="search-highlight">$1</mark>');
    };

    return { searchQuery, clearSearch, isSearchActive, highlight };
}

export function useFilteredList<T>(
    items: Ref<T[]> | (() => T[]),
    searchQuery: Ref<string>,
    searchFields: (keyof T)[],
) {
    const filtered = computed(() => {
        const list = typeof items === 'function' ? items() : items.value;
        const query = searchQuery.value.trim().toLowerCase();
        if (!query) return list;
        return list.filter((item) =>
            searchFields.some((field) => {
                const value = item[field];
                if (typeof value === 'string') {
                    return value.toLowerCase().includes(query);
                }
                return false;
            }),
        );
    });

    return { filtered };
}
