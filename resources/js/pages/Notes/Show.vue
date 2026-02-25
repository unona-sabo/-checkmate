<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import InputError from '@/components/InputError.vue';
import { useClearErrorsOnInput } from '@/composables/useClearErrorsOnInput';
import TranslateButtons from '@/components/TranslateButtons.vue';
import { StickyNote, Save, Trash2, Upload, FileText } from 'lucide-vue-next';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { computed, ref, watch } from 'vue';

interface Documentation {
    id: number;
    title: string;
    category: string | null;
}

interface Note {
    id: number;
    title: string | null;
    content: string | null;
    is_draft: boolean;
    documentation_id: number | null;
    documentation: Documentation | null;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    project: Project;
    note: Note;
    documentations: Documentation[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Notes', href: `/projects/${props.project.id}/notes` },
    { title: props.note.title || 'Untitled Note', href: `/projects/${props.project.id}/notes/${props.note.id}` },
];

const form = useForm({
    title: props.note.title || '',
    content: props.note.content || '',
    documentation_id: props.note.documentation_id,
    is_draft: props.note.is_draft,
});
useClearErrorsOnInput(form);

// Track if form has changes
const initialState = {
    title: props.note.title || '',
    content: props.note.content || '',
    documentation_id: props.note.documentation_id,
};

const hasChanges = computed(() => {
    return form.title !== initialState.title ||
           form.content !== initialState.content ||
           form.documentation_id !== initialState.documentation_id;
});

const submit = () => {
    form.put(`/projects/${props.project.id}/notes/${props.note.id}`);
};

const publish = () => {
    if (!form.documentation_id) {
        alert('Please select a documentation to publish to.');
        return;
    }
    // First save changes, then publish
    form.post(`/projects/${props.project.id}/notes/${props.note.id}/publish`);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="note.title || 'Untitled Note'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="flex items-start gap-2 text-2xl font-bold tracking-tight">
                    <StickyNote class="h-6 w-6 shrink-0 mt-1 text-yellow-500" />
                    {{ note.title || 'Untitled Note' }}
                    <Badge :variant="note.is_draft ? 'secondary' : 'default'" class="ml-2">
                        {{ note.is_draft ? 'Draft' : 'Published' }}
                    </Badge>
                </h1>
                <div class="flex gap-2">
                    <RestrictedAction>
                        <Link
                            :href="`/projects/${project.id}/notes/${note.id}`"
                            method="delete"
                            as="button"
                        >
                            <Button variant="destructive" class="gap-2">
                                <Trash2 class="h-4 w-4" />
                                Delete
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3 min-w-0">
                <!-- Main editor -->
                <div class="lg:col-span-2 min-w-0">
                    <Card>
                        <CardHeader>
                            <CardTitle>Edit Note</CardTitle>
                            <CardDescription>
                                Last updated: {{ formatDate(note.updated_at) }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="submit" class="space-y-6 min-w-0">
                                <div class="space-y-2">
                                    <Label for="title">Title</Label>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        type="text"
                                        placeholder="Note title (optional)"
                                        :class="{ 'border-destructive': form.errors.title }"
                                    />
                                    <InputError :message="form.errors.title" />
                                </div>

                                <div class="space-y-2 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <Label for="content">Content</Label>
                                        <TranslateButtons :project-id="project.id" :text="form.content" @translated="form.content = $event" />
                                    </div>
                                    <Textarea
                                        id="content"
                                        v-model="form.content"
                                        placeholder="Write your note here..."
                                        rows="20"
                                        class="font-mono text-sm"
                                    />
                                    <InputError :message="form.errors.content" />
                                </div>

                                <div class="flex gap-2">
                                    <RestrictedAction>
                                        <Button type="submit" :disabled="form.processing || !hasChanges" class="gap-2">
                                            <Save class="h-4 w-4" />
                                            Save Changes
                                        </Button>
                                    </RestrictedAction>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar with publish options -->
                <div class="lg:col-span-1 space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Link to Documentation</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <Select v-model="form.documentation_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="None (standalone note)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">None (standalone note)</SelectItem>
                                    <SelectItem v-for="doc in documentations" :key="doc.id" :value="doc.id">
                                        {{ doc.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <div v-if="form.documentation_id" class="pt-2">
                                <RestrictedAction>
                                    <Button
                                        @click="publish"
                                        :disabled="form.processing || !note.is_draft"
                                        class="w-full gap-2"
                                        :variant="note.is_draft ? 'default' : 'secondary'"
                                    >
                                        <Upload class="h-4 w-4" />
                                        {{ note.is_draft ? 'Publish to Documentation' : 'Already Published' }}
                                    </Button>
                                </RestrictedAction>
                                <p class="text-xs text-muted-foreground mt-2">
                                    Publishing will append this note's content to the selected documentation.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="note.documentation">
                        <CardHeader>
                            <CardTitle class="text-base flex items-center gap-2">
                                <FileText class="h-4 w-4 text-primary" />
                                Linked Documentation
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Link
                                :href="`/projects/${project.id}/documentations/${note.documentation.id}`"
                                class="text-sm text-primary hover:underline cursor-pointer"
                            >
                                {{ note.documentation.title }}
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
