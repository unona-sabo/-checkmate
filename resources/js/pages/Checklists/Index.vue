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
import { Plus, ClipboardList, FileText, StickyNote, Import, Pencil, Trash2, X, Search } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { ref, computed, watch, onMounted } from 'vue';

interface NoteDraft {
    content: string;
    selectedChecklistId: number | null;
    selectedColumnKey: string;
    createdAt: string;
    updatedAt: string;
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

const searchQuery = ref('');

const filteredChecklists = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.checklists;
    }
    const query = searchQuery.value.toLowerCase();
    return props.checklists.filter(checklist =>
        checklist.name.toLowerCase().includes(query)
    );
});

const escapeRegExp = (str: string): string => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const escapeHtml = (str: string): string => str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const highlight = (text: string): string => {
    const safe = escapeHtml(text);
    if (!searchQuery.value.trim()) return safe;
    const query = escapeRegExp(searchQuery.value.trim());
    return safe.replace(new RegExp(`(${query})`, 'gi'), '<mark class="search-highlight">$1</mark>');
};

const showNoteDialog = ref(false);
const showDeleteConfirm = ref(false);
const noteContent = ref('');
const selectedChecklistId = ref<number | null>(null);
const selectedColumnKey = ref<string>('');
const isImporting = ref(false);
const hasDraft = ref(false);
const draftPreview = ref('');
const draftUpdatedAt = ref('');

const loadDraft = () => {
    try {
        const saved = localStorage.getItem(DRAFT_STORAGE_KEY);
        if (saved) {
            const draft: NoteDraft = JSON.parse(saved);
            if (draft.content && draft.content.trim()) {
                hasDraft.value = true;
                draftPreview.value = draft.content.split('\n').filter(l => l.trim()).slice(0, 3).join(', ');
                if (draftPreview.value.length > 50) {
                    draftPreview.value = draftPreview.value.substring(0, 50) + '...';
                }
                draftUpdatedAt.value = draft.updatedAt;
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
        createdAt: draftUpdatedAt.value || new Date().toISOString(),
        updatedAt: new Date().toISOString(),
    };

    try {
        localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draft));
        hasDraft.value = true;
        draftPreview.value = draft.content.split('\n').filter(l => l.trim()).slice(0, 3).join(', ');
        if (draftPreview.value.length > 50) {
            draftPreview.value = draftPreview.value.substring(0, 50) + '...';
        }
        draftUpdatedAt.value = draft.updatedAt;
    } catch (e) {
        console.error('Failed to save draft:', e);
    }
};

const confirmDeleteDraft = () => {
    showDeleteConfirm.value = true;
};

const deleteDraft = () => {
    try {
        localStorage.removeItem(DRAFT_STORAGE_KEY);
        hasDraft.value = false;
        draftPreview.value = '';
        draftUpdatedAt.value = '';
        showDeleteConfirm.value = false;
    } catch (e) {
        console.error('Failed to delete draft:', e);
    }
};

const openDraft = () => {
    try {
        const saved = localStorage.getItem(DRAFT_STORAGE_KEY);
        if (saved) {
            const draft: NoteDraft = JSON.parse(saved);
            noteContent.value = draft.content;
            selectedChecklistId.value = draft.selectedChecklistId;
            selectedColumnKey.value = draft.selectedColumnKey;
            showNoteDialog.value = true;
        }
    } catch (e) {
        console.error('Failed to open draft:', e);
    }
};

const formatDraftDate = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    loadDraft();
});

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

watch(selectedChecklistId, () => {
    if (availableColumns.value.length > 0) {
        selectedColumnKey.value = availableColumns.value[0].key;
    }
});

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

const importNotes = () => {
    if (!selectedChecklistId.value || parsedNotes.value.length === 0 || !selectedColumnKey.value) return;

    isImporting.value = true;

    router.post(
        `/projects/${props.project.id}/checklists/${selectedChecklistId.value}/import-notes`,
        {
            notes: parsedNotes.value,
            column_key: selectedColumnKey.value,
        },
        {
            onSuccess: () => {
                showNoteDialog.value = false;
                noteContent.value = '';
                selectedChecklistId.value = null;
                selectedColumnKey.value = '';
                isImporting.value = false;
                deleteDraft();
            },
            onError: () => {
                isImporting.value = false;
            },
        }
    );
};

const clearNotes = () => {
    noteContent.value = '';
    deleteDraft();
};

const onDialogClose = (open: boolean) => {
    if (!open && noteContent.value.trim()) {
        // Save draft when closing with content
        saveDraft();
    }
    if (!open) {
        // Reset state after close animation completes
        setTimeout(() => {
            noteContent.value = '';
            selectedChecklistId.value = null;
            selectedColumnKey.value = '';
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
                            <Button variant="outline" class="gap-2">
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

            <div v-else class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <!-- Draft Card -->
                <Card
                    v-if="hasDraft"
                    class="transition-all border-dashed border-amber-400 bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-amber-950/20 dark:to-yellow-950/20 cursor-pointer h-full relative group flex flex-col"
                    @click="openDraft"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="absolute top-12 right-2 h-7 w-7 p-0 opacity-0 group-hover:opacity-100 transition-opacity text-muted-foreground hover:text-destructive z-10"
                        @click.stop="confirmDeleteDraft"
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
                            {{ draftPreview }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-xs text-muted-foreground">
                            Last edited: {{ formatDraftDate(draftUpdatedAt) }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Checklist Cards -->
                <Link v-for="checklist in filteredChecklists" :key="checklist.id" :href="`/projects/${project.id}/checklists/${checklist.id}`">
                    <Card class="transition-all hover:border-primary cursor-pointer h-full flex flex-col">
                        <CardHeader class="flex-1">
                            <CardTitle class="flex items-start gap-2">
                                <ClipboardList class="h-4 w-4 shrink-0 text-primary" />
                                <span class="break-words" v-html="highlight(checklist.name)" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <FileText class="h-4 w-4" />
                                {{ checklist.rows_count || 0 }} items
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>

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
                        <Button variant="destructive" @click="deleteDraft" class="flex-1 sm:flex-none">
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
