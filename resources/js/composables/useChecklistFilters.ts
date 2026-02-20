import { ref, computed, watch, nextTick, type Ref } from 'vue';
import type { ChecklistRow } from '@/types';

interface ExtendedChecklistRow extends ChecklistRow {
    _isNew?: boolean;
}

const INITIAL_ROWS = 50;
const LOAD_MORE_COUNT = 50;

export function useChecklistFilters(
    rows: Ref<ExtendedChecklistRow[]>,
    searchQuery: Ref<string>,
) {
    const scrollContainerRef = ref<HTMLElement | null>(null);
    const highlightedRowId = ref<number | null>(null);
    const visibleRowCount = ref(INITIAL_ROWS);

    // Filters
    const showFilters = ref(false);
    const filterValues = ref<Record<string, string>>({});
    const filterUpdatedFrom = ref('');
    const filterUpdatedTo = ref('');

    const activeFilterCount = computed(() => {
        const selectCount = Object.values(filterValues.value).filter(v => v !== '').length;
        return selectCount + (filterUpdatedFrom.value ? 1 : 0) + (filterUpdatedTo.value ? 1 : 0);
    });

    const clearFilters = () => {
        filterValues.value = {};
        filterUpdatedFrom.value = '';
        filterUpdatedTo.value = '';
    };

    const isRowMatch = (row: ExtendedChecklistRow, query: string): boolean => {
        return Object.values(row.data).some(value => {
            if (typeof value === 'string') {
                return value.toLowerCase().includes(query);
            }
            return false;
        });
    };

    const filteredRows = computed(() => {
        const allRows = rows.value;
        const query = searchQuery.value.trim().toLowerCase();
        const hasFilters = activeFilterCount.value > 0;

        if (!query && !hasFilters) return allRows;

        let dataRows = allRows.filter(row => row.row_type !== 'section_header');

        if (query) {
            dataRows = dataRows.filter(row => isRowMatch(row, query));
        }

        if (hasFilters) {
            dataRows = dataRows.filter(row => {
                const matchesSelects = Object.entries(filterValues.value).every(([key, value]) => {
                    if (!value) return true;
                    return row.data[key] === value;
                });
                if (!matchesSelects) return false;

                const rowDate = row.updated_at ? row.updated_at.slice(0, 10) : '';
                if (filterUpdatedFrom.value && rowDate < filterUpdatedFrom.value) return false;
                if (filterUpdatedTo.value && rowDate > filterUpdatedTo.value) return false;

                return true;
            });
        }

        const matchedIds = new Set(dataRows.map(r => r.id));

        // Find headers that match the search query directly
        const matchedHeaderIds = new Set<number>();
        if (query) {
            for (const row of allRows) {
                if (row.row_type === 'section_header' && isRowMatch(row, query)) {
                    matchedHeaderIds.add(row.id);
                }
            }
        }

        const headersWithMatches = new Set<number>();
        let currentHeader: ExtendedChecklistRow | null = null;

        for (const row of allRows) {
            if (row.row_type === 'section_header') {
                currentHeader = row;
            } else if (matchedIds.has(row.id) && currentHeader) {
                headersWithMatches.add(currentHeader.id);
            }
        }

        const result: ExtendedChecklistRow[] = [];
        currentHeader = null;
        let headerAdded = false;

        for (const row of allRows) {
            if (row.row_type === 'section_header') {
                currentHeader = row;
                headerAdded = false;
                // If the header itself matches the query, include it and all its rows
                if (matchedHeaderIds.has(row.id)) {
                    result.push(row);
                    headerAdded = true;
                }
            } else if (matchedHeaderIds.has(currentHeader?.id ?? -1)) {
                // Include all rows under a matched header
                result.push(row);
            } else if (matchedIds.has(row.id)) {
                if (currentHeader && !headerAdded && headersWithMatches.has(currentHeader.id)) {
                    result.push(currentHeader);
                    headerAdded = true;
                }
                result.push(row);
            }
        }

        return result;
    });

    const filteredDataRowCount = computed(() => {
        return filteredRows.value.filter(r => r.row_type !== 'section_header').length;
    });

    const totalDataRowCount = computed(() => {
        return rows.value.filter(r => r.row_type !== 'section_header').length;
    });

    const displayRows = computed(() => {
        return filteredRows.value.slice(0, visibleRowCount.value);
    });

    const hasMoreRows = computed(() => {
        return visibleRowCount.value < filteredRows.value.length;
    });

    const totalRowCount = computed(() => {
        return filteredRows.value.length;
    });

    const loadMoreRows = () => {
        visibleRowCount.value += LOAD_MORE_COUNT;
    };

    const showAllRows = () => {
        visibleRowCount.value = totalRowCount.value;
    };

    // Reset visible count when search changes
    let isNavigating = false;

    watch(searchQuery, () => {
        if (isNavigating) return;
        visibleRowCount.value = INITIAL_ROWS;
    });

    // Navigate to row
    let highlightTimer: ReturnType<typeof setTimeout> | null = null;

    const navigateToRow = (row: ExtendedChecklistRow, resizeCallback: () => void) => {
        if (!searchQuery.value.trim()) return;

        const rowId = row.id;
        const rowIndex = rows.value.findIndex(r => r.id === rowId);
        const requiredCount = rowIndex >= 0 ? rowIndex + LOAD_MORE_COUNT : visibleRowCount.value;

        isNavigating = true;
        searchQuery.value = '';
        visibleRowCount.value = Math.max(requiredCount, INITIAL_ROWS);

        nextTick(() => {
            isNavigating = false;
            resizeCallback();

            requestAnimationFrame(() => {
                if (highlightTimer) clearTimeout(highlightTimer);
                highlightedRowId.value = rowId;
                highlightTimer = setTimeout(() => {
                    highlightedRowId.value = null;
                }, 2500);

                nextTick(() => {
                    const container = scrollContainerRef.value;
                    if (!container) return;
                    const targetRow = container.querySelector(`tr[data-row-id="${rowId}"]`) as HTMLElement;
                    if (targetRow) {
                        targetRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    }
                });
            });
        });
    };

    const canDragRows = computed(() => !searchQuery.value.trim() && !hasMoreRows.value && activeFilterCount.value === 0);

    return {
        scrollContainerRef,
        highlightedRowId,
        visibleRowCount,
        showFilters,
        filterValues,
        filterUpdatedFrom,
        filterUpdatedTo,
        activeFilterCount,
        clearFilters,
        filteredRows,
        filteredDataRowCount,
        totalDataRowCount,
        displayRows,
        hasMoreRows,
        totalRowCount,
        loadMoreRows,
        showAllRows,
        navigateToRow,
        canDragRows,
        INITIAL_ROWS,
        LOAD_MORE_COUNT,
    };
}
