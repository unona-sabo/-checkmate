<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type Checklist, type ColumnConfig } from '@/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus, ClipboardList, FileText, StickyNote, Import, Pencil, Trash2, X, Search, GripVertical, ChevronDown, ChevronRight, Tag, FolderOpen } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { ref, computed, watch, onMounted, nextTick } from 'vue';

interface NoteDraft {
    id: string;
    content: string;
    selectedChecklistId: number | null;
    selectedColumnKey: string;
    createdAt: string;
    updatedAt: string;
}

interface CategoryGroup {
    name: string | null;
    label: string;
    checklists: Checklist[];
}

const props = defineProps<{
    project: Project;
    checklists: Checklist[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Checklists', href: `/projects/${props.project.id}/checklists` },
];

const DRAFT_STORAGE_KEY = `checklist-note-draft-${props.project.id}`;
const COLLAPSED_KEY = `checklist-categories-collapsed-${props.project.id}`;

const searchQuery = ref('');

// Local ordering state — reflects server data, mutated by drag
const localChecklists = ref<Checklist[]>([...props.checklists]);

watch(() => props.checklists, (val) => {
    localChecklists.value = [...val];
});

const filteredChecklists = computed(() => {
    if (!searchQuery.value.trim()) {
        return localChecklists.value;
    }
    const query = searchQuery.value.toLowerCase();
    return localChecklists.value.filter(checklist =>
        checklist.name.toLowerCase().includes(query)
    );
});

// Category grouping — order derived from checklist order (not alphabetical)
const categoryGroups = computed<CategoryGroup[]>(() => {
    const groups = new Map<string | null, Checklist[]>();
    const seen: (string | null)[] = [];

    for (const cl of filteredChecklists.value) {
        const cat = cl.category || null;
        if (!groups.has(cat)) {
            groups.set(cat, []);
            seen.push(cat);
        }
        groups.get(cat)!.push(cl);
    }

    // Ensure uncategorized is always first
    const uncatIdx = seen.indexOf(null);
    if (uncatIdx > 0) {
        seen.splice(uncatIdx, 1);
        seen.unshift(null);
    }

    return seen.map(name => ({
        name,
        label: name ?? 'Uncategorized',
        checklists: groups.get(name)!,
    }));
});

const allCategories = computed(() => {
    const cats = new Set<string>();
    for (const cl of localChecklists.value) {
        if (cl.category) cats.add(cl.category);
    }
    return [...cats].sort();
});

const hasCategories = computed(() => allCategories.value.length > 0 || localChecklists.value.some(c => c.category));

// Collapsed state
const collapsedCategories = ref<Set<string>>(new Set());

const loadCollapsed = () => {
    try {
        const saved = localStorage.getItem(COLLAPSED_KEY);
        if (saved) {
            collapsedCategories.value = new Set(JSON.parse(saved));
        }
    } catch { /* ignore */ }
};

const saveCollapsed = () => {
    try {
        localStorage.setItem(COLLAPSED_KEY, JSON.stringify([...collapsedCategories.value]));
    } catch { /* ignore */ }
};

const toggleCategory = (name: string | null) => {
    const key = name ?? '__uncategorized__';
    if (collapsedCategories.value.has(key)) {
        collapsedCategories.value.delete(key);
    } else {
        collapsedCategories.value.add(key);
    }
    saveCollapsed();
};

const isCategoryCollapsed = (name: string | null) => {
    return collapsedCategories.value.has(name ?? '__uncategorized__');
};

// Drag and drop — cards
const draggedChecklist = ref<Checklist | null>(null);
const dragOverCategory = ref<string | null | undefined>(undefined);
const isDragging = ref(false);
const canDrag = computed(() => !searchQuery.value.trim());

const onDragStart = (e: DragEvent, checklist: Checklist) => {
    if (!canDrag.value) return;
    draggedChecklist.value = checklist;
    isDragging.value = true;
    e.dataTransfer!.effectAllowed = 'move';
    e.dataTransfer!.setData('text/plain', String(checklist.id));
};

const onDragEnd = () => {
    draggedChecklist.value = null;
    draggedCategoryName.value = undefined;
    dragOverCategory.value = undefined;
    isDragging.value = false;
};

const onDragOver = (e: DragEvent, categoryName: string | null) => {
    e.preventDefault();
    e.dataTransfer!.dropEffect = 'move';
    dragOverCategory.value = categoryName;
};

const onDragLeave = (e: DragEvent, categoryName: string | null) => {
    if (dragOverCategory.value === categoryName) {
        dragOverCategory.value = undefined;
    }
};

const onDropOnCard = (e: DragEvent, targetChecklist: Checklist, categoryName: string | null) => {
    e.preventDefault();
    // Ignore if this is a category drag
    if (draggedCategoryName.value !== undefined) return;
    if (!draggedChecklist.value || draggedChecklist.value.id === targetChecklist.id) {
        onDragEnd();
        return;
    }

    const dragged = draggedChecklist.value;
    const list = [...localChecklists.value];

    // Remove dragged from list
    const dragIdx = list.findIndex(c => c.id === dragged.id);
    if (dragIdx === -1) { onDragEnd(); return; }
    list.splice(dragIdx, 1);

    // Update category
    dragged.category = categoryName;

    // Insert before target
    const targetIdx = list.findIndex(c => c.id === targetChecklist.id);
    list.splice(targetIdx, 0, dragged);

    // Reassign order values
    list.forEach((c, i) => { c.order = i; });
    localChecklists.value = list;

    saveReorder();
    onDragEnd();
};

const onDropOnCategory = (e: DragEvent, categoryName: string | null) => {
    e.preventDefault();

    // Category-on-category drop
    if (draggedCategoryName.value !== undefined) {
        onDropCategoryOnCategory(categoryName);
        return;
    }

    // Card-on-category drop
    if (!draggedChecklist.value) { onDragEnd(); return; }

    const dragged = draggedChecklist.value;
    const list = [...localChecklists.value];

    // Remove from old position
    const dragIdx = list.findIndex(c => c.id === dragged.id);
    if (dragIdx === -1) { onDragEnd(); return; }
    list.splice(dragIdx, 1);

    // Update category
    dragged.category = categoryName;

    // Find last checklist in target category and insert after it
    let insertIdx = list.length;
    for (let i = list.length - 1; i >= 0; i--) {
        if ((list[i].category || null) === categoryName) {
            insertIdx = i + 1;
            break;
        }
    }
    // If no checklists in this category yet, find correct position based on group order
    if (!list.some(c => (c.category || null) === categoryName)) {
        insertIdx = list.length;
    }

    list.splice(insertIdx, 0, dragged);
    list.forEach((c, i) => { c.order = i; });
    localChecklists.value = list;

    saveReorder();
    onDragEnd();
};

// Drag and drop — categories (move entire group)
const draggedCategoryName = ref<string | null | undefined>(undefined);
const isDraggingCategory = computed(() => draggedCategoryName.value !== undefined);

const onCategoryDragStart = (e: DragEvent, categoryName: string | null) => {
    if (!canDrag.value) return;
    draggedCategoryName.value = categoryName;
    isDragging.value = true;
    e.dataTransfer!.effectAllowed = 'move';
    e.dataTransfer!.setData('text/plain', `category:${categoryName ?? '__null__'}`);
};

const onDropCategoryOnCategory = (targetCategoryName: string | null) => {
    const draggedCat = draggedCategoryName.value;
    if (draggedCat === undefined || draggedCat === targetCategoryName) {
        onDragEnd();
        return;
    }

    const list = [...localChecklists.value];

    // Extract checklists belonging to the dragged category
    const draggedItems = list.filter(c => (c.category || null) === draggedCat);
    const remaining = list.filter(c => (c.category || null) !== draggedCat);

    // Find the first checklist of the target category in the remaining list
    const targetIdx = remaining.findIndex(c => (c.category || null) === targetCategoryName);

    // Insert all dragged items before the target category
    if (targetIdx === -1) {
        remaining.push(...draggedItems);
    } else {
        remaining.splice(targetIdx, 0, ...draggedItems);
    }

    remaining.forEach((c, i) => { c.order = i; });
    localChecklists.value = remaining;

    saveReorder();
    onDragEnd();
};

const saveReorder = () => {
    const items = localChecklists.value.map((c, i) => ({
        id: c.id,
        order: i,
        category: c.category || null,
    }));

    router.put(
        `/projects/${props.project.id}/checklists/reorder`,
        { items },
        { preserveScroll: true, preserveState: true },
    );
};

// Category editing
const showCategoryDialog = ref(false);
const editingChecklistId = ref<number | null>(null);
const newCategoryName = ref('');

// Category renaming (inline)
const renamingCategory = ref<string | null>(null);
const renameCategoryValue = ref('');
const renameCategoryInput = ref<HTMLInputElement | null>(null);

const openCategoryEditor = (checklist: Checklist) => {
    editingChecklistId.value = checklist.id;
    newCategoryName.value = '';
};

const setCategoryForChecklist = (checklistId: number, category: string | null) => {
    const list = [...localChecklists.value];
    const cl = list.find(c => c.id === checklistId);
    if (!cl) return;
    cl.category = category;
    list.forEach((c, i) => { c.order = i; });
    localChecklists.value = list;
    editingChecklistId.value = null;
    saveReorder();
};

const setNewCategory = (checklistId: number) => {
    if (!newCategoryName.value.trim()) return;
    setCategoryForChecklist(checklistId, newCategoryName.value.trim());
    showCategoryDialog.value = false;
    newCategoryName.value = '';
};

const startRenameCategory = (categoryName: string) => {
    renamingCategory.value = categoryName;
    renameCategoryValue.value = categoryName;
    nextTick(() => {
        renameCategoryInput.value?.focus();
        renameCategoryInput.value?.select();
    });
};

const commitRenameCategory = () => {
    const oldName = renamingCategory.value;
    const newName = renameCategoryValue.value.trim();
    renamingCategory.value = null;

    if (!oldName || !newName || oldName === newName) return;

    const list = [...localChecklists.value];
    for (const cl of list) {
        if (cl.category === oldName) {
            cl.category = newName;
        }
    }
    localChecklists.value = list;
    saveReorder();
};

// Search + highlight
const escapeRegExp = (str: string): string => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const escapeHtml = (str: string): string => str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const highlight = (text: string): string => {
    const safe = escapeHtml(text);
    if (!searchQuery.value.trim()) return safe;
    const query = escapeRegExp(searchQuery.value.trim());
    return safe.replace(new RegExp(`(${query})`, 'gi'), '<mark class="search-highlight">$1</mark>');
};

// Note / Draft functionality (multiple drafts)
const showNoteDialog = ref(false);
const showDeleteConfirm = ref(false);
const deletingDraftId = ref<string | null>(null);
const editingDraftId = ref<string | null>(null);
const noteContent = ref('');
const selectedChecklistId = ref<number | null>(null);
const selectedColumnKey = ref<string>('');
const isImporting = ref(false);
const drafts = ref<NoteDraft[]>([]);

const generateDraftId = () => `draft-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;

const getDraftPreview = (content: string) => {
    let preview = content.split('\n').filter(l => l.trim()).slice(0, 3).join(', ');
    if (preview.length > 50) {
        preview = preview.substring(0, 50) + '...';
    }
    return preview;
};

const loadDrafts = () => {
    try {
        const saved = localStorage.getItem(DRAFT_STORAGE_KEY);
        if (saved) {
            const parsed = JSON.parse(saved);
            // Migrate single draft to array format
            if (parsed && !Array.isArray(parsed) && parsed.content) {
                const migrated: NoteDraft = {
                    ...parsed,
                    id: parsed.id || generateDraftId(),
                };
                drafts.value = [migrated];
                localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(drafts.value));
            } else if (Array.isArray(parsed)) {
                drafts.value = parsed.filter((d: NoteDraft) => d.content && d.content.trim());
            }
        }
    } catch (e) {
        console.error('Failed to load drafts:', e);
    }
};

const saveDrafts = () => {
    try {
        localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(drafts.value));
    } catch (e) {
        console.error('Failed to save drafts:', e);
    }
};

const saveDraft = () => {
    if (!noteContent.value.trim()) {
        if (editingDraftId.value) {
            deleteDraft(editingDraftId.value);
        }
        return;
    }

    const now = new Date().toISOString();

    if (editingDraftId.value) {
        const idx = drafts.value.findIndex(d => d.id === editingDraftId.value);
        if (idx !== -1) {
            drafts.value[idx] = {
                ...drafts.value[idx],
                content: noteContent.value,
                selectedChecklistId: selectedChecklistId.value,
                selectedColumnKey: selectedColumnKey.value,
                updatedAt: now,
            };
        }
    } else {
        drafts.value.push({
            id: generateDraftId(),
            content: noteContent.value,
            selectedChecklistId: selectedChecklistId.value,
            selectedColumnKey: selectedColumnKey.value,
            createdAt: now,
            updatedAt: now,
        });
    }

    saveDrafts();
};

const confirmDeleteDraft = (draftId: string) => {
    deletingDraftId.value = draftId;
    showDeleteConfirm.value = true;
};

const deleteDraft = (draftId?: string) => {
    const id = draftId || deletingDraftId.value;
    if (!id) return;
    drafts.value = drafts.value.filter(d => d.id !== id);
    saveDrafts();
    deletingDraftId.value = null;
    showDeleteConfirm.value = false;
};

const openDraft = (draft: NoteDraft) => {
    editingDraftId.value = draft.id;
    noteContent.value = draft.content;
    selectedChecklistId.value = draft.selectedChecklistId;
    selectedColumnKey.value = draft.selectedColumnKey;
    showNoteDialog.value = true;
};

const formatDraftDate = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    loadDrafts();
    loadCollapsed();
});

const selectedChecklistId_section = ref<number | null>(null);

const selectedChecklist = computed(() => {
    if (!selectedChecklistId.value) return null;
    return props.checklists.find(c => c.id === selectedChecklistId.value) || null;
});

const availableColumns = computed(() => {
    if (!selectedChecklist.value?.columns_config) {
        return [{ key: 'item', label: 'Item', type: 'text' as const }];
    }
    return selectedChecklist.value.columns_config.filter(col => col.type === 'text');
});

const availableSections = computed(() => {
    if (!selectedChecklist.value?.section_headers) return [];
    const firstTextKey = availableColumns.value[0]?.key;
    return selectedChecklist.value.section_headers.map(sh => {
        const label = firstTextKey ? String(sh.data[firstTextKey] || '') : '';
        // Fallback: try any non-empty value from data
        const displayLabel = label || Object.values(sh.data).find(v => v && String(v).trim()) as string || `Section (order ${sh.order})`;
        return { id: sh.id, label: displayLabel };
    });
});

watch(selectedChecklistId, () => {
    if (availableColumns.value.length > 0) {
        selectedColumnKey.value = availableColumns.value[0].key;
    }
    selectedChecklistId_section.value = null;
});

const parsedNotes = computed(() => {
    if (!noteContent.value.trim()) return [];

    return noteContent.value
        .split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0)
        .map(line => {
            return line.replace(/^[\d]+[.\)\-:\s]+/, '').trim();
        })
        .filter(line => line.length > 0);
});

const importNotes = () => {
    if (!selectedChecklistId.value || parsedNotes.value.length === 0 || !selectedColumnKey.value) return;

    isImporting.value = true;

    router.post(
        `/projects/${props.project.id}/checklists/${selectedChecklistId.value}/import-notes`,
        {
            notes: parsedNotes.value,
            column_key: selectedColumnKey.value,
            section_row_id: selectedChecklistId_section.value || null,
        },
        {
            onSuccess: () => {
                showNoteDialog.value = false;
                if (editingDraftId.value) {
                    deleteDraft(editingDraftId.value);
                }
                noteContent.value = '';
                selectedChecklistId.value = null;
                selectedColumnKey.value = '';
                selectedChecklistId_section.value = null;
                isImporting.value = false;
                editingDraftId.value = null;
            },
            onError: () => {
                isImporting.value = false;
            },
        }
    );
};

const clearNotes = () => {
    noteContent.value = '';
    if (editingDraftId.value) {
        deleteDraft(editingDraftId.value);
        editingDraftId.value = null;
    }
};

const onDialogClose = (open: boolean) => {
    if (!open && noteContent.value.trim()) {
        saveDraft();
    }
    if (!open) {
        setTimeout(() => {
            noteContent.value = '';
            selectedChecklistId.value = null;
            selectedColumnKey.value = '';
            editingDraftId.value = null;
        }, 200);
    }
};
</script>

<template>
    <Head title="Checklists" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-start gap-2 text-2xl font-bold tracking-tight">
                        <ClipboardList class="h-6 w-6 shrink-0 mt-1 text-primary" />
                        Checklists
                    </h1>
                    <p class="text-muted-foreground">Create and manage custom checklists</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search checklists..."
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
                    <Dialog v-model:open="showNoteDialog" @update:open="onDialogClose">
                        <DialogTrigger as-child>
                            <Button variant="outline" class="gap-2" @click="editingDraftId = null; noteContent = ''; selectedChecklistId = null; selectedColumnKey = ''">
                                <StickyNote class="h-4 w-4" />
                                Create a Note
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="max-w-2xl max-h-[75vh] flex flex-col" style="overflow: hidden !important; max-width: min(42rem, calc(100vw - 2rem)) !important;">
                            <DialogHeader>
                                <DialogTitle class="flex items-center gap-2">
                                    <StickyNote class="h-5 w-5 text-primary" />
                                    Create a Note
                                </DialogTitle>
                                <DialogDescription>
                                    Write your notes below. Each line will become a separate row in the checklist.
                                </DialogDescription>
                            </DialogHeader>

                            <div class="space-y-4 py-4 px-0.5 overflow-y-auto min-h-0 flex-1">
                                <div class="space-y-2">
                                    <Label>Notes</Label>
                                    <Textarea
                                        v-model="noteContent"
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
                                                <SelectItem v-for="checklist in checklists" :key="checklist.id" :value="checklist.id">
                                                    {{ checklist.name }}
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

                                    <div v-if="selectedChecklistId && availableSections.length > 0" class="space-y-2">
                                        <Label>Section <span class="text-muted-foreground font-normal">(optional)</span></Label>
                                        <Select v-model="selectedChecklistId_section">
                                            <SelectTrigger>
                                                <SelectValue placeholder="End of checklist (default)" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem :value="null">End of checklist (default)</SelectItem>
                                                <SelectItem v-for="section in availableSections" :key="section.id" :value="section.id">
                                                    {{ section.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div v-if="parsedNotes.length > 0" class="space-y-2 overflow-hidden">
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
                                    <Button
                                        @click="importNotes"
                                        :disabled="!selectedChecklistId || parsedNotes.length === 0 || !selectedColumnKey || isImporting"
                                        class="gap-2"
                                    >
                                        <Import class="h-4 w-4" />
                                        Import to Checklist
                                    </Button>
                                </div>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <Link :href="`/projects/${project.id}/checklists/create`">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Checklist
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-if="checklists.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <ClipboardList class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No checklists yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Create your first checklist to track items.</p>
                    <Link :href="`/projects/${project.id}/checklists/create`" class="mt-4 inline-block">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Create Checklist
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-else class="space-y-6">
                <!-- Draft Cards (always outside category groups) -->
                <div v-if="drafts.length > 0" class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="draft in drafts"
                        :key="draft.id"
                        class="transition-all border-dashed border-amber-400 bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-amber-950/20 dark:to-yellow-950/20 cursor-pointer h-full relative group flex flex-col"
                        @click="openDraft(draft)"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            class="absolute top-12 right-2 h-7 w-7 p-0 opacity-0 group-hover:opacity-100 transition-opacity text-muted-foreground hover:text-destructive z-10"
                            @click.stop="confirmDeleteDraft(draft.id)"
                            title="Delete draft"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                        <CardHeader class="flex-1">
                            <CardTitle class="flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                    <StickyNote class="h-5 w-5 text-amber-500" />
                                    <Badge variant="warning" class="text-xs">Draft</Badge>
                                </span>
                                <Pencil class="h-4 w-4 text-muted-foreground" />
                            </CardTitle>
                            <CardDescription class="line-clamp-2">
                                {{ getDraftPreview(draft.content) }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="text-xs text-muted-foreground">
                                Last edited: {{ formatDraftDate(draft.updatedAt) }}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Category Groups -->
                <div
                    v-for="(group, groupIdx) in categoryGroups"
                    :key="group.name ?? '__uncategorized__'"
                    class="space-y-3"
                    @dragover.prevent="onDragOver($event, group.name)"
                    @dragleave="onDragLeave($event, group.name)"
                    @drop="onDropOnCategory($event, group.name)"
                >
                    <!-- Category Header (shown when there are multiple groups or named categories) -->
                    <div
                        v-if="hasCategories"
                        :draggable="canDrag && categoryGroups.length > 1 && renamingCategory !== group.name"
                        class="flex items-center gap-2 select-none rounded-md px-2 py-1.5 transition-colors hover:bg-muted/50 group/header"
                        :class="{
                            'bg-primary/5 ring-1 ring-primary/20': isDragging && dragOverCategory === group.name,
                            'opacity-50': isDraggingCategory && draggedCategoryName === group.name,
                            'cursor-pointer': true,
                        }"
                        @click="renamingCategory !== group.name && toggleCategory(group.name)"
                        @dragstart.stop="onCategoryDragStart($event, group.name)"
                        @dragend="onDragEnd"
                    >
                        <!-- Category drag handle -->
                        <div
                            v-if="canDrag && categoryGroups.length > 1"
                            class="opacity-0 group-hover/header:opacity-100 transition-opacity cursor-grab active:cursor-grabbing shrink-0"
                            @click.stop
                        >
                            <GripVertical class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <component
                            :is="isCategoryCollapsed(group.name) ? ChevronRight : ChevronDown"
                            class="h-4 w-4 text-muted-foreground shrink-0"
                        />
                        <FolderOpen v-if="group.name" class="h-4 w-4 text-muted-foreground shrink-0" />

                        <!-- Inline rename input -->
                        <template v-if="renamingCategory === group.name">
                            <input
                                ref="renameCategoryInput"
                                v-model="renameCategoryValue"
                                type="text"
                                class="text-sm font-medium bg-background border border-input rounded px-2 py-0.5 outline-none focus:ring-1 focus:ring-ring w-48"
                                @click.stop
                                @keydown.enter="commitRenameCategory"
                                @keydown.escape="renamingCategory = null"
                                @blur="commitRenameCategory"
                            />
                        </template>
                        <template v-else>
                            <span class="text-sm font-medium">{{ group.label }}</span>
                        </template>

                        <Badge variant="secondary" class="text-xs ml-1">{{ group.checklists.length }}</Badge>

                        <!-- Rename button for named categories -->
                        <button
                            v-if="group.name && renamingCategory !== group.name"
                            class="opacity-0 group-hover/header:opacity-100 transition-opacity cursor-pointer p-0.5 rounded hover:bg-muted"
                            title="Rename category"
                            @click.stop="startRenameCategory(group.name)"
                        >
                            <Pencil class="h-3.5 w-3.5 text-muted-foreground" />
                        </button>
                    </div>

                    <!-- Cards Grid -->
                    <div
                        v-show="!isCategoryCollapsed(group.name)"
                        class="grid gap-5 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="checklist in group.checklists"
                            :key="checklist.id"
                            :draggable="canDrag"
                            class="relative group/card"
                            @dragstart="onDragStart($event, checklist)"
                            @dragend="onDragEnd"
                            @dragover.prevent.stop="onDragOver($event, group.name)"
                            @drop.stop="onDropOnCard($event, checklist, group.name)"
                        >
                            <!-- Drag handle -->
                            <div
                                v-if="canDrag"
                                class="absolute top-2 right-2 z-10 opacity-0 group-hover/card:opacity-100 transition-opacity cursor-grab active:cursor-grabbing p-1 rounded hover:bg-muted"
                                @mousedown.stop
                            >
                                <GripVertical class="h-4 w-4 text-muted-foreground" />
                            </div>

                            <Link :href="`/projects/${project.id}/checklists/${checklist.id}`">
                                <Card
                                    class="transition-all hover:border-primary cursor-pointer h-full flex flex-col"
                                    :class="{ 'opacity-50': isDragging && draggedChecklist?.id === checklist.id }"
                                >
                                    <CardHeader class="flex-1">
                                        <CardTitle class="flex items-start gap-2 pr-8">
                                            <ClipboardList class="h-4 w-4 shrink-0 text-primary" />
                                            <span class="break-words" v-html="highlight(checklist.name)" />
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                            <FileText class="h-4 w-4" />
                                            {{ checklist.rows_count || 0 }} items
                                            <!-- Category pill -->
                                            <DropdownMenu v-if="canDrag">
                                                <DropdownMenuTrigger as-child>
                                                    <button
                                                        @click.prevent.stop
                                                        class="ml-auto inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs transition-colors cursor-pointer"
                                                        :class="checklist.category
                                                            ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                                            : 'bg-muted text-muted-foreground hover:bg-muted/80 opacity-0 group-hover/card:opacity-100'"
                                                    >
                                                        <Tag class="h-3 w-3" />
                                                        {{ checklist.category || 'Category' }}
                                                    </button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" class="w-48">
                                                    <DropdownMenuItem
                                                        v-for="cat in allCategories"
                                                        :key="cat"
                                                        class="cursor-pointer"
                                                        @click.prevent.stop="setCategoryForChecklist(checklist.id, cat)"
                                                    >
                                                        <FolderOpen class="h-4 w-4 mr-2" />
                                                        {{ cat }}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator v-if="allCategories.length > 0" />
                                                    <DropdownMenuItem
                                                        class="cursor-pointer"
                                                        @click.prevent.stop="editingChecklistId = checklist.id; showCategoryDialog = true"
                                                    >
                                                        <Plus class="h-4 w-4 mr-2" />
                                                        New category...
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        v-if="checklist.category"
                                                        class="cursor-pointer text-destructive"
                                                        @click.prevent.stop="setCategoryForChecklist(checklist.id, null)"
                                                    >
                                                        <X class="h-4 w-4 mr-2" />
                                                        Remove category
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- No results -->
                <div v-if="filteredChecklists.length === 0 && searchQuery.trim()" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                    <Search class="h-12 w-12 mb-3" />
                    <p class="font-semibold">No results found</p>
                    <p class="text-sm max-w-full truncate px-4">No checklists match "{{ searchQuery }}"</p>
                </div>
            </div>

            <!-- New Category Dialog -->
            <Dialog v-model:open="showCategoryDialog">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>New Category</DialogTitle>
                        <DialogDescription>
                            Enter a name for the new category.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="py-4">
                        <Input
                            v-model="newCategoryName"
                            placeholder="Category name..."
                            @keydown.enter="editingChecklistId && setNewCategory(editingChecklistId)"
                        />
                    </div>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" @click="showCategoryDialog = false; newCategoryName = ''" class="flex-1 sm:flex-none">
                            Cancel
                        </Button>
                        <Button
                            @click="editingChecklistId && setNewCategory(editingChecklistId)"
                            :disabled="!newCategoryName.trim()"
                            class="flex-1 sm:flex-none"
                        >
                            Create
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Dialog -->
            <Dialog v-model:open="showDeleteConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Draft?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this draft? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" @click="showDeleteConfirm = false" class="flex-1 sm:flex-none">
                            No
                        </Button>
                        <Button variant="destructive" @click="deleteDraft()" class="flex-1 sm:flex-none">
                            Yes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.search-highlight) {
    background-color: rgb(147 197 253 / 0.5);
    border-radius: 0.125rem;
    padding: 0.0625rem 0.125rem;
}
</style>
