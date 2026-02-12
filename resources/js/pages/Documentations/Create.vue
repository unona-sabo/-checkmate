<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { FileText, Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface ParentOption {
    id: number;
    title: string;
}

const props = defineProps<{
    project: Project;
    parentOptions: ParentOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Documentations', href: `/projects/${props.project.id}/documentations` },
    { title: 'Create', href: `/projects/${props.project.id}/documentations/create` },
];

const form = useForm({
    title: '',
    content: '',
    category: '',
    parent_id: null as number | null,
    attachments: [] as File[],
});

const imageUploadUrl = `/projects/${props.project.id}/documentations/upload-image`;

const onFilesSelected = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files) {
        form.attachments.push(...Array.from(input.files));
        input.value = '';
    }
};

const removeFile = (index: number) => {
    form.attachments.splice(index, 1);
};

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const submit = () => {
    form.post(`/projects/${props.project.id}/documentations`, {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Create Documentation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-4xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5 text-primary" />
                            Create Documentation
                        </CardTitle>
                        <CardDescription>
                            Add new documentation or technical specification. Paste screenshots directly into the editor.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="space-y-2">
                                <Label for="title">Title *</Label>
                                <Input
                                    id="title"
                                    v-model="form.title"
                                    type="text"
                                    placeholder="Documentation title"
                                    :class="{ 'border-destructive': form.errors.title }"
                                />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="category">Category</Label>
                                    <Input
                                        id="category"
                                        v-model="form.category"
                                        type="text"
                                        placeholder="e.g., API, Frontend, Database"
                                    />
                                    <InputError :message="form.errors.category" />
                                </div>

                                <div class="space-y-2">
                                    <Label>Parent Document</Label>
                                    <Select v-model="form.parent_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="None (top level)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="null">None (top level)</SelectItem>
                                            <SelectItem v-for="parent in parentOptions" :key="parent.id" :value="parent.id">
                                                {{ parent.title }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>Content</Label>
                                <RichTextEditor
                                    v-model="form.content"
                                    :upload-url="imageUploadUrl"
                                    placeholder="Write your documentation here... Paste screenshots with Ctrl+V"
                                />
                                <InputError :message="form.errors.content" />
                            </div>

                            <!-- File Attachments -->
                            <div class="space-y-2">
                                <Label>Attachments</Label>
                                <div class="border border-dashed border-input rounded-md p-4">
                                    <label class="flex flex-col items-center gap-2 cursor-pointer text-muted-foreground hover:text-foreground transition-colors">
                                        <Upload class="h-8 w-8" />
                                        <span class="text-sm">Click to upload files</span>
                                        <span class="text-xs">(Max 10MB per file)</span>
                                        <input
                                            type="file"
                                            multiple
                                            class="hidden"
                                            @change="onFilesSelected"
                                        />
                                    </label>
                                </div>
                                <div v-if="form.attachments.length > 0" class="space-y-2 mt-2">
                                    <div
                                        v-for="(file, index) in form.attachments"
                                        :key="index"
                                        class="flex items-center justify-between bg-muted/50 rounded-md px-3 py-2"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <FileText class="h-4 w-4 text-muted-foreground shrink-0" />
                                            <span class="text-sm truncate">{{ file.name }}</span>
                                            <span class="text-xs text-muted-foreground shrink-0">{{ formatFileSize(file.size) }}</span>
                                        </div>
                                        <button type="button" @click="removeFile(index)" class="text-muted-foreground hover:text-destructive cursor-pointer">
                                            <X class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Create Documentation
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/documentations`)">
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
