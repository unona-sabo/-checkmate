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
import { StickyNote, Plus, FileText, Edit, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

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
                <Link :href="`/projects/${project.id}/notes/create`">
                    <Button variant="cta" class="gap-2">
                        <Plus class="h-4 w-4" />
                        New Note
                    </Button>
                </Link>
            </div>

            <div v-if="notes.length === 0" class="flex flex-col items-center justify-center py-12">
                <StickyNote class="h-12 w-12 text-muted-foreground mb-4" />
                <h3 class="text-lg font-semibold">No notes yet</h3>
                <p class="mt-2 text-sm text-muted-foreground">Create your first note to get started.</p>
                <Link :href="`/projects/${project.id}/notes/create`" class="mt-4 inline-block">
                    <Button variant="cta" class="gap-2">
                        <Plus class="h-4 w-4" />
                        Create Note
                    </Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="note in notes" :key="note.id" class="hover:border-primary transition-colors cursor-pointer flex flex-col">
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between">
                            <Link :href="`/projects/${project.id}/notes/${note.id}`" class="flex-1">
                                <CardTitle class="text-base flex items-start gap-2 hover:text-primary transition-colors cursor-pointer">
                                    <StickyNote class="h-4 w-4 shrink-0 mt-0.5 text-yellow-500" />
                                    {{ note.title || 'Untitled Note' }}
                                </CardTitle>
                            </Link>
                            <Badge :variant="note.is_draft ? 'secondary' : 'default'">
                                {{ note.is_draft ? 'Draft' : 'Published' }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col flex-1">
                        <p v-if="note.content" class="text-sm text-muted-foreground mb-3">
                            {{ truncateContent(note.content) }}
                        </p>
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
                                <Link :href="`/projects/${project.id}/notes/${note.id}`">
                                    <Button variant="ghost" size="icon-sm" class="p-0">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                </Link>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="p-0 text-destructive hover:text-destructive"
                                    @click.prevent="confirmDelete(note)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
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
