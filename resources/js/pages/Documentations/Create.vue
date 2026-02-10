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
});

const submit = () => {
    form.post(`/projects/${props.project.id}/documentations`);
};
</script>

<template>
    <Head title="Create Documentation" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-3xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5 text-primary" />
                            Create Documentation
                        </CardTitle>
                        <CardDescription>
                            Add new documentation or technical specification.
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
                                <Label for="content">Content</Label>
                                <Textarea
                                    id="content"
                                    v-model="form.content"
                                    placeholder="Write your documentation here..."
                                    rows="15"
                                    class="font-mono text-sm"
                                />
                                <InputError :message="form.errors.content" />
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
