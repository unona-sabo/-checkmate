<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import TranslateButtons from '@/components/TranslateButtons.vue';
import { StickyNote } from 'lucide-vue-next';

interface Documentation {
    id: number;
    title: string;
    category: string | null;
}

const props = defineProps<{
    project: Project;
    documentations: Documentation[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Notes', href: `/projects/${props.project.id}/notes` },
    { title: 'Create', href: `/projects/${props.project.id}/notes/create` },
];

const form = useForm({
    title: '',
    content: '',
    documentation_id: null as number | null,
    is_draft: true,
});

const submit = () => {
    form.post(`/projects/${props.project.id}/notes`);
};
</script>

<template>
    <Head title="Create Note" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-3xl w-full min-w-0">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <StickyNote class="h-5 w-5 text-yellow-500" />
                            Create Note
                        </CardTitle>
                        <CardDescription>
                            Create a new note or draft. You can optionally link it to a documentation.
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

                            <div class="space-y-2">
                                <Label>Link to Documentation</Label>
                                <Select v-model="form.documentation_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="None (standalone note)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="null">None (standalone note)</SelectItem>
                                        <SelectItem v-for="doc in documentations" :key="doc.id" :value="doc.id">
                                            {{ doc.title }}
                                            <span v-if="doc.category" class="text-muted-foreground ml-1">({{ doc.category }})</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="text-xs text-muted-foreground">
                                    Optionally link this note to a documentation for later publishing.
                                </p>
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
                                    rows="15"
                                    class="font-mono text-sm"
                                />
                                <InputError :message="form.errors.content" />
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Create Note
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/notes`)">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
