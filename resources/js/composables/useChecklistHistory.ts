import { computed, ref } from 'vue';
import type { ChecklistRow, ColumnConfig } from '@/types';

/**
 * Multi-step undo for the checklist grid, without paying for a full deep
 * clone of every row per history step. Each step only stores what actually
 * changed between two saves: a lightweight "skeleton" (order/type/styling,
 * no cell data) for every row plus, only for rows whose cell values changed,
 * their previous `data` object. Full rows are only kept for rows that were
 * deleted (nothing smaller could restore them) and columns are only kept
 * when the column config itself changed.
 */

type Row = ChecklistRow & { _isNew?: boolean };
type Column = ColumnConfig & { width?: number };

interface RowSkeleton {
    id: number;
    order: number;
    row_type: Row['row_type'];
    background_color: string | null;
    font_color: string | null;
    font_weight: Row['font_weight'];
    module: string[] | null;
}

interface HistoryEntry {
    /** Order/styling for every row that existed before this save, cheap to keep. */
    skeletons: RowSkeleton[];
    /** rowId -> previous `data`, only for rows whose data actually changed. */
    changedData: Map<number, Record<string, unknown>>;
    /** Full rows that existed before this save but were deleted by it. */
    removedRows: Row[];
    /** Ids of rows that didn't exist before this save (to drop on undo). */
    addedRowIds: number[];
    /** Previous columns config, only set when columns actually changed. */
    columns: Column[] | null;
}

const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value));

const toSkeleton = (row: Row): RowSkeleton => ({
    id: row.id,
    order: row.order,
    row_type: row.row_type,
    background_color: row.background_color,
    font_color: row.font_color,
    font_weight: row.font_weight,
    module: row.module ? [...row.module] : null,
});

export function useChecklistHistory(maxSteps = 3) {
    const stack = ref<HistoryEntry[]>([]);

    const canUndo = computed(() => stack.value.length > 0);

    /** Records the diff between the previously saved state and the newly saved one. */
    function push(oldRows: Row[], oldColumns: Column[], newRows: Row[], newColumns: Column[]) {
        const oldById = new Map(oldRows.map((row) => [row.id, row]));
        const newIds = new Set(newRows.map((row) => row.id));

        const removedRows = oldRows
            .filter((row) => !newIds.has(row.id))
            .map((row) => clone(row));

        const addedRowIds = newRows
            .filter((row) => !oldById.has(row.id))
            .map((row) => row.id);

        const changedData = new Map<number, Record<string, unknown>>();
        for (const newRow of newRows) {
            const oldRow = oldById.get(newRow.id);
            if (!oldRow) continue;
            if (JSON.stringify(oldRow.data) !== JSON.stringify(newRow.data)) {
                changedData.set(newRow.id, clone(oldRow.data));
            }
        }

        const columnsChanged =
            JSON.stringify(oldColumns) !== JSON.stringify(newColumns);

        const entry: HistoryEntry = {
            skeletons: oldRows.map(toSkeleton),
            changedData,
            removedRows,
            addedRowIds,
            columns: columnsChanged ? clone(oldColumns) : null,
        };

        stack.value.push(entry);
        if (stack.value.length > maxSteps) {
            stack.value.shift();
        }
    }

    /** Reverts to the state before the most recent recorded save, if any. */
    function undo(
        currentRows: Row[],
        currentColumns: Column[],
    ): { rows: Row[]; columns: Column[] } | null {
        const entry = stack.value.pop();
        if (!entry) return null;

        const addedIds = new Set(entry.addedRowIds);
        const skeletonById = new Map(entry.skeletons.map((s) => [s.id, s]));

        const survivingRows = currentRows
            .filter((row) => !addedIds.has(row.id))
            .map((row) => {
                const skeleton = skeletonById.get(row.id);
                const previousData = entry.changedData.get(row.id);

                if (!skeleton && !previousData) return clone(row);

                return {
                    ...clone(row),
                    ...(skeleton ?? {}),
                    data: previousData ? clone(previousData) : row.data,
                };
            });

        const restoredRows = [
            ...survivingRows,
            ...entry.removedRows.map((row) => clone(row)),
        ].sort((a, b) => a.order - b.order);

        return {
            rows: restoredRows,
            columns: entry.columns ? clone(entry.columns) : clone(currentColumns),
        };
    }

    return { canUndo, push, undo };
}
