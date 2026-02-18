<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type Checklist, type ChecklistRow, type ColumnConfig, type SelectOption, type TestSuite } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
    DropdownMenuSub,
    DropdownMenuSubTrigger,
    DropdownMenuSubContent,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    ClipboardList, Edit, Plus, Trash2, Save, GripVertical,
    Bold, Heading, GripHorizontal, StickyNote, Import, Pencil, X, Search,
    MoreHorizontal, Copy, Layers, Play, Download, Upload, FileSpreadsheet,
    ArrowUp, ArrowDown, Bug, RefreshCw, Undo2, AlertCircle, Columns3, Check, Link2, Filter,
    LocateFixed
} from 'lucide-vue-next';
import { ref, watch, onMounted, nextTick, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import RestrictedAction from '@/components/RestrictedAction.vue';
import FeatureBadges from '@/components/FeatureBadges.vue';
import { useCanEdit } from '@/composables/useCanEdit';

const { canEdit } = useCanEdit();

const props = defineProps<{
    project: Project;
    checklist: Checklist;
    checklists: Checklist[];
    testSuites: TestSuite[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Checklists', href: `/projects/${props.project.id}/checklists` },
    { title: props.checklist.name, href: `/projects/${props.project.id}/checklists/${props.checklist.id}` },
];

interface ExtendedChecklistRow extends ChecklistRow {
    _isNew?: boolean;
}

interface ExtendedColumnConfig extends ColumnConfig {
    width?: number;
}

const copied = ref(false);
const copiedRows = ref(false);

// Internal clipboard (localStorage)
const CLIPBOARD_KEY = 'checkmate-row-clipboard';

interface ClipboardData {
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

const clipboardData = ref<ClipboardData | null>(null);

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

const hasClipboardRows = computed(() => clipboardData.value !== null && clipboardData.value.rows.length > 0);

const titleStart = computed(() => {
    const words = props.checklist.name.split(' ');
    return words.length > 1 ? words.slice(0, -1).join(' ') + ' ' : '';
});
const titleEnd = computed(() => {
    const words = props.checklist.name.split(' ');
    return words[words.length - 1];
});

const copyLink = () => {
    const route = `/projects/${props.project.id}/checklists/${props.checklist.id}`;
    const url = window.location.origin + route;
    const textArea = document.createElement('textarea');
    textArea.value = url;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
};

const rows = ref<ExtendedChecklistRow[]>(
    props.checklist.rows?.map(r => ({
        ...r,
        row_type: r.row_type || 'normal',
        background_color: r.background_color || null,
        font_color: r.font_color || null,
        font_weight: r.font_weight || 'normal',
    })) || []
);

const columns = ref<ExtendedColumnConfig[]>(
    (props.checklist.columns_config || [
        { key: 'item', label: 'Item', type: 'text' as const, width: 200 },
        { key: 'status', label: 'Status', type: 'checkbox' as const, width: 80 },
    ]).map(col => ({ ...col, width: col.width || 150 }))
);

// Column visibility
const HIDDEN_COLS_KEY = `checklist-hidden-cols-${props.checklist.id}`;
const hiddenColumns = ref<string[]>([]);

const loadHiddenColumns = () => {
    try {
        const saved = localStorage.getItem(HIDDEN_COLS_KEY);
        if (saved) {
            const parsed = JSON.parse(saved) as string[];
            const validKeys = columns.value.map(c => c.key);
            hiddenColumns.value = parsed.filter(k => validKeys.includes(k));
            if (hiddenColumns.value.length !== parsed.length) {
                saveHiddenColumns();
            }
        }
    } catch (e) {
        console.error('Failed to load hidden columns:', e);
    }
};

const saveHiddenColumns = () => {
    try {
        localStorage.setItem(HIDDEN_COLS_KEY, JSON.stringify(hiddenColumns.value));
    } catch (e) {
        console.error('Failed to save hidden columns:', e);
    }
};

const toggleColumnVisibility = (key: string) => {
    const idx = hiddenColumns.value.indexOf(key);
    if (idx >= 0) {
        hiddenColumns.value.splice(idx, 1);
    } else {
        hiddenColumns.value.push(key);
    }
    saveHiddenColumns();
};

const visibleColumns = computed(() => columns.value.filter(c => !hiddenColumns.value.includes(c.key)));
const hasHiddenColumns = computed(() => hiddenColumns.value.length > 0);

const showDeleteConfirm = ref(false);
const rowToDeleteIndex = ref<number | null>(null);

// Copy to Checklist dialog state
const showCopyToChecklistDialog = ref(false);
const copyTargetChecklistId = ref<number | null>(null);
const copyTargetSectionId = ref<number | null>(null);
const isCopying = ref(false);

const otherChecklists = computed(() => props.checklists.filter(c => c.id !== props.checklist.id));

const targetSectionHeaders = computed(() => {
    if (!copyTargetChecklistId.value) return [];
    const target = props.checklists.find(c => c.id === copyTargetChecklistId.value);
    if (!target?.section_headers?.length) return [];

    const cols = target.columns_config || [{ key: 'item', label: 'Item', type: 'text' as const }];
    const labelKey = cols[0]?.key || 'item';

    return target.section_headers
        .sort((a, b) => a.order - b.order)
        .map(sh => ({
            id: sh.id,
            label: String(sh.data[labelKey] || `Section ${sh.order}`),
        }));
});

watch(copyTargetChecklistId, () => {
    copyTargetSectionId.value = null;
});

// Add rows dialog state
const showAddRowsDialog = ref(false);
const addRowsPosition = ref<'above' | 'below' | 'end'>('end');
const addRowsCount = ref(1);
const addRowsAtIndex = ref<number>(0);
const addRowsType = ref<'normal' | 'section_header'>('normal');

// Track content changes (excluding checkbox changes)
const hasContentChanges = ref(false);
const checkboxKeys = computed(() => columns.value.filter(c => c.type === 'checkbox').map(c => c.key));

// Undo last save — tracks the state after each successful save
type Snapshot = { rows: ExtendedChecklistRow[]; columns: ExtendedColumnConfig[] };
// State after the last successful save (current "clean" state)
let lastSavedState: Snapshot = {
    rows: JSON.parse(JSON.stringify(rows.value)),
    columns: JSON.parse(JSON.stringify(columns.value)),
};
// State after the save before that (what undo reverts to)
const previousSavedState = ref<Snapshot | null>(null);
const saveError = ref(false);
const isSaving = ref(false);
const canUndo = computed(() => previousSavedState.value !== null);

const undoLastSave = () => {
    if (!previousSavedState.value) return;
    const snapshot = previousSavedState.value;
    previousSavedState.value = null;

    rows.value = JSON.parse(JSON.stringify(snapshot.rows));
    columns.value = JSON.parse(JSON.stringify(snapshot.columns));
    hasContentChanges.value = true;
    saveError.value = false;
    nextTick(() => {
        resizeAllTextareas();
        saveRows();
    });
};

const dismissSaveError = () => {
    saveError.value = false;
};

// Search state
const searchQuery = ref('');
const scrollContainerRef = ref<HTMLElement | null>(null);
const highlightedRowId = ref<number | null>(null);

// Progressive rendering - show rows in batches for better performance
const INITIAL_ROWS = 50;
const LOAD_MORE_COUNT = 50;
const visibleRowCount = ref(INITIAL_ROWS);

const isRowMatch = (row: ExtendedChecklistRow, query: string): boolean => {
    return Object.values(row.data).some(value => {
        if (typeof value === 'string') {
            return value.toLowerCase().includes(query);
        }
        return false;
    });
};

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

const filteredRows = computed(() => {
    const allRows = rows.value;
    const query = searchQuery.value.trim().toLowerCase();
    const hasFilters = activeFilterCount.value > 0;

    if (!query && !hasFilters) return allRows;

    // Filter only data rows (not section headers)
    let dataRows = allRows.filter(row => row.row_type !== 'section_header');

    if (query) {
        dataRows = dataRows.filter(row => isRowMatch(row, query));
    }

    if (hasFilters) {
        dataRows = dataRows.filter(row => {
            // Select column filters
            const matchesSelects = Object.entries(filterValues.value).every(([key, value]) => {
                if (!value) return true;
                return row.data[key] === value;
            });
            if (!matchesSelects) return false;

            // Date range filters (compare date-only strings YYYY-MM-DD)
            const rowDate = row.updated_at ? row.updated_at.slice(0, 10) : '';
            if (filterUpdatedFrom.value && rowDate < filterUpdatedFrom.value) return false;
            if (filterUpdatedTo.value && rowDate > filterUpdatedTo.value) return false;

            return true;
        });
    }

    // Re-insert section headers that have matching data rows below them
    const matchedIds = new Set(dataRows.map(r => r.id));
    const headersWithMatches = new Set<number>();
    let currentHeader: ExtendedChecklistRow | null = null;

    for (const row of allRows) {
        if (row.row_type === 'section_header') {
            currentHeader = row;
        } else if (matchedIds.has(row.id) && currentHeader) {
            headersWithMatches.add(currentHeader.id);
        }
    }

    // Build final list preserving original order
    const result: ExtendedChecklistRow[] = [];
    currentHeader = null;
    let headerAdded = false;

    for (const row of allRows) {
        if (row.row_type === 'section_header') {
            currentHeader = row;
            headerAdded = false;
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

// Count of matched data rows (excluding section headers)
const filteredDataRowCount = computed(() => {
    return filteredRows.value.filter(r => r.row_type !== 'section_header').length;
});

const totalDataRowCount = computed(() => {
    return rows.value.filter(r => r.row_type !== 'section_header').length;
});

// Rows to actually render (limited for performance)
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

// Navigate to row: clear search, show full table, scroll to row
let highlightTimer: ReturnType<typeof setTimeout> | null = null;
let isNavigating = false;

const navigateToRow = (row: ExtendedChecklistRow) => {
    if (!searchQuery.value.trim()) return;

    const rowId = row.id;

    // Determine how many rows need to be visible so the target row is rendered
    const rowIndex = rows.value.findIndex(r => r.id === rowId);
    const requiredCount = rowIndex >= 0 ? rowIndex + LOAD_MORE_COUNT : visibleRowCount.value;

    // Skip the watcher reset while navigating — reset flag in nextTick
    // because Vue 3 watch callbacks are async (flush: 'pre')
    isNavigating = true;
    searchQuery.value = '';
    visibleRowCount.value = Math.max(requiredCount, INITIAL_ROWS);

    // Wait for Vue to update the DOM, then for the browser to paint new rows
    nextTick(() => {
        isNavigating = false;
        resizeAllTextareas();

        // requestAnimationFrame ensures the browser has painted new rows
        // before we add the highlight class (so transition-all animates in)
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

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);

watch(searchQuery, () => {
    if (isNavigating) return;
    visibleRowCount.value = INITIAL_ROWS;
});

// Resize textareas when visible rows change (load more, show all, search)
watch(visibleRowCount, () => {
    resizeAllTextareas();
});

// Drag-and-drop only allowed when showing all rows and not searching
const canDragRows = computed(() => !searchQuery.value.trim() && !hasMoreRows.value && activeFilterCount.value === 0);

// Note dialog state
const showNoteDialog = ref(false);
const noteContent = ref('');
const isImporting = ref(false);
const selectedChecklistId = ref<number>(props.checklist.id);
const hasDraft = ref(false);

// Draft storage
const DRAFT_STORAGE_KEY = `checklist-note-draft-${props.checklist.id}`;

interface NoteDraft {
    content: string;
    selectedChecklistId: number;
    selectedColumnKey: string;
}

const loadDraft = () => {
    try {
        const saved = localStorage.getItem(DRAFT_STORAGE_KEY);
        if (saved) {
            const draft: NoteDraft = JSON.parse(saved);
            if (draft.content && draft.content.trim()) {
                hasDraft.value = true;
            }
        }
    } catch (e) {
        console.error('Failed to load draft:', e);
    }
};

const saveDraft = () => {
    if (!noteContent.value.trim()) {
        deleteDraft();
        return;
    }

    const draft: NoteDraft = {
        content: noteContent.value,
        selectedChecklistId: selectedChecklistId.value,
        selectedColumnKey: selectedColumnKey.value,
    };

    try {
        localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draft));
        hasDraft.value = true;
    } catch (e) {
        console.error('Failed to save draft:', e);
    }
};

const deleteDraft = () => {
    try {
        localStorage.removeItem(DRAFT_STORAGE_KEY);
        hasDraft.value = false;
    } catch (e) {
        console.error('Failed to delete draft:', e);
    }
};

const clearNotes = () => {
    noteContent.value = '';
    deleteDraft();
};

const openDraft = () => {
    try {
        const saved = localStorage.getItem(DRAFT_STORAGE_KEY);
        if (saved) {
            const draft: NoteDraft = JSON.parse(saved);
            noteContent.value = draft.content;
            selectedChecklistId.value = draft.selectedChecklistId;
            selectedColumnKey.value = draft.selectedColumnKey;
        }
    } catch (e) {
        console.error('Failed to open draft:', e);
    }
};

const onNoteDialogChange = (open: boolean) => {
    if (open && hasDraft.value) {
        openDraft();
    }
    if (!open && noteContent.value.trim()) {
        saveDraft();
    }
    if (!open) {
        setTimeout(() => {
            noteContent.value = '';
        }, 200);
    }
};

// Get selected checklist
const selectedChecklist = computed(() => {
    return props.checklists.find(c => c.id === selectedChecklistId.value) || null;
});

// Get available text columns for the selected checklist
const availableColumns = computed(() => {
    if (!selectedChecklist.value?.columns_config) {
        return [{ key: 'item', label: 'Item', type: 'text' as const }];
    }
    return selectedChecklist.value.columns_config.filter(col => col.type === 'text');
});

// Find default column key (prefer "check" or "item")
const getDefaultColumnKey = (cols: ColumnConfig[]) => {
    const checkColumn = cols.find(
        col => col.key.toLowerCase().includes('check') || col.label.toLowerCase().includes('check')
    );
    if (checkColumn) return checkColumn.key;

    const itemColumn = cols.find(
        col => col.key.toLowerCase().includes('item') || col.label.toLowerCase().includes('item')
    );
    if (itemColumn) return itemColumn.key;

    return cols[0]?.key || 'item';
};

// Initialize with default column for current checklist
const selectedColumnKey = ref<string>(getDefaultColumnKey(columns.value.filter(col => col.type === 'text')));

// Set default column when checklist changes
watch(selectedChecklistId, () => {
    selectedColumnKey.value = getDefaultColumnKey(availableColumns.value);
}, { immediate: true });

// Parse notes into array of items
const parsedNotes = computed(() => {
    if (!noteContent.value.trim()) return [];

    return noteContent.value
        .split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0)
        .map(line => {
            // Remove leading numbers, dots, dashes, etc.
            return line.replace(/^[\d]+[.\)\-:\s]+/, '').trim();
        })
        .filter(line => line.length > 0);
});

// Import notes to checklist
const importNotes = () => {
    if (parsedNotes.value.length === 0 || !selectedChecklistId.value || !selectedColumnKey.value) return;

    isImporting.value = true;

    router.post(
        `/projects/${props.project.id}/checklists/${selectedChecklistId.value}/import-notes`,
        {
            notes: parsedNotes.value,
            column_key: selectedColumnKey.value,
        },
        {
            preserveState: false,
            preserveScroll: true,
            onSuccess: () => {
                showNoteDialog.value = false;
                noteContent.value = '';
                isImporting.value = false;
                deleteDraft();
            },
            onError: () => {
                isImporting.value = false;
            },
        }
    );
};


// Drag and drop for rows
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

// Drag and drop for columns
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
        // Map visible indices to actual indices in the full columns array
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
    // Map visible index to actual index in the full columns array
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

const predefinedColors = [
    { name: 'Red', value: '#fee2e2', text: '#dc2626' },
    { name: 'Orange', value: '#ffedd5', text: '#ea580c' },
    { name: 'Yellow', value: '#fef9c3', text: '#ca8a04' },
    { name: 'Green', value: '#dcfce7', text: '#16a34a' },
    { name: 'Blue', value: '#dbeafe', text: '#2563eb' },
    { name: 'Purple', value: '#f3e8ff', text: '#9333ea' },
    { name: 'Pink', value: '#fce7f3', text: '#db2777' },
    { name: 'Gray', value: '#f3f4f6', text: '#4b5563' },
];

const getTextColorForBg = (bgColor: string | undefined): string => {
    if (!bgColor) return '#2563eb';
    const found = predefinedColors.find(c => c.value === bgColor);
    return found?.text || '#2563eb';
};

const getSelectedOption = (column: ExtendedColumnConfig, value: unknown): SelectOption | undefined => {
    if (!column.options || !value) return undefined;
    return column.options.find(opt => opt.value === value || opt.label === value);
};

const fontColors = [
    { name: 'Black', value: '#000000' },
    { name: 'Gray', value: '#6b7280' },
    { name: 'Red', value: '#dc2626' },
    { name: 'Orange', value: '#ea580c' },
    { name: 'Yellow', value: '#ca8a04' },
    { name: 'Green', value: '#16a34a' },
    { name: 'Blue', value: '#2563eb' },
    { name: 'Purple', value: '#9333ea' },
];

const fontWeights = [
    { label: 'Normal', value: 'normal' },
    { label: 'Medium', value: 'medium' },
    { label: 'Semi Bold', value: 'semibold' },
    { label: 'Bold', value: 'bold' },
];

const addRow = (type: 'normal' | 'section_header' = 'normal') => {
    const newData: Record<string, unknown> = {};
    columns.value.forEach(col => {
        newData[col.key] = col.type === 'checkbox' ? false : '';
    });
    rows.value.push({
        id: Date.now(),
        checklist_id: props.checklist.id,
        data: newData,
        order: rows.value.length,
        row_type: type,
        background_color: type === 'section_header' ? '#dbeafe' : null,
        font_color: null,
        font_weight: type === 'section_header' ? 'bold' : 'normal',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        _isNew: true,
    });
    // Ensure new row is visible
    if (visibleRowCount.value < rows.value.length) {
        visibleRowCount.value = rows.value.length;
    }
    // Auto-save after adding row
    nextTick(() => saveRows());
};

const openAddRowsDialog = (index: number, position: 'above' | 'below' | 'end', type: 'normal' | 'section_header' = 'normal') => {
    addRowsAtIndex.value = index;
    addRowsPosition.value = position;
    addRowsType.value = type;
    addRowsCount.value = 1;
    showAddRowsDialog.value = true;
};

const insertRows = () => {
    const count = Math.max(1, Math.min(100, addRowsCount.value)); // Limit 1-100
    let insertIndex: number;

    if (addRowsPosition.value === 'end') {
        insertIndex = rows.value.length;
    } else if (addRowsPosition.value === 'above') {
        insertIndex = addRowsAtIndex.value;
    } else {
        insertIndex = addRowsAtIndex.value + 1;
    }

    const rowType = addRowsType.value;
    const newRows: ExtendedChecklistRow[] = [];
    for (let i = 0; i < count; i++) {
        const newData: Record<string, unknown> = {};
        columns.value.forEach(col => {
            newData[col.key] = col.type === 'checkbox' ? false : '';
        });
        newRows.push({
            id: Date.now() + i,
            checklist_id: props.checklist.id,
            data: newData,
            order: 0,
            row_type: rowType,
            background_color: rowType === 'section_header' ? '#dbeafe' : null,
            font_color: null,
            font_weight: rowType === 'section_header' ? 'bold' : 'normal',
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
            _isNew: true,
        });
    }

    rows.value.splice(insertIndex, 0, ...newRows);
    showAddRowsDialog.value = false;

    // Ensure new rows are visible
    const lastNewRowIndex = insertIndex + newRows.length;
    if (visibleRowCount.value < lastNewRowIndex) {
        visibleRowCount.value = lastNewRowIndex;
    }

    // Auto-save after adding rows
    nextTick(() => {
        saveRows();
    });
};

const showDeleteSelectedConfirm = ref(false);

const removeSelectedRows = () => {
    const selectedIds = new Set(selectedRows.value.map(r => r.id));
    rows.value = rows.value.filter(r => !selectedIds.has(r.id));
    showDeleteSelectedConfirm.value = false;
    hasContentChanges.value = false;
    nextTick(() => saveRows());
};

const confirmRemoveRow = (index: number) => {
    rowToDeleteIndex.value = index;
    showDeleteConfirm.value = true;
};

const removeRow = () => {
    if (rowToDeleteIndex.value !== null) {
        rows.value.splice(rowToDeleteIndex.value, 1);
        rowToDeleteIndex.value = null;
        showDeleteConfirm.value = false;
        hasContentChanges.value = false;
        nextTick(() => saveRows());
    }
};

const saveRows = () => {
    saveError.value = false;
    isSaving.value = true;

    const rowsData = rows.value.map((row, index) => ({
        id: row._isNew ? null : row.id,
        data: row.data,
        order: index,
        row_type: row.row_type,
        background_color: row.background_color,
        font_color: row.font_color,
        font_weight: row.font_weight,
    }));

    router.put(
        `/projects/${props.project.id}/checklists/${props.checklist.id}/rows`,
        {
            rows: rowsData,
            columns_config: columns.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                hasContentChanges.value = false;
                saveError.value = false;
                isSaving.value = false;
                // Shift: previous saved = what was last saved, last saved = current
                previousSavedState.value = lastSavedState;
                lastSavedState = {
                    rows: JSON.parse(JSON.stringify(rows.value)),
                    columns: JSON.parse(JSON.stringify(columns.value)),
                };
            },
            onError: () => {
                saveError.value = true;
                isSaving.value = false;
            },
        }
    );
};

const selectColumnKeys = computed(() => columns.value.filter(c => c.type === 'select').map(c => c.key));

let checkboxSaveTimer: ReturnType<typeof setTimeout> | null = null;

const updateCell = (row: ExtendedChecklistRow, key: string, value: unknown) => {
    row.data[key] = value;
    // Auto-save immediately for select columns
    if (selectColumnKeys.value.includes(key)) {
        nextTick(() => saveRows());
    } else if (checkboxKeys.value.includes(key)) {
        // Debounced save for checkboxes — waits 1.5s after last toggle
        if (checkboxSaveTimer) clearTimeout(checkboxSaveTimer);
        checkboxSaveTimer = setTimeout(() => {
            checkboxSaveTimer = null;
            saveRows();
        }, 1500);
    } else {
        hasContentChanges.value = true;
    }
};

const saveOnBlur = () => {
    if (hasContentChanges.value) {
        saveRows();
    }
};

const setBackgroundColor = (row: ExtendedChecklistRow, color: string | null) => {
    row.background_color = color;
    hasContentChanges.value = true;
};

const setFontColor = (row: ExtendedChecklistRow, color: string | null) => {
    row.font_color = color;
    hasContentChanges.value = true;
};

const setFontWeight = (row: ExtendedChecklistRow, weight: 'normal' | 'medium' | 'semibold' | 'bold') => {
    row.font_weight = weight;
    hasContentChanges.value = true;
};

const toggleRowType = (row: ExtendedChecklistRow) => {
    if (row.row_type === 'normal') {
        row.row_type = 'section_header';
        row.background_color = row.background_color || '#dbeafe';
        row.font_weight = 'bold';
    } else {
        row.row_type = 'normal';
        row.background_color = null;
        row.font_weight = 'normal';
    }
    hasContentChanges.value = true;
};

const getRowStyles = (row: ExtendedChecklistRow) => {
    const styles: Record<string, string> = {};
    if (row.background_color) {
        styles.backgroundColor = row.background_color;
        if (!row.font_color) {
            // Force dark text on colored backgrounds so it's readable in dark theme
            styles.color = '#1f2937';
        }
    }
    if (row.font_color) {
        styles.color = row.font_color;
    }
    return styles;
};

const getFontWeightClass = (weight: string) => {
    switch (weight) {
        case 'medium': return 'font-medium';
        case 'semibold': return 'font-semibold';
        case 'bold': return 'font-bold';
        default: return 'font-normal';
    }
};

// Get all checkbox column keys
const checkboxColumnKeys = computed(() => {
    return columns.value.filter(col => col.type === 'checkbox').map(col => col.key);
});

// Get selected rows (rows with any checkbox checked)
const selectedRows = computed(() => {
    const checkboxKeys = checkboxColumnKeys.value;
    if (checkboxKeys.length === 0) return [];

    return rows.value.filter(row => {
        if (row.row_type === 'section_header') return false;
        return checkboxKeys.some(key => !!row.data[key]);
    });
});

// Check if any rows are selected
const hasSelectedRows = computed(() => selectedRows.value.length > 0);

// Get select columns for "Change Status" action
const selectColumns = computed(() => columns.value.filter(col => col.type === 'select' && col.options?.length));

// Clean up filter values when select columns change
watch(selectColumns, (newCols) => {
    const validKeys = new Set(newCols.map(c => c.key));
    for (const key of Object.keys(filterValues.value)) {
        if (!validKeys.has(key)) {
            delete filterValues.value[key];
        } else {
            const col = newCols.find(c => c.key === key);
            const validOptions = new Set(col?.options?.map(o => o.value) || []);
            if (filterValues.value[key] && !validOptions.has(filterValues.value[key])) {
                filterValues.value[key] = '';
            }
        }
    }
}, { deep: true });

// Change status of selected rows for a given column
const changeSelectedStatus = (columnKey: string, value: string) => {
    selectedRows.value.forEach(row => {
        row.data[columnKey] = value;
    });
    rows.value = [...rows.value];
    hasContentChanges.value = true;
    nextTick(() => saveRows());
};

// Open copy to checklist dialog
const openCopyToChecklistDialog = () => {
    if (otherChecklists.value.length === 0) return;
    copyTargetChecklistId.value = otherChecklists.value[0].id;
    copyTargetSectionId.value = null;
    showCopyToChecklistDialog.value = true;
};

// Copy selected rows to another checklist
const copyToChecklist = () => {
    if (!copyTargetChecklistId.value || selectedRows.value.length === 0) return;

    isCopying.value = true;

    const rowsPayload = selectedRows.value.map(row => ({
        data: row.data,
        row_type: row.row_type || 'normal',
        background_color: row.background_color || null,
        font_color: row.font_color || null,
        font_weight: row.font_weight || 'normal',
    }));

    router.post(
        `/projects/${props.project.id}/checklists/${copyTargetChecklistId.value}/copy-rows`,
        {
            rows: rowsPayload,
            section_row_id: copyTargetSectionId.value,
            source_columns_config: columns.value.map(c => ({ key: c.key, label: c.label, type: c.type })),
        },
        {
            preserveState: false,
            onSuccess: () => {
                showCopyToChecklistDialog.value = false;
                isCopying.value = false;
            },
            onError: () => {
                isCopying.value = false;
            },
        }
    );
};

// Copy selected rows to internal app clipboard (localStorage)
const copyRowsToClipboard = () => {
    if (selectedRows.value.length === 0) return;

    const payload: ClipboardData = {
        rows: selectedRows.value.map(row => ({
            data: { ...row.data },
            row_type: row.row_type || 'normal',
            background_color: row.background_color || null,
            font_color: row.font_color || null,
            font_weight: row.font_weight || 'normal',
        })),
        source_columns_config: columns.value.map(c => ({ key: c.key, label: c.label, type: c.type, options: c.options })),
        source_checklist_name: props.checklist.name,
        timestamp: Date.now(),
    };

    try {
        localStorage.setItem(CLIPBOARD_KEY, JSON.stringify(payload));
        clipboardData.value = payload;
        copiedRows.value = true;
        setTimeout(() => { copiedRows.value = false; }, 2000);
    } catch (e) {
        console.error('Failed to save to clipboard:', e);
    }
};

// Paste rows dialog state
const showPasteDialog = ref(false);
const pasteSectionId = ref<number | null>(null);
const isPasting = ref(false);

const currentSectionHeaders = computed(() => {
    return rows.value
        .filter(r => r.row_type === 'section_header')
        .map(r => {
            const labelKey = columns.value[0]?.key || 'item';
            return {
                id: r.id,
                label: String(r.data[labelKey] || `Section ${r.order}`),
            };
        });
});

const openPasteDialog = () => {
    pasteSectionId.value = null;
    showPasteDialog.value = true;
};

const pasteRows = () => {
    if (!clipboardData.value || clipboardData.value.rows.length === 0) return;

    isPasting.value = true;

    const payload = {
        rows: clipboardData.value.rows,
        section_row_id: pasteSectionId.value,
        source_columns_config: clipboardData.value.source_columns_config,
    };

    // Clear localStorage before POST so the re-rendered component won't reload it
    const backup = localStorage.getItem(CLIPBOARD_KEY);
    clearClipboard();

    router.post(
        `/projects/${props.project.id}/checklists/${props.checklist.id}/copy-rows`,
        payload,
        {
            preserveState: false,
            onError: () => {
                // Restore clipboard on error
                if (backup) {
                    localStorage.setItem(CLIPBOARD_KEY, backup);
                    loadClipboard();
                }
                isPasting.value = false;
            },
        }
    );
};

// Create bugreport from selected rows
const createBugreportFromSelected = () => {
    const textColumns = columns.value.filter(col => col.type === 'text');
    const lines: string[] = [];

    selectedRows.value.forEach((row, idx) => {
        const parts: string[] = [];
        textColumns.forEach(col => {
            const val = row.data[col.key];
            if (typeof val === 'string' && val.trim()) {
                parts.push(val.trim());
            }
        });
        if (parts.length > 0) {
            lines.push(`${idx + 1}. ${parts.join(' — ')}`);
        }
    });

    const stepsText = lines.join('\n');
    const params = new URLSearchParams();
    params.set('title', `[${props.checklist.name}] Bug`);
    if (stepsText) {
        params.set('steps_to_reproduce', stepsText);
    }

    // Pass checklist context for auto-link-back
    params.set('checklist_id', String(props.checklist.id));

    const persistedRowIds = selectedRows.value
        .filter(row => !row._isNew && row.id)
        .map(row => row.id);
    if (persistedRowIds.length > 0) {
        params.set('checklist_row_ids', persistedRowIds.join(','));
    }

    const bugreportColumn = columns.value.find(col =>
        col.key.toLowerCase().includes('bugreport') || col.label.toLowerCase().includes('bugreport'),
    );
    if (bugreportColumn) {
        params.set('checklist_link_column', bugreportColumn.key);
    }

    router.get(`/projects/${props.project.id}/bugreports/create?${params.toString()}`);
};

// Create test case from selected rows
const showTestCaseDialog = ref(false);
const testCaseTargetSuiteId = ref<number | null>(null);
const testCaseTargetChildId = ref<number | null>(null);

const selectedParentSuiteChildren = computed(() => {
    if (!testCaseTargetSuiteId.value) return [];
    const parent = props.testSuites.find(s => s.id === testCaseTargetSuiteId.value);
    if (!parent?.children?.length) return [];
    return [...parent.children].sort((a, b) => a.order - b.order);
});

watch(testCaseTargetSuiteId, () => {
    testCaseTargetChildId.value = null;
});

const effectiveTestSuiteId = computed(() => testCaseTargetChildId.value ?? testCaseTargetSuiteId.value);

const openTestCaseDialog = () => {
    if (props.testSuites.length === 0) return;
    testCaseTargetSuiteId.value = props.testSuites[0].id;
    testCaseTargetChildId.value = null;
    showTestCaseDialog.value = true;
};

const createTestCaseFromSelected = () => {
    if (!effectiveTestSuiteId.value) return;

    const textColumns = columns.value.filter(col => col.type === 'text');
    const steps = selectedRows.value.map(row => {
        const parts: string[] = [];
        textColumns.forEach(col => {
            const val = row.data[col.key];
            if (typeof val === 'string' && val.trim()) {
                parts.push(val.trim());
            }
        });
        return { action: parts.join(' — '), expected: '' };
    }).filter(s => s.action);

    const params = new URLSearchParams();
    params.set('title', `[${props.checklist.name}] Test Case`);
    if (steps.length > 0) {
        params.set('steps', JSON.stringify(steps));
    }

    // Pass checklist context for auto-link-back
    params.set('checklist_id', String(props.checklist.id));

    const persistedRowIds = selectedRows.value
        .filter(row => !row._isNew && row.id)
        .map(row => row.id);
    if (persistedRowIds.length > 0) {
        params.set('checklist_row_ids', persistedRowIds.join(','));
    }

    // Auto-detect test case link column
    const testCaseColumn = columns.value.find(col =>
        col.key.toLowerCase().includes('testcase') ||
        col.key.toLowerCase().includes('test_case') ||
        col.label.toLowerCase().includes('testcase') ||
        col.label.toLowerCase().includes('test case'),
    ) || columns.value.find(col =>
        col.key.toLowerCase().includes('test') ||
        col.label.toLowerCase().includes('test'),
    );
    if (testCaseColumn) {
        params.set('checklist_link_column', testCaseColumn.key);
    }

    showTestCaseDialog.value = false;
    router.get(`/projects/${props.project.id}/test-suites/${effectiveTestSuiteId.value}/test-cases/create?${params.toString()}`);
};

// Create test run from selected rows
const showTestRunDialog = ref(false);
const testRunName = ref('');
const testRunDescription = ref('');
const testRunEnvPreset = ref('');
const testRunEnvNotes = ref('');
const testRunMilestone = ref('');
const testRunPriority = ref('');
const testRunColumnKey = ref('');
const isCreatingTestRun = ref(false);

const textColumnsForTestRun = computed(() => columns.value.filter(col => col.type === 'text'));

const findCheckColumn = (): string => {
    const text = textColumnsForTestRun.value;
    const match = text.find(col =>
        col.key.toLowerCase() === 'check' ||
        col.label.toLowerCase() === 'check',
    ) ?? text.find(col =>
        col.key.toLowerCase().includes('check') ||
        col.label.toLowerCase().includes('check'),
    );
    return match?.key ?? text[0]?.key ?? '';
};

const openTestRunDialog = () => {
    testRunName.value = `${props.checklist.name} Test Run`;
    testRunDescription.value = '';
    testRunEnvPreset.value = '';
    testRunEnvNotes.value = '';
    testRunMilestone.value = '';
    testRunPriority.value = '';
    testRunColumnKey.value = findCheckColumn();
    showTestRunDialog.value = true;
};

const createTestRunFromSelected = () => {
    if (isCreatingTestRun.value || !testRunColumnKey.value) return;

    const titles = selectedRows.value.map(row => {
        const val = row.data[testRunColumnKey.value];
        return typeof val === 'string' ? val.trim() : '';
    }).filter(t => t.length > 0);

    if (titles.length === 0) return;

    isCreatingTestRun.value = true;
    const envParts = [testRunEnvPreset.value, testRunEnvNotes.value.trim()].filter(Boolean);
    router.post(`/projects/${props.project.id}/test-runs/from-checklist`, {
        name: testRunName.value,
        description: testRunDescription.value || null,
        priority: testRunPriority.value || null,
        environment: envParts.join(' — ') || null,
        milestone: testRunMilestone.value || null,
        checklist_id: props.checklist.id,
        titles,
    }, {
        onFinish: () => {
            isCreatingTestRun.value = false;
            showTestRunDialog.value = false;
        },
    });
};

// Check if row has any content (non-empty text fields)
const rowHasContent = (row: ExtendedChecklistRow, checkboxColumnKey: string): boolean => {
    return Object.entries(row.data).some(([key, value]) => {
        // Skip the checkbox column itself
        if (key === checkboxColumnKey) return false;
        // Check if value is non-empty string (excluding default placeholders)
        if (typeof value === 'string') {
            const trimmed = value.trim().toLowerCase();
            if (trimmed.length === 0) return false;
            if (trimmed === 'select...' || trimmed === 'select') return false;
            return true;
        }
        return false;
    });
};

// Get checkbox header state: true, false, or 'indeterminate' (only considers rows with content)
const getHeaderCheckboxState = (columnKey: string): boolean | 'indeterminate' => {
    const rowsWithContent = rows.value.filter(r =>
        r.row_type !== 'section_header' && rowHasContent(r, columnKey)
    );

    if (rowsWithContent.length === 0) return false;

    const checkedCount = rowsWithContent.filter(row => !!row.data[columnKey]).length;

    if (checkedCount === 0) return false;
    if (checkedCount === rowsWithContent.length) return true;
    return 'indeterminate';
};

// Toggle all checkboxes in a column (only for rows with content)
const toggleAllCheckboxes = (columnKey: string) => {
    // Get rows that have content
    const rowsWithContent = rows.value.filter(r =>
        r.row_type !== 'section_header' && rowHasContent(r, columnKey)
    );

    if (rowsWithContent.length === 0) return;

    const checkedCount = rowsWithContent.filter(row => !!row.data[columnKey]).length;
    const allChecked = checkedCount === rowsWithContent.length;
    const newValue = !allChecked;

    // Update only rows with content
    rowsWithContent.forEach(row => {
        updateCell(row, columnKey, newValue);
    });

    // Force Vue to detect the change
    rows.value = [...rows.value];
};

const autoResizeTextarea = (el: HTMLTextAreaElement) => {
    // First, resize the textarea to fit its content
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';

    // Then, sync all textareas in the same row to the max height
    const row = el.closest('tr');
    if (row) {
        const textareas = row.querySelectorAll('textarea');
        let maxHeight = 0;
        textareas.forEach((textarea) => {
            const ta = textarea as HTMLTextAreaElement;
            ta.style.height = 'auto';
            maxHeight = Math.max(maxHeight, ta.scrollHeight);
        });
        textareas.forEach((textarea) => {
            (textarea as HTMLTextAreaElement).style.height = maxHeight + 'px';
        });
    }
};

const resizeAllTextareas = () => {
    nextTick(() => {
        const textareas = document.querySelectorAll('table textarea');
        textareas.forEach((el) => {
            autoResizeTextarea(el as HTMLTextAreaElement);
        });
    });
};

// Import file dialog state
const showImportDialog = ref(false);
const importFile = ref<File | null>(null);
const importError = ref<string | null>(null);
const isUploadingFile = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

const page = usePage();

// Get errors from page props
const pageErrors = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;
    return errors || {};
});

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    importError.value = null;

    if (file) {
        // Validate file type
        const validTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
        const validExtensions = ['.csv', '.txt'];
        const hasValidExtension = validExtensions.some(ext => file.name.toLowerCase().endsWith(ext));

        if (!validTypes.includes(file.type) && !hasValidExtension) {
            importError.value = 'Please select a valid CSV file.';
            importFile.value = null;
            return;
        }

        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            importError.value = 'File size must not exceed 5MB.';
            importFile.value = null;
            return;
        }

        importFile.value = file;
    }
};

const submitImport = () => {
    if (!importFile.value) {
        importError.value = 'Please select a file to import.';
        return;
    }

    isUploadingFile.value = true;
    importError.value = null;

    const formData = new FormData();
    formData.append('file', importFile.value);

    router.post(
        `/projects/${props.project.id}/checklists/${props.checklist.id}/import`,
        formData,
        {
            forceFormData: true,
            preserveState: false,
            preserveScroll: false,
            onSuccess: () => {
                showImportDialog.value = false;
                importFile.value = null;
                isUploadingFile.value = false;
                if (fileInputRef.value) {
                    fileInputRef.value.value = '';
                }
            },
            onError: (errors) => {
                isUploadingFile.value = false;
                if (errors.file) {
                    importError.value = errors.file;
                } else {
                    importError.value = 'An error occurred during import.';
                }
            },
        }
    );
};

const closeImportDialog = () => {
    showImportDialog.value = false;
    importFile.value = null;
    importError.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const exportChecklist = () => {
    window.location.href = `/projects/${props.project.id}/checklists/${props.checklist.id}/export`;
};

onMounted(() => {
    loadHiddenColumns();
    resizeAllTextareas();
    loadDraft();
    loadClipboard();
});

</script>

<template>
    <Head :title="checklist.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold tracking-tight">
                    <ClipboardList class="inline-block h-6 w-6 align-text-top text-primary mr-2" />{{ titleStart }}<span class="whitespace-nowrap">{{ titleEnd }}<button
                        @click="copyLink"
                        class="inline-flex align-middle ml-1.5 p-1 rounded-md text-muted-foreground hover:text-primary hover:bg-muted transition-colors cursor-pointer"
                        :title="copied ? 'Copied!' : 'Copy link'"
                    ><Check v-if="copied" class="h-4 w-4 text-green-500" /><Link2 v-else class="h-4 w-4" /></button></span>
                </h1>
                <FeatureBadges v-if="checklist.project_features?.length" :features="checklist.project_features" :max-visible="3" />
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative">
                    <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search content..."
                        class="pl-9 pr-8 w-56 bg-background/60"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5 relative text-xs"
                    @click="showFilters = !showFilters"
                >
                    <Filter class="h-3.5 w-3.5" />
                    Filter
                    <Badge
                        v-if="activeFilterCount > 0"
                        class="absolute -top-2 -right-2 h-5 w-5 p-0 flex items-center justify-center text-[10px] rounded-full"
                    >
                        {{ activeFilterCount }}
                    </Badge>
                </Button>
                <span v-if="isSearchActive && filteredDataRowCount > 0" class="text-xs text-muted-foreground whitespace-nowrap">
                    {{ filteredDataRowCount }} found — click to navigate
                </span>
                <span v-else-if="activeFilterCount > 0" class="text-xs text-muted-foreground whitespace-nowrap">
                    {{ filteredDataRowCount }} {{ filteredDataRowCount === 1 ? 'row' : 'rows' }} found
                </span>
                <div class="flex items-center gap-1.5 ml-auto">
                    <!-- Undo last save -->
                    <RestrictedAction>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :disabled="!canUndo"
                            @click="undoLastSave"
                            title="Undo last save"
                        >
                            <Undo2 class="h-3.5 w-3.5" />
                        </Button>
                    </RestrictedAction>
                    <!-- Actions dropdown when rows are selected -->
                    <RestrictedAction>
                        <DropdownMenu v-if="hasSelectedRows">
                            <DropdownMenuTrigger as-child>
                                <Button size="sm" class="gap-1.5 text-xs">
                                    <MoreHorizontal class="h-3.5 w-3.5" />
                                    Actions ({{ selectedRows.length }})
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>Selected Rows</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="createBugreportFromSelected">
                                    <Bug class="h-4 w-4 mr-2" />
                                    Create Bugreport
                                </DropdownMenuItem>
                                <DropdownMenuSub v-if="selectColumns.length > 0">
                                    <DropdownMenuSubTrigger>
                                        <RefreshCw class="h-4 w-4 mr-2" />
                                        Change Status
                                    </DropdownMenuSubTrigger>
                                    <DropdownMenuSubContent>
                                        <template v-for="col in selectColumns" :key="col.key">
                                            <DropdownMenuLabel v-if="selectColumns.length > 1">{{ col.label }}</DropdownMenuLabel>
                                            <DropdownMenuItem
                                                v-for="option in col.options"
                                                :key="option.value"
                                                @click="changeSelectedStatus(col.key, option.value)"
                                            >
                                                <span
                                                    class="px-2 py-0.5 rounded text-xs font-medium"
                                                    :style="{
                                                        backgroundColor: option.color || '#dbeafe',
                                                        color: getTextColorForBg(option.color)
                                                    }"
                                                >
                                                    {{ option.label }}
                                                </span>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator v-if="selectColumns.length > 1" />
                                        </template>
                                    </DropdownMenuSubContent>
                                </DropdownMenuSub>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="openCopyToChecklistDialog" :disabled="otherChecklists.length === 0">
                                    <Copy class="h-4 w-4 mr-2" />
                                    Copy to Checklist
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="openTestCaseDialog" :disabled="testSuites.length === 0">
                                    <Layers class="h-4 w-4 mr-2" />
                                    Create Test Case
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="openTestRunDialog">
                                    <Play class="h-4 w-4 mr-2" />
                                    Create Test Run
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem class="text-destructive focus:text-destructive" @click="showDeleteSelectedConfirm = true">
                                    <Trash2 class="h-4 w-4 mr-2" />
                                    Delete Rows
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </RestrictedAction>
                    <!-- Copy Rows / Paste Rows — mutually exclusive -->
                    <Button
                        v-if="hasClipboardRows"
                        variant="outline"
                        size="sm"
                        @click="openPasteDialog"
                        class="gap-1.5 text-xs cursor-pointer"
                    >
                        <ClipboardList class="h-3.5 w-3.5" />
                        Paste ({{ clipboardData?.rows.length }})
                    </Button>
                    <Button
                        v-else-if="hasSelectedRows"
                        variant="outline"
                        size="sm"
                        @click="copyRowsToClipboard"
                        class="gap-1.5 text-xs cursor-pointer"
                    >
                        <Copy class="h-3.5 w-3.5" />
                        {{ copiedRows ? 'Copied!' : 'Copy Row' }}
                    </Button>
                    <!-- Save Changes button when no rows selected but there are content changes -->
                    <RestrictedAction>
                        <Button
                            v-if="!hasSelectedRows && hasContentChanges"
                            size="sm"
                            @click="saveRows"
                            class="gap-1.5 text-xs"
                        >
                            <Save class="h-3.5 w-3.5" />
                            Save
                        </Button>
                    </RestrictedAction>
                    <!-- Import/Export dropdown -->
                    <RestrictedAction>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm" class="gap-1.5 text-xs">
                                    <FileSpreadsheet class="h-3.5 w-3.5" />
                                    File
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="showImportDialog = true">
                                    <Download class="h-4 w-4 mr-2" />
                                    Import
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="exportChecklist">
                                    <Upload class="h-4 w-4 mr-2" />
                                    Export
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </RestrictedAction>
                    <!-- Columns visibility dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button :variant="hasHiddenColumns ? 'default' : 'outline'" size="sm" class="gap-1.5 text-xs">
                                <Columns3 class="h-3.5 w-3.5" />
                                Cols
                                <span v-if="hasHiddenColumns" class="ml-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-background/20 text-[10px] font-medium">
                                    {{ hiddenColumns.length }}
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Toggle Columns</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="column in columns"
                                :key="column.key"
                                @select.prevent="toggleColumnVisibility(column.key)"
                            >
                                <Check v-if="!hiddenColumns.includes(column.key)" class="h-4 w-4 mr-2" />
                                <span v-else class="h-4 w-4 mr-2" />
                                {{ column.label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Dialog v-model:open="showNoteDialog" @update:open="onNoteDialogChange">
                        <RestrictedAction>
                            <DialogTrigger as-child>
                                <Button
                                    :variant="hasDraft ? 'cta' : 'outline'"
                                    size="sm"
                                    class="gap-1.5 text-xs"
                                >
                                    <Pencil v-if="hasDraft" class="h-3.5 w-3.5" />
                                    <StickyNote v-else class="h-3.5 w-3.5" />
                                    {{ hasDraft ? 'Draft' : 'Note' }}
                                </Button>
                            </DialogTrigger>
                        </RestrictedAction>
                        <DialogContent class="max-w-2xl max-h-[75vh] flex flex-col" style="overflow: hidden !important; max-width: min(42rem, calc(100vw - 2rem)) !important;">
                            <DialogHeader>
                                <DialogTitle class="flex items-center gap-2">
                                    <StickyNote class="h-5 w-5 text-primary" />
                                    {{ hasDraft ? 'Edit Draft' : 'Create a Note' }}
                                </DialogTitle>
                                <DialogDescription>
                                    Write your notes below. Each line will become a separate row in the selected checklist.
                                </DialogDescription>
                            </DialogHeader>

                            <div class="space-y-4 py-4 px-0.5 overflow-y-auto min-h-0 flex-1">
                                <div class="space-y-2">
                                    <Label>Notes</Label>
                                    <Textarea
                                        :model-value="noteContent"
                                        @update:model-value="noteContent = $event"
                                        placeholder="1. First item&#10;2. Second item&#10;3. Third item&#10;&#10;Or just write each item on a new line..."
                                        rows="10"
                                        class="font-mono text-sm resize-y"
                                        style="white-space: pre-wrap; overflow-wrap: break-word; overflow-y: auto; max-height: 400px;"
                                    />
                                    <p v-if="parsedNotes.length > 0" class="text-sm text-muted-foreground">
                                        {{ parsedNotes.length }} item(s) will be imported
                                    </p>
                                </div>

                                <div v-if="parsedNotes.length > 0" class="space-y-4 rounded-lg border p-4 bg-muted/30">
                                    <div class="space-y-2">
                                        <Label>Import to Checklist</Label>
                                        <Select v-model="selectedChecklistId">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a checklist..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="cl in checklists" :key="cl.id" :value="cl.id">
                                                    {{ cl.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div v-if="selectedChecklistId && availableColumns.length > 0" class="space-y-2">
                                        <Label>Column</Label>
                                        <Select v-model="selectedColumnKey">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a column..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="col in availableColumns" :key="col.key" :value="col.key">
                                                    {{ col.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="space-y-2 overflow-hidden">
                                        <Label>Preview</Label>
                                        <div class="max-h-40 overflow-auto rounded border bg-background p-2 text-sm" style="word-wrap: break-word; overflow-wrap: break-word;">
                                            <ol class="list-decimal list-inside space-y-1">
                                                <li v-for="(note, index) in parsedNotes.slice(0, 10)" :key="index" class="break-words whitespace-pre-wrap" style="overflow-wrap: break-word; word-break: break-all;">
                                                    {{ note }}
                                                </li>
                                                <li v-if="parsedNotes.length > 10" class="text-muted-foreground">
                                                    ... and {{ parsedNotes.length - 10 }} more
                                                </li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter class="flex justify-between sm:justify-between">
                                <Button
                                    v-if="noteContent.trim()"
                                    variant="ghost"
                                    @click="clearNotes"
                                    class="gap-2 text-muted-foreground hover:text-destructive"
                                >
                                    <X class="h-4 w-4" />
                                    Clear
                                </Button>
                                <div v-else></div>
                                <div class="flex gap-2">
                                    <Button variant="outline" @click="showNoteDialog = false">
                                        Cancel
                                    </Button>
                                    <RestrictedAction>
                                        <Button
                                            @click="importNotes"
                                            :disabled="!selectedChecklistId || parsedNotes.length === 0 || !selectedColumnKey || isImporting"
                                            class="gap-2"
                                        >
                                            <Import class="h-4 w-4" />
                                            Import to Checklist
                                        </Button>
                                    </RestrictedAction>
                                </div>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    <RestrictedAction>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm" class="gap-1.5 text-xs">
                                    <Plus class="h-3.5 w-3.5" />
                                    Add Row
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="openAddRowsDialog(rows.length, 'end', 'normal')">
                                    <Plus class="h-4 w-4 mr-2" />
                                    Normal Row
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="openAddRowsDialog(rows.length, 'end', 'section_header')">
                                    <Heading class="h-4 w-4 mr-2" />
                                    Section Header
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </RestrictedAction>
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/checklists/${checklist.id}/edit`">
                            <Button variant="outline" size="sm" class="gap-1.5 text-xs">
                                <Edit class="h-3.5 w-3.5" />
                                Edit
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <!-- Save error banner with undo -->
            <div
                v-if="saveError"
                class="flex items-center justify-between gap-3 rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3"
            >
                <div class="flex items-center gap-2 text-sm text-destructive">
                    <AlertCircle class="h-4 w-4 shrink-0" />
                    <span>Failed to save changes. You can undo to restore the previous state.</span>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="saveSnapshot"
                        variant="outline"
                        size="sm"
                        class="gap-1.5"
                        @click="undoChanges"
                    >
                        <Undo2 class="h-3.5 w-3.5" />
                        Undo
                    </Button>
                    <button
                        class="text-muted-foreground hover:text-foreground"
                        @click="dismissSaveError"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Filter Dropdown -->
            <div v-if="showFilters" class="relative z-20">
                <div class="rounded-xl border bg-card shadow-lg p-4 animate-in fade-in slide-in-from-top-2 duration-200">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium flex items-center gap-2">
                            <Filter class="h-4 w-4 text-primary" />
                            Filters
                            <Badge v-if="activeFilterCount > 0" class="h-5 px-1.5 text-[10px] rounded-full">{{ activeFilterCount }}</Badge>
                        </span>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="activeFilterCount > 0"
                                variant="ghost"
                                size="sm"
                                class="h-6 gap-1 text-xs text-muted-foreground hover:text-destructive"
                                @click="clearFilters"
                            >
                                <X class="h-3 w-3" />
                                Clear All
                            </Button>
                            <button @click="showFilters = false" class="p-1 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-x-3 gap-y-2.5">
                        <div v-for="col in selectColumns" :key="col.key" class="relative min-w-[150px]">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">{{ col.label }}</Label>
                            <Select v-model="filterValues[col.key]">
                                <SelectTrigger class="h-8 text-xs" :class="filterValues[col.key] ? 'pr-7' : ''">
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in col.options"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        <span
                                            class="px-2 py-0.5 rounded text-xs font-medium"
                                            :style="{
                                                backgroundColor: option.color || '#dbeafe',
                                                color: getTextColorForBg(option.color)
                                            }"
                                        >
                                            {{ option.label }}
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <button v-if="filterValues[col.key]" @click="filterValues[col.key] = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                        <!-- Updated From -->
                        <div class="relative">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Updated From</Label>
                            <input
                                :value="filterUpdatedFrom"
                                @input="filterUpdatedFrom = ($event.target as HTMLInputElement).value"
                                type="date"
                                data-slot="input"
                                class="border-input dark:bg-input/30 h-8 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                :class="filterUpdatedFrom ? 'pr-7' : ''"
                            />
                            <button v-if="filterUpdatedFrom" @click="filterUpdatedFrom = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                        <!-- Updated To -->
                        <div class="relative">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Updated To</Label>
                            <input
                                :value="filterUpdatedTo"
                                @input="filterUpdatedTo = ($event.target as HTMLInputElement).value"
                                type="date"
                                data-slot="input"
                                class="border-input dark:bg-input/30 h-8 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                :class="filterUpdatedTo ? 'pr-7' : ''"
                            />
                            <button v-if="filterUpdatedTo" @click="filterUpdatedTo = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                        <!-- Results count -->
                        <div class="flex items-end pb-1 ml-auto">
                            <span class="text-sm text-muted-foreground">
                                Found <span class="font-semibold text-foreground">{{ filteredDataRowCount }}</span> of {{ totalDataRowCount }} {{ totalDataRowCount === 1 ? 'row' : 'rows' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <Card>
                <CardContent class="p-0">
                    <div ref="scrollContainerRef" class="overflow-auto max-h-[calc(100vh-220px)]">
                        <table class="w-full border-collapse" style="table-layout: auto;">
                            <thead class="sticky top-0 z-10">
                                <tr class="border-b bg-muted">
                                    <th class="w-6 px-1 py-2"></th>
                                    <th
                                        v-for="(column, colIndex) in visibleColumns"
                                        :key="column.key"
                                        class="px-1 py-2 text-left text-sm font-medium text-muted-foreground relative select-none align-top"
                                        :style="{ width: column.width + 'px' }"
                                        @dragover="onColDragOver(colIndex, $event)"
                                        @dragleave="onColDragLeave"
                                        @drop="onColDrop(colIndex, $event)"
                                        :class="{
                                            'bg-primary/10': dragOverColIndex === colIndex,
                                            'opacity-50': draggedColIndex === colIndex
                                        }"
                                    >
                                        <div class="flex items-center gap-1">
                                            <div
                                                draggable="true"
                                                class="shrink-0 cursor-grab active:cursor-grabbing p-0.5 rounded hover:bg-muted"
                                                @dragstart="onColDragStart(colIndex, $event)"
                                                @dragend="onColDragEnd"
                                            >
                                                <GripHorizontal class="h-3 w-3 text-muted-foreground/50" />
                                            </div>
                                            <template v-if="column.type === 'checkbox'">
                                                <input
                                                    type="checkbox"
                                                    :checked="getHeaderCheckboxState(column.key) === true"
                                                    :indeterminate="getHeaderCheckboxState(column.key) === 'indeterminate'"
                                                    @click.stop="toggleAllCheckboxes(column.key)"
                                                    class="h-4 w-4 rounded border-gray-300 mr-1 cursor-pointer"
                                                />
                                            </template>
                                            <span class="truncate">{{ column.label }}</span>
                                        </div>
                                        <!-- Resize handle -->
                                        <div
                                            class="absolute -right-1 top-0 bottom-0 w-3 cursor-col-resize group"
                                            @mousedown.stop="startResize(colIndex, $event)"
                                        >
                                            <div class="absolute right-1 top-0 bottom-0 w-0.5 group-hover:bg-primary/50 group-active:bg-primary" />
                                        </div>
                                    </th>
                                    <th class="w-8 px-1 py-2"></th>
                                    <th class="w-8 px-1 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="h-2"><td :colspan="visibleColumns.length + 3"></td></tr>
                                <tr
                                    v-for="(row, index) in displayRows"
                                    :key="row.id"
                                    :data-row-id="row.id"
                                    class="border-b last:border-0 transition-all duration-500"
                                    :class="[
                                        row.row_type === 'section_header' ? '' : 'hover:bg-muted/50',
                                        getFontWeightClass(row.font_weight),
                                        {
                                            'border-t-2 border-t-primary': dragOverRowIndex === index && canDragRows,
                                            'opacity-50': draggedRowIndex === index && canDragRows,
                                            'ring-2 ring-primary/50 bg-primary/5': highlightedRowId === row.id,
                                        }
                                    ]"
                                    :style="getRowStyles(row)"
                                    @dragover="canDragRows && onRowDragOver(index, $event)"
                                    @dragleave="onRowDragLeave"
                                    @drop="canDragRows && onRowDrop(index, $event)"
                                >
                                    <td class="px-1 py-0.5 align-top">
                                        <button
                                            v-if="isSearchActive"
                                            @click="navigateToRow(row)"
                                            class="p-0.5 rounded hover:bg-primary/10 cursor-pointer"
                                            title="Go to row"
                                        >
                                            <LocateFixed class="h-4 w-4 text-primary" />
                                        </button>
                                        <div
                                            v-else
                                            :draggable="canDragRows"
                                            @dragstart="canDragRows && onRowDragStart(index, $event)"
                                            @dragend="onRowDragEnd"
                                            :class="canDragRows ? 'cursor-grab active:cursor-grabbing' : 'cursor-default opacity-30'"
                                        >
                                            <GripVertical class="h-4 w-4 text-muted-foreground/50" />
                                        </div>
                                    </td>
                                    <template v-if="row.row_type === 'section_header'">
                                        <td :colspan="visibleColumns.length" class="px-1 py-0.5 align-top">
                                            <Textarea
                                                :model-value="row.data[columns[0]?.key] as string || ''"
                                                @update:model-value="(val) => updateCell(row, columns[0]?.key || 'item', val)"
                                                class="w-full h-full min-h-[28px] border-transparent bg-transparent focus:border-input text-sm resize-none overflow-hidden py-1 px-2 whitespace-pre-wrap break-words"
                                                :class="getFontWeightClass(row.font_weight)"
                                                :style="{ color: row.font_color || 'inherit' }"
                                                placeholder="Section title..."
                                                rows="1"
                                                :readonly="!canEdit"
                                                @input="(e: Event) => autoResizeTextarea(e.target as HTMLTextAreaElement)"
                                                @blur="saveOnBlur"
                                            />
                                        </td>
                                    </template>
                                    <template v-else>
                                        <td
                                            v-for="column in visibleColumns"
                                            :key="column.key"
                                            class="px-1 py-0.5 align-top"
                                            :style="{ width: column.width + 'px' }"
                                        >
                                            <template v-if="column.type === 'checkbox'">
                                                <div class="flex items-start justify-center pt-1">
                                                    <input
                                                        type="checkbox"
                                                        :checked="!!row.data[column.key]"
                                                        @change="(e) => updateCell(row, column.key, (e.target as HTMLInputElement).checked)"
                                                        class="h-4 w-4 rounded border-gray-300 cursor-pointer"
                                                        :disabled="!canEdit"
                                                    />
                                                </div>
                                            </template>
                                            <template v-else-if="column.type === 'text'">
                                                <Textarea
                                                    :model-value="row.data[column.key] as string"
                                                    @update:model-value="(val) => updateCell(row, column.key, val)"
                                                    class="w-full min-h-[28px] text-sm resize-none overflow-hidden py-1 px-2 whitespace-pre-wrap break-words"
                                                    :class="getFontWeightClass(row.font_weight)"
                                                    :style="{ color: row.font_color || 'inherit' }"
                                                    rows="1"
                                                    :readonly="!canEdit"
                                                    @input="(e: Event) => autoResizeTextarea(e.target as HTMLTextAreaElement)"
                                                    @blur="saveOnBlur"
                                                />
                                            </template>
                                            <template v-else-if="column.type === 'select'">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <button
                                                            class="h-7 px-2 text-sm rounded border flex items-center gap-1 min-w-[80px] hover:bg-muted/50 cursor-pointer"
                                                            :class="{ 'pointer-events-none opacity-70': !canEdit }"
                                                            :style="getSelectedOption(column, row.data[column.key]) ? {
                                                                backgroundColor: getSelectedOption(column, row.data[column.key])?.color || '#dbeafe',
                                                                color: getTextColorForBg(getSelectedOption(column, row.data[column.key])?.color)
                                                            } : {}"
                                                        >
                                                            <span class="truncate flex-1 text-left">
                                                                {{ getSelectedOption(column, row.data[column.key])?.label || 'Select...' }}
                                                            </span>
                                                        </button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="start" class="min-w-[120px]">
                                                        <DropdownMenuItem
                                                            @click="updateCell(row, column.key, '')"
                                                            class="text-muted-foreground"
                                                        >
                                                            Clear
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator v-if="column.options?.length" />
                                                        <DropdownMenuItem
                                                            v-for="option in column.options"
                                                            :key="option.value"
                                                            @click="updateCell(row, column.key, option.value)"
                                                            class="gap-2"
                                                        >
                                                            <span
                                                                class="px-2 py-0.5 rounded text-xs font-medium"
                                                                :style="{
                                                                    backgroundColor: option.color || '#dbeafe',
                                                                    color: getTextColorForBg(option.color)
                                                                }"
                                                            >
                                                                {{ option.label }}
                                                            </span>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </template>
                                            <template v-else-if="column.type === 'date'">
                                                <Input
                                                    type="date"
                                                    :model-value="row.data[column.key] as string"
                                                    @update:model-value="(val) => updateCell(row, column.key, val)"
                                                    class="h-7 text-sm"
                                                    :readonly="!canEdit"
                                                    @blur="saveOnBlur"
                                                />
                                            </template>
                                            <template v-else>
                                                <Input
                                                    :model-value="row.data[column.key] as string"
                                                    @update:model-value="(val) => updateCell(row, column.key, val)"
                                                    class="h-7 text-sm"
                                                    :readonly="!canEdit"
                                                    @blur="saveOnBlur"
                                                />
                                            </template>
                                        </td>
                                    </template>
                                    <td class="px-1 py-0.5 align-top">
                                        <RestrictedAction>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-7 w-7 p-0"
                                                        title="Row Style"
                                                    >
                                                        <Edit class="h-3.5 w-3.5" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-56">
                                                <!-- Insert Rows -->
                                                <DropdownMenuLabel>Insert Rows</DropdownMenuLabel>
                                                <DropdownMenuItem @click="openAddRowsDialog(index, 'above', 'normal')">
                                                    <ArrowUp class="h-4 w-4 mr-2" />
                                                    Add rows above
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="openAddRowsDialog(index, 'below', 'normal')">
                                                    <ArrowDown class="h-4 w-4 mr-2" />
                                                    Add rows below
                                                </DropdownMenuItem>

                                                <DropdownMenuSeparator />

                                                <!-- Row Type -->
                                                <DropdownMenuLabel>Row Type</DropdownMenuLabel>
                                                <DropdownMenuItem
                                                    @click="row.row_type = 'normal'"
                                                    :class="row.row_type === 'normal' ? 'bg-accent' : ''"
                                                >
                                                    <Plus class="h-4 w-4 mr-2" />
                                                    Normal Row
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="toggleRowType(row)"
                                                    :class="row.row_type === 'section_header' ? 'bg-accent' : ''"
                                                >
                                                    <Heading class="h-4 w-4 mr-2" />
                                                    Section Header
                                                </DropdownMenuItem>

                                                <DropdownMenuSeparator />

                                                <!-- Background Color -->
                                                <DropdownMenuLabel>Background Color</DropdownMenuLabel>
                                                <div class="grid grid-cols-4 gap-1 p-2">
                                                    <button
                                                        v-for="color in predefinedColors"
                                                        :key="color.value"
                                                        @click="setBackgroundColor(row, color.value)"
                                                        class="w-8 h-8 rounded border-2 hover:scale-110 transition-transform cursor-pointer"
                                                        :class="row.background_color === color.value ? 'border-primary' : 'border-transparent'"
                                                        :style="{ backgroundColor: color.value }"
                                                        :title="color.name"
                                                    />
                                                </div>
                                                <div class="px-2 pb-2 flex items-center gap-2">
                                                    <label class="flex items-center gap-2 text-sm flex-1 cursor-pointer">
                                                        <input
                                                            type="color"
                                                            :value="row.background_color || '#ffffff'"
                                                            @input="(e) => setBackgroundColor(row, (e.target as HTMLInputElement).value)"
                                                            class="w-6 h-6 rounded cursor-pointer"
                                                        />
                                                        Custom
                                                    </label>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-6 text-xs"
                                                        @click="setBackgroundColor(row, null)"
                                                    >
                                                        Clear
                                                    </Button>
                                                </div>

                                                <DropdownMenuSeparator />

                                                <!-- Font Color -->
                                                <DropdownMenuLabel>Font Color</DropdownMenuLabel>
                                                <div class="grid grid-cols-4 gap-1 p-2">
                                                    <button
                                                        v-for="color in fontColors"
                                                        :key="color.value"
                                                        @click="setFontColor(row, color.value)"
                                                        class="w-8 h-8 rounded border-2 hover:scale-110 transition-transform flex items-center justify-center cursor-pointer"
                                                        :class="row.font_color === color.value ? 'border-primary' : 'border-gray-200'"
                                                        :title="color.name"
                                                    >
                                                        <span class="text-lg font-bold" :style="{ color: color.value }">A</span>
                                                    </button>
                                                </div>
                                                <div class="px-2 pb-2 flex items-center gap-2">
                                                    <label class="flex items-center gap-2 text-sm flex-1 cursor-pointer">
                                                        <input
                                                            type="color"
                                                            :value="row.font_color || '#000000'"
                                                            @input="(e) => setFontColor(row, (e.target as HTMLInputElement).value)"
                                                            class="w-6 h-6 rounded cursor-pointer"
                                                        />
                                                        Custom
                                                    </label>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-6 text-xs"
                                                        @click="setFontColor(row, null)"
                                                    >
                                                        Clear
                                                    </Button>
                                                </div>

                                                <DropdownMenuSeparator />

                                                <!-- Font Weight -->
                                                <DropdownMenuLabel>Font Weight</DropdownMenuLabel>
                                                <DropdownMenuItem
                                                    v-for="weight in fontWeights"
                                                    :key="weight.value"
                                                    @click="setFontWeight(row, weight.value as 'normal' | 'medium' | 'semibold' | 'bold')"
                                                    :class="row.font_weight === weight.value ? 'bg-accent' : ''"
                                                >
                                                    <Bold class="h-4 w-4 mr-2" />
                                                    <span :class="getFontWeightClass(weight.value)">{{ weight.label }}</span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                            </DropdownMenu>
                                        </RestrictedAction>
                                    </td>
                                    <td class="px-1 py-0.5 align-top">
                                        <RestrictedAction>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="confirmRemoveRow(index)"
                                                class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </RestrictedAction>
                                    </td>
                                </tr>
                                <tr v-if="rows.length === 0">
                                    <td :colspan="visibleColumns.length + 3" class="p-6 text-center text-muted-foreground text-sm">
                                        No items yet. Click "Add Row" to add your first item.
                                    </td>
                                </tr>
                                <tr v-else-if="(searchQuery.trim() || activeFilterCount > 0) && filteredDataRowCount === 0">
                                    <td :colspan="visibleColumns.length + 3" class="p-6 text-center text-muted-foreground text-sm">
                                        <span v-if="searchQuery.trim()" class="inline-block max-w-full truncate align-bottom">No items match "{{ searchQuery }}".</span>
                                        <template v-else>No items match the selected filters.</template>
                                        <Button v-if="activeFilterCount > 0" variant="outline" size="sm" class="mt-2 gap-2 ml-2" @click="clearFilters">
                                            <X class="h-3.5 w-3.5" />
                                            Clear Filters
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Load More -->
                        <div v-if="hasMoreRows" class="flex flex-col items-center gap-2 py-4 border-t">
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-muted-foreground">
                                    Showing {{ displayRows.length }} of {{ totalRowCount }} rows
                                </span>
                                <Button variant="outline" size="sm" @click="loadMoreRows">
                                    Load more
                                </Button>
                                <Button variant="ghost" size="sm" @click="showAllRows">
                                    Show all
                                </Button>
                            </div>
                            <span class="text-xs text-muted-foreground/70">
                                Show all rows to enable drag-and-drop reordering
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Delete Row Confirmation Dialog -->
            <Dialog v-model:open="showDeleteConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Row?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this row? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" @click="showDeleteConfirm = false" class="flex-1 sm:flex-none">
                            No
                        </Button>
                        <RestrictedAction>
                            <Button variant="destructive" @click="removeRow" class="flex-1 sm:flex-none">
                                Yes
                            </Button>
                        </RestrictedAction>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Selected Rows Confirmation Dialog -->
            <Dialog v-model:open="showDeleteSelectedConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete {{ selectedRows.length }} Row{{ selectedRows.length !== 1 ? 's' : '' }}?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete the selected rows? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" @click="showDeleteSelectedConfirm = false" class="flex-1 sm:flex-none cursor-pointer">
                            No
                        </Button>
                        <RestrictedAction>
                            <Button variant="destructive" @click="removeSelectedRows" class="flex-1 sm:flex-none cursor-pointer">
                                Yes
                            </Button>
                        </RestrictedAction>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Add Rows Dialog -->
            <Dialog v-model:open="showAddRowsDialog">
                <DialogContent class="max-w-xs">
                    <DialogHeader>
                        <DialogTitle>Add {{ addRowsType === 'section_header' ? 'Section Headers' : 'Rows' }}{{ addRowsPosition === 'above' ? ' Above' : addRowsPosition === 'below' ? ' Below' : '' }}</DialogTitle>
                        <DialogDescription>
                            Specify the number of rows to add.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="py-4">
                        <Label for="rows-count">Number of rows</Label>
                        <Input
                            id="rows-count"
                            v-model.number="addRowsCount"
                            type="number"
                            min="1"
                            max="100"
                            class="mt-2"
                        />
                    </div>
                    <DialogFooter class="flex gap-2 sm:justify-end">
                        <Button variant="outline" @click="showAddRowsDialog = false">
                            Cancel
                        </Button>
                        <RestrictedAction>
                            <Button @click="insertRows" class="gap-2">
                                <Plus class="h-4 w-4" />
                                Add {{ addRowsCount }} row{{ addRowsCount > 1 ? 's' : '' }}
                            </Button>
                        </RestrictedAction>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Copy to Checklist Dialog -->
            <Dialog v-model:open="showCopyToChecklistDialog">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Copy class="h-5 w-5 text-primary" />
                            Copy Rows to Checklist
                        </DialogTitle>
                        <DialogDescription>
                            Copy {{ selectedRows.length }} selected row{{ selectedRows.length !== 1 ? 's' : '' }} to another checklist.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="py-4 space-y-4">
                        <div class="space-y-2">
                            <Label for="copy-target-checklist">Target Checklist</Label>
                            <Select v-model="copyTargetChecklistId">
                                <SelectTrigger id="copy-target-checklist">
                                    <SelectValue placeholder="Select checklist..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="cl in otherChecklists"
                                        :key="cl.id"
                                        :value="cl.id"
                                    >
                                        {{ cl.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="targetSectionHeaders.length > 0" class="space-y-2">
                            <Label for="copy-target-section">Insert into Section</Label>
                            <div class="flex gap-2">
                                <Select v-model="copyTargetSectionId" class="flex-1">
                                    <SelectTrigger id="copy-target-section">
                                        <SelectValue placeholder="End of checklist (default)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sh in targetSectionHeaders"
                                            :key="sh.id"
                                            :value="sh.id"
                                            :textValue="sh.label"
                                        >
                                            {{ sh.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button v-if="copyTargetSectionId !== null" variant="ghost" size="icon" class="shrink-0" @click="copyTargetSectionId = null">
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="flex gap-2 sm:justify-end">
                        <Button variant="outline" @click="showCopyToChecklistDialog = false">
                            Cancel
                        </Button>
                        <RestrictedAction>
                            <Button @click="copyToChecklist" :disabled="!copyTargetChecklistId || isCopying" class="gap-2">
                                <Copy class="h-4 w-4" />
                                {{ isCopying ? 'Copying...' : 'Copy Rows' }}
                            </Button>
                        </RestrictedAction>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Paste Rows Dialog -->
            <Dialog v-model:open="showPasteDialog">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <ClipboardList class="h-5 w-5 text-primary" />
                            Paste Rows
                        </DialogTitle>
                        <DialogDescription>
                            Paste {{ clipboardData?.rows.length }} row{{ (clipboardData?.rows.length ?? 0) !== 1 ? 's' : '' }} from "{{ clipboardData?.source_checklist_name }}".
                        </DialogDescription>
                    </DialogHeader>
                    <div class="py-4 space-y-4">
                        <div v-if="currentSectionHeaders.length > 0" class="space-y-2">
                            <Label for="paste-section">Insert into Section</Label>
                            <div class="flex gap-2">
                                <Select v-model="pasteSectionId" class="flex-1">
                                    <SelectTrigger id="paste-section">
                                        <SelectValue placeholder="End of checklist (default)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sh in currentSectionHeaders"
                                            :key="sh.id"
                                            :value="sh.id"
                                            :textValue="sh.label"
                                        >
                                            {{ sh.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button v-if="pasteSectionId !== null" variant="ghost" size="icon" class="shrink-0 cursor-pointer" @click="pasteSectionId = null">
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="flex gap-2 sm:justify-between">
                        <Button variant="ghost" @click="clearClipboard(); showPasteDialog = false" class="gap-2 text-muted-foreground hover:text-destructive cursor-pointer">
                            <Trash2 class="h-4 w-4" />
                            Clear
                        </Button>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="showPasteDialog = false" class="cursor-pointer">
                                Cancel
                            </Button>
                            <RestrictedAction>
                                <Button @click="pasteRows" :disabled="isPasting" class="gap-2 cursor-pointer">
                                    <ClipboardList class="h-4 w-4" />
                                    {{ isPasting ? 'Pasting...' : 'Paste Rows' }}
                                </Button>
                            </RestrictedAction>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Create Test Case Dialog -->
            <Dialog v-model:open="showTestCaseDialog">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Layers class="h-5 w-5 text-primary" />
                            Create Test Case
                        </DialogTitle>
                        <DialogDescription>
                            Create a test case from {{ selectedRows.length }} selected row{{ selectedRows.length !== 1 ? 's' : '' }}. Select the target test suite.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="py-4 space-y-4">
                        <div class="space-y-2">
                            <Label for="test-case-target-suite">Test Suite</Label>
                            <Select v-model="testCaseTargetSuiteId">
                                <SelectTrigger id="test-case-target-suite">
                                    <SelectValue placeholder="Select test suite..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="suite in testSuites"
                                        :key="suite.id"
                                        :value="suite.id"
                                    >
                                        {{ suite.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="selectedParentSuiteChildren.length > 0" class="space-y-2">
                            <Label for="test-case-target-child">Subcategory</Label>
                            <div class="flex gap-2">
                                <Select v-model="testCaseTargetChildId" class="flex-1">
                                    <SelectTrigger id="test-case-target-child">
                                        <SelectValue placeholder="None (save in parent)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="child in selectedParentSuiteChildren"
                                            :key="child.id"
                                            :value="child.id"
                                        >
                                            {{ child.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button v-if="testCaseTargetChildId !== null" variant="ghost" size="icon" class="shrink-0 cursor-pointer" @click="testCaseTargetChildId = null">
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="flex gap-2 sm:justify-end">
                        <Button variant="outline" @click="showTestCaseDialog = false">
                            Cancel
                        </Button>
                        <Button @click="createTestCaseFromSelected" :disabled="!testCaseTargetSuiteId" class="gap-2">
                            <Layers class="h-4 w-4" />
                            Create Test Case
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Create Test Run Dialog -->
            <Dialog v-model:open="showTestRunDialog">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Play class="h-5 w-5 text-primary" />
                            Create Test Run
                        </DialogTitle>
                        <DialogDescription>
                            Create a test run from {{ selectedRows.length }} selected row{{ selectedRows.length !== 1 ? 's' : '' }}. Each row becomes a check item.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="py-4 space-y-4">
                        <div class="space-y-2">
                            <Label for="test-run-column">Source Column</Label>
                            <Select v-model="testRunColumnKey">
                                <SelectTrigger id="test-run-column">
                                    <SelectValue placeholder="Select column..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="col in textColumnsForTestRun"
                                        :key="col.key"
                                        :value="col.key"
                                    >
                                        {{ col.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label for="test-run-name">Name</Label>
                            <Input id="test-run-name" v-model="testRunName" placeholder="Test run name..." />
                        </div>
                        <div class="space-y-2">
                            <Label for="test-run-description">Description</Label>
                            <Input id="test-run-description" v-model="testRunDescription" placeholder="Optional description..." />
                        </div>
                        <div class="space-y-2">
                            <Label>Priority</Label>
                            <Select v-model="testRunPriority">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select priority..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Low</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="critical">Critical</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label>Environment</Label>
                            <div class="grid grid-cols-3 gap-2">
                                <Select v-model="testRunEnvPreset">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Develop">Develop</SelectItem>
                                        <SelectItem value="Staging">Staging</SelectItem>
                                        <SelectItem value="Production">Production</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input v-model="testRunEnvNotes" placeholder="Devices, browser..." class="col-span-2" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="test-run-milestone">Milestone</Label>
                            <Input id="test-run-milestone" v-model="testRunMilestone" placeholder="e.g. v1.0, Sprint 5..." />
                        </div>
                    </div>
                    <DialogFooter class="flex gap-2 sm:justify-end">
                        <Button variant="outline" @click="showTestRunDialog = false">
                            Cancel
                        </Button>
                        <Button @click="createTestRunFromSelected" :disabled="!testRunName.trim() || !testRunColumnKey || isCreatingTestRun" class="gap-2">
                            <Play class="h-4 w-4" />
                            {{ isCreatingTestRun ? 'Creating...' : 'Create Test Run' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Import CSV Dialog -->
            <Dialog v-model:open="showImportDialog">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Download class="h-5 w-5 text-primary" />
                            Import from CSV
                        </DialogTitle>
                        <DialogDescription>
                            Select a CSV file to import. New rows will be added below existing content.
                            The CSV headers should match the checklist column names.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <Label for="import-file">CSV File</Label>
                            <Input
                                id="import-file"
                                ref="fileInputRef"
                                type="file"
                                accept=".csv,.txt"
                                @change="handleFileSelect"
                                :class="{ 'border-destructive': importError }"
                            />
                            <p class="text-xs text-muted-foreground">
                                Maximum file size: 5MB. Supported formats: CSV, TXT
                            </p>
                        </div>

                        <div v-if="importError" class="rounded-md bg-destructive/15 p-3 text-sm text-destructive">
                            {{ importError }}
                        </div>

                        <div v-if="importFile" class="rounded-md bg-muted p-3">
                            <div class="flex items-center gap-2 text-sm">
                                <FileSpreadsheet class="h-4 w-4 text-primary" />
                                <span class="font-medium">{{ importFile.name }}</span>
                                <span class="text-muted-foreground">({{ (importFile.size / 1024).toFixed(1) }} KB)</span>
                            </div>
                        </div>

                        <div class="rounded-md border p-3 space-y-2">
                            <p class="text-sm font-medium">Expected columns:</p>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="col in columns"
                                    :key="col.key"
                                    class="px-2 py-0.5 bg-muted rounded text-xs"
                                >
                                    {{ col.label }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="flex gap-2 sm:justify-end">
                        <Button variant="outline" @click="closeImportDialog">
                            Cancel
                        </Button>
                        <RestrictedAction>
                            <Button
                                @click="submitImport"
                                :disabled="!importFile || isUploadingFile"
                                class="gap-2"
                            >
                                <Download class="h-4 w-4" />
                                {{ isUploadingFile ? 'Importing...' : 'Import' }}
                            </Button>
                        </RestrictedAction>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
