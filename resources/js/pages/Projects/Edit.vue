<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Edit, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    project: Project;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Edit', href: `/projects/${props.project.id}/edit` },
];

const form = useForm({
    name: props.project.name,
});

const showDeleteDialog = ref(false);

const submit = () => {
    form.put(`/projects/${props.project.id}`);
};

const deleteProject = () => {
    router.delete(`/projects/${props.project.id}`);
};
</script>

<template>
    <Head title="Edit Project" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Edit class="h-5 w-5 text-primary" />
                            Edit Project
                        </CardTitle>
                        <CardDescription>
                            Update your project details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="name">Project Name</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Enter project name"
                                    :class="{ 'border-destructive': form.errors.name }"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Save Changes
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}`)">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card class="border-destructive/50">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-destructive">
                            <Trash2 class="h-5 w-5" />
                            Danger Zone
                        </CardTitle>
                        <CardDescription>
                            Permanently delete this project and all its data.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Dialog v-model:open="showDeleteDialog">
                            <DialogTrigger as-child>
                                <Button variant="destructive">Delete Project</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Are you absolutely sure?</DialogTitle>
                                    <DialogDescription>
                                        This action cannot be undone. This will permanently delete the project
                                        "{{ project.name }}" and all of its checklists, test suites, test cases, and test runs.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                                    <Button variant="destructive" @click="deleteProject">Delete Project</Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
