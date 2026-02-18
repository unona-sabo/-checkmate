import { ref, type Ref, type ComputedRef } from 'vue';

interface ColumnWithWidth {
    key: string;
    width?: number;
    [key: string]: unknown;
}

export function useChecklistDragDrop<TRow, TCol extends ColumnWithWidth>(
    rows: Ref<TRow[]>,
    columns: Ref<TCol[]>,
    visibleColumns: ComputedRef<TCol[]>,
    hasContentChanges: Ref<boolean>,
) {
    // Row drag and drop
    const draggedRowIndex = ref<number | null>(null);
    const dragOverRowIndex = ref<number | null>(null);

    const onRowDragStart = (index: number, event: DragEvent) => {
        draggedRowIndex.value = index;
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', index.toString());
        }
    };

    const onRowDragOver = (index: number, event: DragEvent) => {
        event.preventDefault();
        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }
        dragOverRowIndex.value = index;
    };

    const onRowDragLeave = () => {
        dragOverRowIndex.value = null;
    };

    const onRowDrop = (index: number, event: DragEvent) => {
        event.preventDefault();
        if (draggedRowIndex.value !== null && draggedRowIndex.value !== index) {
            const draggedRow = rows.value[draggedRowIndex.value];
            rows.value.splice(draggedRowIndex.value, 1);
            rows.value.splice(index, 0, draggedRow);
            hasContentChanges.value = true;
        }
        draggedRowIndex.value = null;
        dragOverRowIndex.value = null;
    };

    const onRowDragEnd = () => {
        draggedRowIndex.value = null;
        dragOverRowIndex.value = null;
    };

    // Column drag and drop
    const draggedColIndex = ref<number | null>(null);
    const dragOverColIndex = ref<number | null>(null);

    const onColDragStart = (index: number, event: DragEvent) => {
        draggedColIndex.value = index;
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', index.toString());
        }
    };

    const onColDragOver = (index: number, event: DragEvent) => {
        event.preventDefault();
        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }
        dragOverColIndex.value = index;
    };

    const onColDragLeave = () => {
        dragOverColIndex.value = null;
    };

    const onColDrop = (visibleIndex: number, event: DragEvent) => {
        event.preventDefault();
        if (draggedColIndex.value !== null && draggedColIndex.value !== visibleIndex) {
            const draggedKey = visibleColumns.value[draggedColIndex.value]?.key;
            const targetKey = visibleColumns.value[visibleIndex]?.key;
            const actualFrom = columns.value.findIndex(c => c.key === draggedKey);
            const actualTo = columns.value.findIndex(c => c.key === targetKey);

            if (actualFrom !== -1 && actualTo !== -1 && actualFrom !== actualTo) {
                const draggedCol = columns.value[actualFrom];
                columns.value.splice(actualFrom, 1);
                columns.value.splice(actualTo, 0, draggedCol);
                hasContentChanges.value = true;
            }
        }
        draggedColIndex.value = null;
        dragOverColIndex.value = null;
    };

    const onColDragEnd = () => {
        draggedColIndex.value = null;
        dragOverColIndex.value = null;
    };

    // Column resize
    const resizingCol = ref<number | null>(null);
    const resizeStartX = ref(0);
    const resizeStartWidth = ref(0);

    const startResize = (visibleIndex: number, event: MouseEvent) => {
        const colKey = visibleColumns.value[visibleIndex]?.key;
        const actualIndex = columns.value.findIndex(c => c.key === colKey);
        if (actualIndex === -1) return;
        resizingCol.value = actualIndex;
        resizeStartX.value = event.clientX;
        resizeStartWidth.value = columns.value[actualIndex].width || 150;
        document.addEventListener('mousemove', onResize);
        document.addEventListener('mouseup', stopResize);
    };

    const onResize = (event: MouseEvent) => {
        if (resizingCol.value === null) return;
        const diff = event.clientX - resizeStartX.value;
        const newWidth = Math.max(50, resizeStartWidth.value + diff);
        columns.value[resizingCol.value].width = newWidth;
    };

    const stopResize = () => {
        if (resizingCol.value !== null) {
            hasContentChanges.value = true;
        }
        resizingCol.value = null;
        document.removeEventListener('mousemove', onResize);
        document.removeEventListener('mouseup', stopResize);
    };

    return {
        // Row drag
        draggedRowIndex,
        dragOverRowIndex,
        onRowDragStart,
        onRowDragOver,
        onRowDragLeave,
        onRowDrop,
        onRowDragEnd,
        // Column drag
        draggedColIndex,
        dragOverColIndex,
        onColDragStart,
        onColDragOver,
        onColDragLeave,
        onColDrop,
        onColDragEnd,
        // Column resize
        resizingCol,
        startResize,
    };
}
