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
import { FileText } from 'lucide-vue-next';

interface ParentOption {
    id: number;
    title: string;
}

interface Documentation {
    id: number;
    title: string;
    content: string | null;
    category: string | null;
    parent_id: number | null;
}

const props = defineProps<{
    project: Project;
    documentation: Documentation;
    parentOptions: ParentOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Documentations', href: `/projects/${props.project.id}/documentations` },
    { title: props.documentation.title, href: `/projects/${props.project.id}/documentations/${props.documentation.id}` },
    { title: 'Edit', href: `/projects/${props.project.id}/documentations/${props.documentation.id}/edit` },
];

const form = useForm({
    title: props.documentation.title,
    content: props.documentation.content || '',
    category: props.documentation.category || '',
    parent_id: props.documentation.parent_id,
});

const submit = () => {
    form.put(`/projects/${props.project.id}/documentations/${props.documentation.id}`);
};
</script>

<template>
    <Head title="Edit Documentation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-3xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5 text-primary" />
                            Edit Documentation
                        </CardTitle>
                        <CardDescription>
                            Update the documentation content.
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
                                <Label for="content">Content</Label>
                                <Textarea
                                    id="content"
                                    v-model="form.content"
                                    rows="15"
                                    class="font-mono text-sm"
                                />
                                <InputError :message="form.errors.content" />
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Update Documentation
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/documentations/${documentation.id}`)">
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
