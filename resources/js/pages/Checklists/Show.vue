<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type Checklist, type ChecklistRow, type ColumnConfig, type SelectOption } from '@/types';
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
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    ClipboardList, Edit, Plus, Trash2, Save, GripVertical,
    Bold, Heading, GripHorizontal
} from 'lucide-vue-next';
import { ref, watch, onMounted, nextTick } from 'vue';

const props = defineProps<{
    project: Project;
    checklist: Checklist;
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

const hasChanges = ref(false);
const showDeleteConfirm = ref(false);
const rowToDeleteIndex = ref<number | null>(null);

watch([rows, columns], () => {
    hasChanges.value = true;
}, { deep: true });

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

const onColDrop = (index: number, event: DragEvent) => {
    event.preventDefault();
    if (draggedColIndex.value !== null && draggedColIndex.value !== index) {
        const draggedCol = columns.value[draggedColIndex.value];
        columns.value.splice(draggedColIndex.value, 1);
        columns.value.splice(index, 0, draggedCol);
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

const startResize = (index: number, event: MouseEvent) => {
    resizingCol.value = index;
    resizeStartX.value = event.clientX;
    resizeStartWidth.value = columns.value[index].width || 150;
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
    }
};

const saveRows = () => {
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
                hasChanges.value = false;
            },
        }
    );
};

const updateCell = (row: ExtendedChecklistRow, key: string, value: unknown) => {
    row.data[key] = value;
};

const setBackgroundColor = (row: ExtendedChecklistRow, color: string | null) => {
    row.background_color = color;
};

const setFontColor = (row: ExtendedChecklistRow, color: string | null) => {
    row.font_color = color;
};

const setFontWeight = (row: ExtendedChecklistRow, weight: 'normal' | 'medium' | 'semibold' | 'bold') => {
    row.font_weight = weight;
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
};

const getRowStyles = (row: ExtendedChecklistRow) => {
    const styles: Record<string, string> = {};
    if (row.background_color) {
        styles.backgroundColor = row.background_color;
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

const autoResizeTextarea = (el: HTMLTextAreaElement) => {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
};

const resizeAllTextareas = () => {
    nextTick(() => {
        const textareas = document.querySelectorAll('table textarea');
        textareas.forEach((el) => {
            autoResizeTextarea(el as HTMLTextAreaElement);
        });
    });
};

onMounted(() => {
    resizeAllTextareas();
});

watch(rows, () => {
    resizeAllTextareas();
}, { deep: true });
</script>

<template>
    <Head :title="checklist.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <ClipboardList class="h-6 w-6 text-primary" />
                        {{ checklist.name }}
                    </h1>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="hasChanges"
                        @click="saveRows"
                        class="gap-2"
                    >
                        <Save class="h-4 w-4" />
                        Save Changes
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" class="gap-2">
                                <Plus class="h-4 w-4" />
                                Add Row
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="addRow('normal')">
                                <Plus class="h-4 w-4 mr-2" />
                                Normal Row
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="addRow('section_header')">
                                <Heading class="h-4 w-4 mr-2" />
                                Section Header
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Link :href="`/projects/${project.id}/checklists/${checklist.id}/edit`">
                        <Button variant="outline" class="gap-2">
                            <Edit class="h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                </div>
            </div>

            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse" style="table-layout: auto;">
                            <thead>
                                <tr class="border-b bg-muted/30">
                                    <th class="w-6 px-1 py-1"></th>
                                    <th
                                        v-for="(column, colIndex) in columns"
                                        :key="column.key"
                                        class="px-1 py-1 text-left text-xs font-medium text-muted-foreground relative select-none align-top"
                                        :style="{ width: column.width + 'px' }"
                                        draggable="true"
                                        @dragstart="onColDragStart(colIndex, $event)"
                                        @dragover="onColDragOver(colIndex, $event)"
                                        @dragleave="onColDragLeave"
                                        @drop="onColDrop(colIndex, $event)"
                                        @dragend="onColDragEnd"
                                        :class="{
                                            'bg-primary/10': dragOverColIndex === colIndex,
                                            'opacity-50': draggedColIndex === colIndex
                                        }"
                                    >
                                        <div class="flex items-center gap-1 cursor-grab active:cursor-grabbing">
                                            <GripHorizontal class="h-3 w-3 text-muted-foreground/50" />
                                            <span class="truncate">{{ column.label }}</span>
                                        </div>
                                        <!-- Resize handle -->
                                        <div
                                            class="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize hover:bg-primary/50 active:bg-primary"
                                            @mousedown.stop="startResize(colIndex, $event)"
                                        />
                                    </th>
                                    <th class="w-8 px-1 py-1"></th>
                                    <th class="w-8 px-1 py-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, index) in rows"
                                    :key="row.id"
                                    class="border-b last:border-0 transition-colors"
                                    :class="[
                                        row.row_type === 'section_header' ? '' : 'hover:bg-muted/50',
                                        getFontWeightClass(row.font_weight),
                                        {
                                            'border-t-2 border-t-primary': dragOverRowIndex === index,
                                            'opacity-50': draggedRowIndex === index
                                        }
                                    ]"
                                    :style="getRowStyles(row)"
                                    draggable="true"
                                    @dragstart="onRowDragStart(index, $event)"
                                    @dragover="onRowDragOver(index, $event)"
                                    @dragleave="onRowDragLeave"
                                    @drop="onRowDrop(index, $event)"
                                    @dragend="onRowDragEnd"
                                >
                                    <td class="px-1 py-1 align-top">
                                        <GripVertical class="h-4 w-4 text-muted-foreground/50 cursor-grab active:cursor-grabbing" />
                                    </td>
                                    <template v-if="row.row_type === 'section_header'">
                                        <td :colspan="columns.length" class="px-1 py-1 align-top">
                                            <Textarea
                                                :model-value="row.data[columns[0]?.key] as string || ''"
                                                @update:model-value="(val) => updateCell(row, columns[0]?.key || 'item', val)"
                                                class="w-full min-h-[28px] border-transparent bg-transparent focus:border-input text-sm resize-none overflow-hidden py-1 px-2 whitespace-pre-wrap break-words"
                                                :class="getFontWeightClass(row.font_weight)"
                                                :style="{ color: row.font_color || 'inherit' }"
                                                placeholder="Section title..."
                                                rows="1"
                                                @input="(e: Event) => autoResizeTextarea(e.target as HTMLTextAreaElement)"
                                            />
                                        </td>
                                    </template>
                                    <template v-else>
                                        <td
                                            v-for="column in columns"
                                            :key="column.key"
                                            class="px-1 py-1 align-top"
                                            :style="{ width: column.width + 'px' }"
                                        >
                                            <template v-if="column.type === 'checkbox'">
                                                <div class="flex justify-center pt-1">
                                                    <Checkbox
                                                        :checked="!!row.data[column.key]"
                                                        @update:checked="(val) => updateCell(row, column.key, val)"
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
                                                    @input="(e: Event) => autoResizeTextarea(e.target as HTMLTextAreaElement)"
                                                />
                                            </template>
                                            <template v-else-if="column.type === 'select'">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <button
                                                            class="h-7 px-2 text-sm rounded border flex items-center gap-1 min-w-[80px] hover:bg-muted/50"
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
                                                />
                                            </template>
                                            <template v-else>
                                                <Input
                                                    :model-value="row.data[column.key] as string"
                                                    @update:model-value="(val) => updateCell(row, column.key, val)"
                                                    class="h-7 text-sm"
                                                />
                                            </template>
                                        </td>
                                    </template>
                                    <td class="px-1 py-1 align-top">
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
                                                        class="w-8 h-8 rounded border-2 hover:scale-110 transition-transform"
                                                        :class="row.background_color === color.value ? 'border-primary' : 'border-transparent'"
                                                        :style="{ backgroundColor: color.value }"
                                                        :title="color.name"
                                                    />
                                                </div>
                                                <div class="px-2 pb-2 flex items-center gap-2">
                                                    <label class="flex items-center gap-2 text-sm flex-1">
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
                                                        class="w-8 h-8 rounded border-2 hover:scale-110 transition-transform flex items-center justify-center"
                                                        :class="row.font_color === color.value ? 'border-primary' : 'border-gray-200'"
                                                        :title="color.name"
                                                    >
                                                        <span class="text-lg font-bold" :style="{ color: color.value }">A</span>
                                                    </button>
                                                </div>
                                                <div class="px-2 pb-2 flex items-center gap-2">
                                                    <label class="flex items-center gap-2 text-sm flex-1">
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
                                    </td>
                                    <td class="px-1 py-1 align-top">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="confirmRemoveRow(index)"
                                            class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="rows.length === 0">
                                    <td :colspan="columns.length + 3" class="p-6 text-center text-muted-foreground text-sm">
                                        No items yet. Click "Add Row" to add your first item.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
                        <Button variant="destructive" @click="removeRow" class="flex-1 sm:flex-none">
                            Yes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
