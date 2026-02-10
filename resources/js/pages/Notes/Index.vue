<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { StickyNote, Plus, FileText, Edit, Trash2 } from 'lucide-vue-next';

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
</script>

<template>
    <Head title="Notes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                    <StickyNote class="h-6 w-6 text-primary" />
                    Notes
                </h1>
                <Link :href="`/projects/${project.id}/notes/create`">
                    <Button class="gap-2">
                        <Plus class="h-4 w-4" />
                        New Note
                    </Button>
                </Link>
            </div>

            <div v-if="notes.length === 0" class="flex flex-col items-center justify-center py-12">
                <StickyNote class="h-12 w-12 text-muted-foreground mb-4" />
                <h3 class="text-lg font-medium text-muted-foreground">No notes yet</h3>
                <p class="text-sm text-muted-foreground mt-1">Create your first note to get started.</p>
                <Link :href="`/projects/${project.id}/notes/create`" class="mt-4">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        Create Note
                    </Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="note in notes" :key="note.id" class="hover:shadow-md transition-shadow">
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between">
                            <Link :href="`/projects/${project.id}/notes/${note.id}`" class="flex-1">
                                <CardTitle class="text-base flex items-center gap-2 hover:text-primary transition-colors">
                                    <StickyNote class="h-4 w-4 text-yellow-500" />
                                    {{ note.title || 'Untitled Note' }}
                                </CardTitle>
                            </Link>
                            <Badge :variant="note.is_draft ? 'secondary' : 'default'">
                                {{ note.is_draft ? 'Draft' : 'Published' }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
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

                        <div class="flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">
                                {{ formatDate(note.updated_at) }}
                            </span>
                            <div class="flex gap-1">
                                <Link :href="`/projects/${project.id}/notes/${note.id}`">
                                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                </Link>
                                <Link
                                    :href="`/projects/${project.id}/notes/${note.id}`"
                                    method="delete"
                                    as="button"
                                >
                                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0 text-destructive hover:text-destructive">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
