<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FileText,
    Plus,
    Search,
    X,
    FolderTree,
    ExternalLink,
    ChevronRight,
    GripVertical,
    StickyNote,
    Pencil,
    Download,
    Upload,
    FileSpreadsheet,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useSearch } from '@/composables/useSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';

interface Documentation {
    id: number;
    title: string;
    content: string | null;
    category: string | null;
    order: number;
    children?: Documentation[];
    created_at: string;
}

const props = defineProps<{
    project: Project;
    documentations: Documentation[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    {
        title: 'Documentations',
        href: `/projects/${props.project.id}/documentations`,
    },
];

const { searchQuery, highlight } = useSearch();

// Local state for drag-and-drop
const localDocs = ref<Documentation[]>([...props.documentations]);

watch(
    () => props.documentations,
    (val) => {
        localDocs.value = [...val];
    },
);

const filterDocs = (docs: Documentation[]): Documentation[] => {
    if (!searchQuery.value.trim()) return docs;
    const query = searchQuery.value.toLowerCase();
    return docs.filter((doc) => {
        const matchesSelf =
            doc.title.toLowerCase().includes(query) ||
            doc.content?.toLowerCase().includes(query);
        const matchingChildren = doc.children ? filterDocs(doc.children) : [];
        return matchesSelf || matchingChildren.length > 0;
    });
};

const filteredDocs = computed(() => filterDocs(localDocs.value));

const filteredChildren = (children: Documentation[]): Documentation[] =>
    filterDocs(children);

// Drag-and-drop
const canDrag = computed(() => !searchQuery.value.trim());
const draggedDoc = ref<Documentation | null>(null);
const dragOverDocId = ref<number | null>(null);
const isDragging = ref(false);

const onDragStart = (e: DragEvent, doc: Documentation) => {
    if (!canDrag.value) return;
    draggedDoc.value = doc;
    isDragging.value = true;
    e.dataTransfer!.effectAllowed = 'move';
    e.dataTransfer!.setData('text/plain', String(doc.id));
    const rowEl = (e.currentTarget as HTMLElement).parentElement;
    if (rowEl) {
        e.dataTransfer!.setDragImage(rowEl, 0, 0);
    }
};

const onDragEnd = () => {
    draggedDoc.value = null;
    dragOverDocId.value = null;
    isDragging.value = false;
};

const onDragOverDoc = (e: DragEvent, targetDoc: Documentation) => {
    e.preventDefault();
    e.dataTransfer!.dropEffect = 'move';
    dragOverDocId.value = targetDoc.id;
};

const onDragLeaveDoc = (e: DragEvent, targetDoc: Documentation) => {
    if (dragOverDocId.value === targetDoc.id) {
        dragOverDocId.value = null;
    }
};

const onDropOnDoc = (
    e: DragEvent,
    targetDoc: Documentation,
    parentId: number | null,
) => {
    e.preventDefault();
    if (!draggedDoc.value || draggedDoc.value.id === targetDoc.id) {
        onDragEnd();
        return;
    }

    const dragged = draggedDoc.value;

    const isRootDrag = localDocs.value.some((d) => d.id === dragged.id);

    if (parentId === null && isRootDrag) {
        // Root doc dropped on another root doc — reorder among roots
        const list = [...localDocs.value];
        const dragIdx = list.findIndex((d) => d.id === dragged.id);
        list.splice(dragIdx, 1);
        const targetIdx = list.findIndex((d) => d.id === targetDoc.id);
        list.splice(targetIdx, 0, dragged);
        list.forEach((d, i) => {
            d.order = i;
        });
        localDocs.value = list;
    } else if (parentId === null && !isRootDrag) {
        // Child dropped on a root doc — move as child of that root doc
        for (const doc of localDocs.value) {
            if (doc.children) {
                const childIdx = doc.children.findIndex(
                    (c) => c.id === dragged.id,
                );
                if (childIdx !== -1) {
                    doc.children.splice(childIdx, 1);
                    break;
                }
            }
        }
        if (!targetDoc.children) targetDoc.children = [];
        dragged.order = targetDoc.children.length;
        targetDoc.children.push(dragged);
    } else {
        // Target is a child — reorder within parent or move into parent
        const parentDoc = localDocs.value.find((d) => d.id === parentId);
        if (!parentDoc || !parentDoc.children) {
            onDragEnd();
            return;
        }

        // Remove dragged from wherever it is
        const rootIdx = localDocs.value.findIndex((d) => d.id === dragged.id);
        if (rootIdx !== -1) {
            localDocs.value.splice(rootIdx, 1);
            localDocs.value.forEach((d, i) => {
                d.order = i;
            });
        } else {
            for (const doc of localDocs.value) {
                if (doc.children) {
                    const childIdx = doc.children.findIndex(
                        (c) => c.id === dragged.id,
                    );
                    if (childIdx !== -1) {
                        doc.children.splice(childIdx, 1);
                        break;
                    }
                }
            }
        }

        // Insert before target in parent's children
        const targetIdx = parentDoc.children.findIndex(
            (c) => c.id === targetDoc.id,
        );
        parentDoc.children.splice(targetIdx, 0, dragged);
        parentDoc.children.forEach((c, i) => {
            c.order = i;
        });
    }

    saveReorder();
    onDragEnd();
};

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const _onDropOnParent = (e: DragEvent, parentDoc: Documentation) => {
    e.preventDefault();
    if (!draggedDoc.value || draggedDoc.value.id === parentDoc.id) {
        onDragEnd();
        return;
    }

    const dragged = draggedDoc.value;

    // Remove from wherever it currently is
    const rootIdx = localDocs.value.findIndex((d) => d.id === dragged.id);
    if (rootIdx !== -1) {
        localDocs.value.splice(rootIdx, 1);
        localDocs.value.forEach((d, i) => {
            d.order = i;
        });
    } else {
        for (const doc of localDocs.value) {
            if (doc.children) {
                const childIdx = doc.children.findIndex(
                    (c) => c.id === dragged.id,
                );
                if (childIdx !== -1) {
                    doc.children.splice(childIdx, 1);
                    break;
                }
            }
        }
    }

    // Add as last child of parentDoc
    if (!parentDoc.children) parentDoc.children = [];
    dragged.order = parentDoc.children.length;
    parentDoc.children.push(dragged);

    saveReorder();
    onDragEnd();
};

const saveReorder = () => {
    const items: { id: number; order: number; parent_id: number | null }[] = [];

    localDocs.value.forEach((doc, i) => {
        items.push({ id: doc.id, order: i, parent_id: null });
        if (doc.children) {
            doc.children.forEach((child, j) => {
                items.push({ id: child.id, order: j, parent_id: doc.id });
            });
        }
    });

    router.post(
        `/projects/${props.project.id}/documentations/reorder`,
        { items },
        { preserveScroll: true, preserveState: true },
    );
};

const decodeHtmlEntities = (text: string): string => {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
};

const highlightDescription = (content: string): string => {
    const withoutTags = content.replace(/<[^>]*>/g, ' ');
    const plain =
        decodeHtmlEntities(withoutTags)
            .replace(/\s+/g, ' ')
            .trim()
            .substring(0, 200) + '...';
    return highlight(plain);
};

// Empty state — Create a Note (creates a new top-level documentation page)
const showNoteDialog = ref(false);
const noteTitle = ref('');
const noteContent = ref('');
const isCreatingNote = ref(false);
const hasDraft = ref(false);
const DRAFT_STORAGE_KEY = `documentation-note-draft-${props.project.id}`;

interface NoteDraft {
    title: string;
    content: string;
}

const loadDraftFlag = () => {
    try {
        const saved = localStorage.getItem(DRAFT_STORAGE_KEY);
        if (saved) {
            const draft: NoteDraft = JSON.parse(saved);
            if (draft.title?.trim() || draft.content?.trim()) {
                hasDraft.value = true;
            }
        }
    } catch (e) {
        console.error('Failed to load draft:', e);
    }
};

const saveDraft = () => {
    if (!noteTitle.value.trim() && !noteContent.value.trim()) {
        deleteDraft();
        return;
    }
    try {
        localStorage.setItem(
            DRAFT_STORAGE_KEY,
            JSON.stringify({
                title: noteTitle.value,
                content: noteContent.value,
            }),
        );
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

const openDraft = () => {
    try {
        const saved = localStorage.getItem(DRAFT_STORAGE_KEY);
        if (saved) {
            const draft: NoteDraft = JSON.parse(saved);
            noteTitle.value = draft.title || '';
            noteContent.value = draft.content || '';
        }
    } catch (e) {
        console.error('Failed to open draft:', e);
    }
};

const clearNote = () => {
    noteTitle.value = '';
    noteContent.value = '';
    deleteDraft();
};

watch(showNoteDialog, (open) => {
    if (open) {
        if (hasDraft.value) {
            openDraft();
        }
        return;
    }

    if (noteTitle.value.trim() || noteContent.value.trim()) {
        saveDraft();
    }

    noteTitle.value = '';
    noteContent.value = '';
});

const escapeHtml = (text: string): string =>
    text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

const submitNote = () => {
    if (!noteTitle.value.trim() || !noteContent.value.trim()) return;

    isCreatingNote.value = true;

    // Documentation content is rendered as HTML — turn each line into its
    // own escaped paragraph rather than storing raw plain text.
    const html = noteContent.value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0)
        .map((line) => `<p>${escapeHtml(line)}</p>`)
        .join('');

    router.post(
        `/projects/${props.project.id}/documentations/note`,
        {
            title: noteTitle.value.trim(),
            content: html,
        },
        {
            onSuccess: () => {
                showNoteDialog.value = false;
                noteTitle.value = '';
                noteContent.value = '';
                isCreatingNote.value = false;
                deleteDraft();
            },
            onError: () => {
                isCreatingNote.value = false;
            },
        },
    );
};

// Empty state — Import (creates new top-level documentation(s) from a file)
const showImportDialog = ref(false);
const importFileInput = ref<HTMLInputElement | null>(null);
const importFile = ref<File | null>(null);
const importError = ref<string | null>(null);
const isImportingDoc = ref(false);

const handleImportFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    importError.value = null;

    if (!file) {
        importFile.value = null;
        return;
    }

    const allowed = [
        '.json',
        '.pdf',
        '.doc',
        '.docx',
        '.xls',
        '.xlsx',
        '.csv',
        '.txt',
    ];
    const ext = '.' + file.name.split('.').pop()?.toLowerCase();
    if (!allowed.includes(ext)) {
        importError.value =
            'Unsupported format. Allowed: JSON, PDF, DOC, DOCX, XLS, XLSX, CSV, TXT.';
        importFile.value = null;
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        importError.value = 'File is too large. Maximum size is 5MB.';
        importFile.value = null;
        return;
    }

    importFile.value = file;
};

const closeImportDialog = () => {
    showImportDialog.value = false;
    importFile.value = null;
    importError.value = null;
};

const submitImport = () => {
    if (!importFile.value) return;

    isImportingDoc.value = true;
    importError.value = null;

    const formData = new FormData();
    formData.append('file', importFile.value);

    router.post(
        `/projects/${props.project.id}/documentations/import-new`,
        formData,
        {
            forceFormData: true,
            onSuccess: () => {
                closeImportDialog();
                isImportingDoc.value = false;
            },
            onError: (errors) => {
                importError.value = errors.file || 'Import failed.';
                isImportingDoc.value = false;
            },
        },
    );
};

// Flatten documents (with their direct children) for the document pickers
// used by the File menu's Export/Import-into-document dialogs.
const allDocumentsFlat = computed(() => {
    const result: { id: number; label: string }[] = [];
    for (const doc of localDocs.value) {
        result.push({ id: doc.id, label: doc.title });
        for (const child of doc.children ?? []) {
            result.push({
                id: child.id,
                label: `${doc.title} / ${child.title}`,
            });
        }
    }
    return result;
});

// File menu — Export a selected document
const showExportDialog = ref(false);
const exportDocumentId = ref('');

const submitExport = () => {
    if (!exportDocumentId.value) return;
    window.location.href = `/projects/${props.project.id}/documentations/${exportDocumentId.value}/export`;
    showExportDialog.value = false;
    exportDocumentId.value = '';
};

// File menu — Import into a selected existing document
const showFileImportDialog = ref(false);
const fileImportDocumentId = ref('');
const fileImportFileInputRef = ref<HTMLInputElement | null>(null);
const fileImportFile = ref<File | null>(null);
const fileImportError = ref<string | null>(null);
const isFileImporting = ref(false);

const handleFileImportFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    fileImportError.value = null;

    if (!file) {
        fileImportFile.value = null;
        return;
    }

    const allowed = [
        '.json',
        '.pdf',
        '.doc',
        '.docx',
        '.xls',
        '.xlsx',
        '.csv',
        '.txt',
    ];
    const ext = '.' + file.name.split('.').pop()?.toLowerCase();
    if (!allowed.includes(ext)) {
        fileImportError.value =
            'Unsupported format. Allowed: JSON, PDF, DOC, DOCX, XLS, XLSX, CSV, TXT.';
        fileImportFile.value = null;
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        fileImportError.value = 'File is too large. Maximum size is 5MB.';
        fileImportFile.value = null;
        return;
    }

    fileImportFile.value = file;
};

const closeFileImportDialog = () => {
    showFileImportDialog.value = false;
    fileImportDocumentId.value = '';
    fileImportFile.value = null;
    fileImportError.value = null;
};

const submitFileImport = () => {
    if (!fileImportDocumentId.value || !fileImportFile.value) return;

    isFileImporting.value = true;
    fileImportError.value = null;

    const formData = new FormData();
    formData.append('file', fileImportFile.value);

    router.post(
        `/projects/${props.project.id}/documentations/${fileImportDocumentId.value}/import`,
        formData,
        {
            forceFormData: true,
            onSuccess: () => {
                closeFileImportDialog();
                isFileImporting.value = false;
            },
            onError: (errors) => {
                fileImportError.value = errors.file || 'Import failed.';
                isFileImporting.value = false;
            },
        },
    );
};

onMounted(() => {
    loadDraftFlag();
});
</script>

<template>
    <Head title="Documentations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <h1
                    class="flex items-start gap-2 text-2xl font-bold tracking-tight"
                >
                    <FileText class="mt-1 h-6 w-6 shrink-0 text-primary" />
                    Documentations
                </h1>
                <div
                    v-if="documentations.length > 0"
                    class="flex flex-wrap items-center gap-2"
                >
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                class="cursor-pointer gap-1.5"
                            >
                                <FileSpreadsheet class="h-4 w-4" />
                                File
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <RestrictedAction>
                                <DropdownMenuItem
                                    class="cursor-pointer"
                                    @click="showFileImportDialog = true"
                                >
                                    <Download class="mr-2 h-4 w-4" />
                                    Import
                                </DropdownMenuItem>
                            </RestrictedAction>
                            <DropdownMenuItem
                                class="cursor-pointer"
                                @click="showExportDialog = true"
                            >
                                <Upload class="mr-2 h-4 w-4" />
                                Export
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <RestrictedAction>
                        <Link
                            :href="`/projects/${project.id}/documentations/create`"
                        >
                            <Button variant="cta" class="cursor-pointer gap-2">
                                <Plus class="h-4 w-4" />
                                Documentation
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <div
                v-if="documentations.length === 0"
                class="flex flex-1 items-center justify-center"
            >
                <div class="text-center">
                    <FileText class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">
                        No documentations yet
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Create your first documentation page.
                    </p>
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <RestrictedAction>
                            <Link
                                :href="`/projects/${project.id}/documentations/create`"
                            >
                                <Button
                                    variant="cta"
                                    class="cursor-pointer gap-2"
                                >
                                    <Plus class="h-4 w-4" />
                                    Create Documentation
                                </Button>
                            </Link>
                        </RestrictedAction>
                        <RestrictedAction>
                            <Button
                                :variant="hasDraft ? 'cta' : 'outline'"
                                class="cursor-pointer gap-2"
                                @click="showNoteDialog = true"
                            >
                                <Pencil v-if="hasDraft" class="h-4 w-4" />
                                <StickyNote v-else class="h-4 w-4" />
                                {{ hasDraft ? 'Draft' : 'Create a Note' }}
                            </Button>
                        </RestrictedAction>
                        <RestrictedAction>
                            <Button
                                variant="outline"
                                class="cursor-pointer gap-2"
                                @click="showImportDialog = true"
                            >
                                <Download class="h-4 w-4" />
                                Import
                            </Button>
                        </RestrictedAction>
                    </div>
                </div>
            </div>

            <template v-else>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                    <!-- Sidebar with navigation -->
                    <div class="self-start lg:sticky lg:top-6 lg:col-span-1">
                        <div class="rounded-xl border bg-card shadow-sm">
                            <div class="border-b bg-muted/30 p-3">
                                <div
                                    class="flex items-center gap-2 text-sm font-medium"
                                >
                                    <FolderTree class="h-4 w-4 text-primary" />
                                    <span>Documents</span>
                                </div>
                                <div class="relative mt-2">
                                    <Search
                                        class="absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Search..."
                                        class="h-8 bg-background/60 pr-7 pl-7 text-xs"
                                    />
                                    <button
                                        v-if="searchQuery"
                                        @click="searchQuery = ''"
                                        class="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div
                                class="max-h-[calc(100vh-270px)] space-y-0.5 overflow-y-auto p-2"
                            >
                                <template v-if="filteredDocs.length">
                                    <template
                                        v-for="doc in filteredDocs"
                                        :key="doc.id"
                                    >
                                        <div
                                            class="group flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 transition-all duration-150 hover:bg-muted/70"
                                            :class="{
                                                'opacity-50':
                                                    isDragging &&
                                                    draggedDoc?.id === doc.id,
                                                'bg-primary/5 ring-2 ring-primary':
                                                    isDragging &&
                                                    dragOverDocId === doc.id &&
                                                    draggedDoc?.id !== doc.id,
                                            }"
                                            @dragover="
                                                onDragOverDoc($event, doc)
                                            "
                                            @dragleave="
                                                onDragLeaveDoc($event, doc)
                                            "
                                            @drop="
                                                onDropOnDoc($event, doc, null)
                                            "
                                        >
                                            <div
                                                v-if="canDrag"
                                                draggable="true"
                                                class="mr-1 shrink-0 cursor-grab opacity-0 transition-opacity group-hover:opacity-100 active:cursor-grabbing"
                                                @dragstart="
                                                    onDragStart($event, doc)
                                                "
                                                @dragend="onDragEnd"
                                            >
                                                <GripVertical
                                                    class="h-3.5 w-3.5 text-muted-foreground"
                                                />
                                            </div>
                                            <Link
                                                :href="`/projects/${project.id}/documentations/${doc.id}`"
                                                class="flex min-w-0 flex-1 items-center gap-2"
                                            >
                                                <FileText
                                                    class="h-4 w-4 shrink-0 text-primary"
                                                />
                                                <span
                                                    class="truncate text-sm font-medium"
                                                    >{{ doc.title }}</span
                                                >
                                            </Link>
                                            <Link
                                                :href="`/projects/${project.id}/documentations/${doc.id}`"
                                                @click.stop
                                                class="ml-2 shrink-0 rounded p-1 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-muted"
                                            >
                                                <ExternalLink class="h-3 w-3" />
                                            </Link>
                                        </div>
                                        <!-- Nested children -->
                                        <template v-if="doc.children?.length">
                                            <template
                                                v-for="child in filteredChildren(
                                                    doc.children,
                                                )"
                                                :key="child.id"
                                            >
                                                <div
                                                    class="group ml-4 flex cursor-pointer items-center justify-between rounded-lg px-3 py-1.5 transition-all duration-150 hover:bg-muted/70"
                                                    :class="{
                                                        'opacity-50':
                                                            isDragging &&
                                                            draggedDoc?.id ===
                                                                child.id,
                                                        'bg-primary/5 ring-2 ring-primary':
                                                            isDragging &&
                                                            dragOverDocId ===
                                                                child.id &&
                                                            draggedDoc?.id !==
                                                                child.id,
                                                    }"
                                                    @dragover="
                                                        onDragOverDoc(
                                                            $event,
                                                            child,
                                                        )
                                                    "
                                                    @dragleave="
                                                        onDragLeaveDoc(
                                                            $event,
                                                            child,
                                                        )
                                                    "
                                                    @drop="
                                                        onDropOnDoc(
                                                            $event,
                                                            child,
                                                            doc.id,
                                                        )
                                                    "
                                                >
                                                    <div
                                                        v-if="canDrag"
                                                        draggable="true"
                                                        class="mr-1 shrink-0 cursor-grab opacity-0 transition-opacity group-hover:opacity-100 active:cursor-grabbing"
                                                        @dragstart="
                                                            onDragStart(
                                                                $event,
                                                                child,
                                                            )
                                                        "
                                                        @dragend="onDragEnd"
                                                    >
                                                        <GripVertical
                                                            class="h-3 w-3 text-muted-foreground"
                                                        />
                                                    </div>
                                                    <Link
                                                        :href="`/projects/${project.id}/documentations/${child.id}`"
                                                        class="flex min-w-0 flex-1 items-center gap-2"
                                                    >
                                                        <ChevronRight
                                                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                                                        />
                                                        <span
                                                            class="truncate text-sm"
                                                            >{{
                                                                child.title
                                                            }}</span
                                                        >
                                                    </Link>
                                                    <Link
                                                        :href="`/projects/${project.id}/documentations/${child.id}`"
                                                        @click.stop
                                                        class="ml-2 shrink-0 rounded p-1 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-muted"
                                                    >
                                                        <ExternalLink
                                                            class="h-3 w-3"
                                                        />
                                                    </Link>
                                                </div>
                                            </template>
                                        </template>
                                    </template>
                                </template>
                                <div
                                    v-else-if="searchQuery.trim()"
                                    class="px-3 py-2 text-sm text-muted-foreground"
                                >
                                    No matches found
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main content -->
                    <div class="space-y-4 lg:col-span-3">
                        <div
                            v-if="filteredDocs.length === 0"
                            class="flex flex-col items-center justify-center py-12"
                        >
                            <FileText
                                class="mb-4 h-12 w-12 text-muted-foreground/50"
                            />
                            <p class="text-muted-foreground">
                                No documentations match your search.
                            </p>
                        </div>

                        <div
                            v-for="doc in filteredDocs"
                            :key="doc.id"
                            class="block cursor-pointer"
                            @click="
                                router.visit(
                                    `/projects/${project.id}/documentations/${doc.id}`,
                                )
                            "
                        >
                            <Card
                                class="transition-colors hover:border-primary"
                            >
                                <CardHeader class="pb-2">
                                    <div
                                        class="flex items-start justify-between"
                                    >
                                        <CardTitle
                                            class="flex items-start gap-2 text-lg"
                                        >
                                            <FileText
                                                class="mt-1 h-4 w-4 shrink-0 text-primary"
                                            />
                                            <span
                                                v-html="highlight(doc.title)"
                                            />
                                        </CardTitle>
                                        <span
                                            v-if="doc.category"
                                            class="rounded bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800"
                                        >
                                            {{ doc.category }}
                                        </span>
                                    </div>
                                    <!-- eslint-disable vue/no-v-text-v-html-on-component -->
                                    <CardDescription
                                        v-if="doc.content"
                                        class="line-clamp-2"
                                        v-html="
                                            highlightDescription(doc.content)
                                        "
                                    />
                                    <!-- eslint-enable vue/no-v-text-v-html-on-component -->
                                </CardHeader>
                                <CardContent
                                    v-if="
                                        doc.children && doc.children.length > 0
                                    "
                                >
                                    <div class="flex flex-wrap gap-1.5">
                                        <Link
                                            v-for="child in doc.children"
                                            :key="child.id"
                                            :href="`/projects/${project.id}/documentations/${child.id}`"
                                            class="cursor-pointer rounded-md bg-muted/60 px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                                            @click.stop
                                        >
                                            <span
                                                v-html="highlight(child.title)"
                                            />
                                        </Link>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Create a Note Dialog -->
            <Dialog v-model:open="showNoteDialog">
                <DialogContent
                    class="flex max-h-[75vh] max-w-2xl flex-col"
                    style="
                        overflow: hidden !important;
                        max-width: min(42rem, calc(100vw - 2rem)) !important;
                    "
                >
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <StickyNote class="h-5 w-5 text-primary" />
                            {{ hasDraft ? 'Edit Draft' : 'Create a Note' }}
                        </DialogTitle>
                        <DialogDescription>
                            Creates a new documentation page. Each line becomes
                            a paragraph.
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        class="min-h-0 flex-1 space-y-4 overflow-y-auto px-0.5 py-4"
                    >
                        <div class="space-y-2">
                            <Label>Title</Label>
                            <Input
                                v-model="noteTitle"
                                type="text"
                                placeholder="e.g. Deployment Checklist"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label>Content</Label>
                            <Textarea
                                v-model="noteContent"
                                placeholder="Write your notes here — each line becomes a paragraph..."
                                rows="10"
                                class="resize-y text-sm"
                                style="
                                    white-space: pre-wrap;
                                    overflow-wrap: break-word;
                                    overflow-y: auto;
                                    max-height: 400px;
                                "
                            />
                        </div>
                    </div>

                    <DialogFooter
                        class="flex justify-between sm:justify-between"
                    >
                        <Button
                            v-if="noteTitle.trim() || noteContent.trim()"
                            variant="ghost"
                            @click="clearNote"
                            class="gap-2 text-muted-foreground hover:text-destructive"
                        >
                            <X class="h-4 w-4" />
                            Clear
                        </Button>
                        <div v-else></div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                @click="showNoteDialog = false"
                            >
                                Cancel
                            </Button>
                            <RestrictedAction>
                                <Button
                                    @click="submitNote"
                                    :disabled="
                                        !noteTitle.trim() ||
                                        !noteContent.trim() ||
                                        isCreatingNote
                                    "
                                    class="gap-2"
                                >
                                    <Plus class="h-4 w-4" />
                                    {{
                                        isCreatingNote
                                            ? 'Creating...'
                                            : 'Create Documentation'
                                    }}
                                </Button>
                            </RestrictedAction>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Import Dialog -->
            <Dialog
                :open="showImportDialog"
                @update:open="
                    (v: boolean) => {
                        if (!v) closeImportDialog();
                    }
                "
            >
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Download class="h-5 w-5 text-primary" />
                            Import Documentation
                        </DialogTitle>
                        <DialogDescription>
                            Upload a document file to create a new documentation
                            page. JSON files are imported as a documentation
                            tree. PDF, DOC, Excel, CSV and TXT are parsed and
                            added as a single page.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <Label>File</Label>
                            <input
                                ref="importFileInput"
                                type="file"
                                accept=".json,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                class="hidden"
                                @change="handleImportFileSelect"
                            />
                            <Button
                                variant="outline"
                                class="w-full cursor-pointer justify-start gap-2 font-normal"
                                @click="importFileInput?.click()"
                            >
                                <Upload class="h-4 w-4 text-muted-foreground" />
                                <span
                                    :class="
                                        importFile
                                            ? 'text-foreground'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        importFile
                                            ? importFile.name
                                            : 'Choose file...'
                                    }}
                                </span>
                            </Button>
                            <p class="text-xs text-muted-foreground">
                                JSON, PDF, DOC, DOCX, XLS, XLSX, CSV, TXT — max
                                5MB
                            </p>
                        </div>

                        <div
                            v-if="importFile"
                            class="rounded-lg border bg-muted/30 p-3"
                        >
                            <p class="text-sm font-medium">
                                {{ importFile.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ (importFile.size / 1024).toFixed(1) }} KB
                            </p>
                        </div>

                        <div
                            v-if="importError"
                            class="rounded-lg border border-destructive/50 bg-destructive/10 p-3"
                        >
                            <p class="text-sm text-destructive">
                                {{ importError }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="closeImportDialog"
                            >Cancel</Button
                        >
                        <RestrictedAction>
                            <Button
                                @click="submitImport"
                                :disabled="!importFile || isImportingDoc"
                                class="gap-2"
                            >
                                <Download class="h-4 w-4" />
                                {{ isImportingDoc ? 'Importing...' : 'Import' }}
                            </Button>
                        </RestrictedAction>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- File Menu: Export a selected document -->
            <Dialog v-model:open="showExportDialog">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Upload class="h-5 w-5 text-primary" />
                            Export Document
                        </DialogTitle>
                        <DialogDescription>
                            Select a documentation page to export as JSON.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-2 py-4">
                        <Label>Document</Label>
                        <Select v-model="exportDocumentId">
                            <SelectTrigger class="w-full">
                                <SelectValue
                                    placeholder="Select a document..."
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="doc in allDocumentsFlat"
                                    :key="doc.id"
                                    :value="String(doc.id)"
                                >
                                    {{ doc.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            @click="showExportDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            @click="submitExport"
                            :disabled="!exportDocumentId"
                            class="gap-2"
                        >
                            <Upload class="h-4 w-4" />
                            Export
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- File Menu: Import into a selected existing document -->
            <Dialog
                :open="showFileImportDialog"
                @update:open="
                    (v: boolean) => {
                        if (!v) closeFileImportDialog();
                    }
                "
            >
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Download class="h-5 w-5 text-primary" />
                            Import into Document
                        </DialogTitle>
                        <DialogDescription>
                            Upload a file to add as a sub-page of the selected
                            document. JSON files are imported as a documentation
                            tree. PDF, DOC, Excel, CSV and TXT are parsed and
                            added as a single page.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <Label>Document</Label>
                            <Select v-model="fileImportDocumentId">
                                <SelectTrigger class="w-full">
                                    <SelectValue
                                        placeholder="Select a document..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="doc in allDocumentsFlat"
                                        :key="doc.id"
                                        :value="String(doc.id)"
                                    >
                                        {{ doc.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label>File</Label>
                            <input
                                ref="fileImportFileInputRef"
                                type="file"
                                accept=".json,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                class="hidden"
                                @change="handleFileImportFileSelect"
                            />
                            <Button
                                variant="outline"
                                class="w-full cursor-pointer justify-start gap-2 font-normal"
                                @click="fileImportFileInputRef?.click()"
                            >
                                <Upload class="h-4 w-4 text-muted-foreground" />
                                <span
                                    :class="
                                        fileImportFile
                                            ? 'text-foreground'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        fileImportFile
                                            ? fileImportFile.name
                                            : 'Choose file...'
                                    }}
                                </span>
                            </Button>
                            <p class="text-xs text-muted-foreground">
                                JSON, PDF, DOC, DOCX, XLS, XLSX, CSV, TXT — max
                                5MB
                            </p>
                        </div>

                        <div
                            v-if="fileImportFile"
                            class="rounded-lg border bg-muted/30 p-3"
                        >
                            <p class="text-sm font-medium">
                                {{ fileImportFile.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ (fileImportFile.size / 1024).toFixed(1) }}
                                KB
                            </p>
                        </div>

                        <div
                            v-if="fileImportError"
                            class="rounded-lg border border-destructive/50 bg-destructive/10 p-3"
                        >
                            <p class="text-sm text-destructive">
                                {{ fileImportError }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="closeFileImportDialog"
                            >Cancel</Button
                        >
                        <RestrictedAction>
                            <Button
                                @click="submitFileImport"
                                :disabled="
                                    !fileImportDocumentId ||
                                    !fileImportFile ||
                                    isFileImporting
                                "
                                class="gap-2"
                            >
                                <Download class="h-4 w-4" />
                                {{
                                    isFileImporting ? 'Importing...' : 'Import'
                                }}
                            </Button>
                        </RestrictedAction>
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
