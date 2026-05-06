<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { FileText, Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClearErrorsOnInput } from '@/composables/useClearErrorsOnInput';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';

interface ParentOption {
    id: number;
    title: string;
}

const props = defineProps<{
    project: Project;
    parentOptions: ParentOption[];
    defaultParentId?: number | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    {
        title: 'Documentations',
        href: `/projects/${props.project.id}/documentations`,
    },
    {
        title: 'Create',
        href: `/projects/${props.project.id}/documentations/create`,
    },
];

const form = useForm({
    title: '',
    content: '',
    category: '',
    parent_id: props.defaultParentId ?? (null as number | null),
    attachments: [] as File[],
});
useClearErrorsOnInput(form);

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
                            Add new documentation or technical specification.
                            Paste screenshots directly into the editor.
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
                                    :class="{
                                        'border-destructive': form.errors.title,
                                    }"
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
                                    <InputError
                                        :message="form.errors.category"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label>Parent Document</Label>
                                    <Select v-model="form.parent_id">
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="None (top level)"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="null"
                                                >None (top level)</SelectItem
                                            >
                                            <SelectItem
                                                v-for="opt in parentOptions"
                                                :key="opt.id"
                                                :value="opt.id"
                                            >
                                                {{ opt.title }}
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
                                <div
                                    class="rounded-md border border-dashed border-input p-4"
                                >
                                    <label
                                        class="flex cursor-pointer flex-col items-center gap-2 text-muted-foreground transition-colors hover:text-foreground"
                                    >
                                        <Upload class="h-8 w-8" />
                                        <span class="text-sm"
                                            >Click to upload files</span
                                        >
                                        <span class="text-xs"
                                            >(Max 10MB per file)</span
                                        >
                                        <input
                                            type="file"
                                            multiple
                                            class="hidden"
                                            @change="onFilesSelected"
                                        />
                                    </label>
                                </div>
                                <div
                                    v-if="form.attachments.length > 0"
                                    class="mt-2 space-y-2"
                                >
                                    <div
                                        v-for="(
                                            file, index
                                        ) in form.attachments"
                                        :key="index"
                                        class="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-2"
                                        >
                                            <FileText
                                                class="h-4 w-4 shrink-0 text-muted-foreground"
                                            />
                                            <span class="truncate text-sm">{{
                                                file.name
                                            }}</span>
                                            <span
                                                class="shrink-0 text-xs text-muted-foreground"
                                                >{{
                                                    formatFileSize(file.size)
                                                }}</span
                                            >
                                        </div>
                                        <button
                                            type="button"
                                            @click="removeFile(index)"
                                            class="cursor-pointer text-muted-foreground hover:text-destructive"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                >
                                    Create Documentation
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="
                                        $inertia.visit(
                                            `/projects/${project.id}/documentations`,
                                        )
                                    "
                                >
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
