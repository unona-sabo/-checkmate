<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { StickyNote, Plus, FileText, Trash2, Search, X } from 'lucide-vue-next';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { useSearch } from '@/composables/useSearch';
import { ref, computed } from 'vue';

interface Documentation {
    id: number;
    title: string;
}

interface Note {
    id: number;
    title: string | null;
    content: string | null;
    is_draft: boolean;
    documentation: Documentation | null;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    project: Project;
    notes: Note[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Notes', href: `/projects/${props.project.id}/notes` },
];

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const truncateContent = (content: string | null, length: number = 150) => {
    if (!content) return '';
    if (content.length <= length) return content;
    return content.substring(0, length) + '...';
};

const { searchQuery, highlight } = useSearch();

const filteredNotes = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return props.notes;
    return props.notes.filter(note =>
        (note.title ?? '').toLowerCase().includes(query) ||
        (note.content ?? '').toLowerCase().includes(query),
    );
});

const showDeleteConfirm = ref(false);
const noteToDelete = ref<Note | null>(null);

const confirmDelete = (note: Note) => {
    noteToDelete.value = note;
    showDeleteConfirm.value = true;
};

const deleteNote = () => {
    if (!noteToDelete.value) return;
    router.delete(`/projects/${props.project.id}/notes/${noteToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteConfirm.value = false;
            noteToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head title="Notes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="flex items-start gap-2 text-2xl font-bold tracking-tight">
                    <StickyNote class="h-6 w-6 shrink-0 mt-1 text-primary" />
                    Notes
                </h1>
                <div class="flex items-center gap-3">
                    <div v-if="notes.length > 0" class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search notes..."
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
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/notes/create`">
                            <Button variant="cta" class="gap-2">
                                <Plus class="h-4 w-4" />
                                New Note
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <div v-if="notes.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <StickyNote class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No notes yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Create your first note to get started.</p>
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/notes/create`" class="mt-4 inline-block">
                            <Button variant="cta" class="gap-2">
                                <Plus class="h-4 w-4" />
                                Create Note
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <div v-else-if="filteredNotes.length === 0" class="flex flex-col items-center justify-center py-12">
                <Search class="h-12 w-12 text-muted-foreground/50 mb-4" />
                <p class="font-semibold text-muted-foreground">No results found</p>
                <p class="text-sm text-muted-foreground max-w-full truncate px-4">No notes match "{{ searchQuery }}"</p>
                <Button variant="outline" size="sm" class="mt-3 gap-2" @click="searchQuery = ''">
                    <X class="h-3.5 w-3.5" />
                    Clear Search
                </Button>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link v-for="note in filteredNotes" :key="note.id" :href="`/projects/${project.id}/notes/${note.id}`" class="block">
                    <Card class="hover:border-primary transition-colors cursor-pointer flex flex-col h-full">
                        <CardHeader class="pb-2">
                            <div class="flex items-start justify-between">
                                <CardTitle class="text-base flex items-start gap-2">
                                    <StickyNote class="h-4 w-4 shrink-0 mt-0.5 text-yellow-500" />
                                    <span v-html="highlight(note.title || 'Untitled Note')" />
                                </CardTitle>
                                <Badge :variant="note.is_draft ? 'secondary' : 'default'">
                                    {{ note.is_draft ? 'Draft' : 'Published' }}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent class="flex flex-col flex-1">
                            <p v-if="note.content" class="text-sm text-muted-foreground mb-3" v-html="highlight(truncateContent(note.content))" />
                            <p v-else class="text-sm text-muted-foreground italic mb-3">
                                No content yet
                            </p>

                            <div v-if="note.documentation" class="flex items-center gap-1 text-xs text-muted-foreground mb-3">
                                <FileText class="h-3 w-3" />
                                <span>{{ note.documentation.title }}</span>
                            </div>

                            <div class="flex items-center justify-between mt-auto pt-3">
                                <span class="text-xs text-muted-foreground">
                                    {{ formatDate(note.updated_at) }}
                                </span>
                                <div class="flex gap-1">
                                    <RestrictedAction>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            class="p-0 text-destructive hover:text-destructive"
                                            @click.prevent.stop="confirmDelete(note)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </RestrictedAction>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <!-- Delete Confirmation Dialog -->
            <Dialog v-model:open="showDeleteConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Note?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete "{{ noteToDelete?.title || 'Untitled Note' }}"? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" @click="showDeleteConfirm = false" class="flex-1 sm:flex-none">
                            No
                        </Button>
                        <Button variant="destructive" @click="deleteNote" class="flex-1 sm:flex-none">
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
