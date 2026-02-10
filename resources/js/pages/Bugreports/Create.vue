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
import { Bug } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
}

const props = defineProps<{
    project: Project;
    users: User[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Bugreports', href: `/projects/${props.project.id}/bugreports` },
    { title: 'Create', href: `/projects/${props.project.id}/bugreports/create` },
];

const form = useForm({
    title: '',
    description: '',
    steps_to_reproduce: '',
    expected_result: '',
    actual_result: '',
    severity: 'minor',
    priority: 'medium',
    status: 'new',
    environment: '',
    assigned_to: null as number | null,
});

const submit = () => {
    form.post(`/projects/${props.project.id}/bugreports`);
};
</script>

<template>
    <Head title="Report Bug" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Bug class="h-5 w-5 text-primary" />
                            Report Bug
                        </CardTitle>
                        <CardDescription>
                            Create a new bug report for this project.
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
                                    placeholder="Brief description of the bug"
                                    :class="{ 'border-destructive': form.errors.title }"
                                />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Detailed description of the bug"
                                    rows="3"
                                />
                                <InputError :message="form.errors.description" />
                            </div>

                            <div class="space-y-2">
                                <Label for="steps_to_reproduce">Steps to Reproduce</Label>
                                <Textarea
                                    id="steps_to_reproduce"
                                    v-model="form.steps_to_reproduce"
                                    placeholder="1. Go to...&#10;2. Click on...&#10;3. See error"
                                    rows="4"
                                />
                                <InputError :message="form.errors.steps_to_reproduce" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="expected_result">Expected Result</Label>
                                    <Textarea
                                        id="expected_result"
                                        v-model="form.expected_result"
                                        placeholder="What should happen"
                                        rows="2"
                                    />
                                    <InputError :message="form.errors.expected_result" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="actual_result">Actual Result</Label>
                                    <Textarea
                                        id="actual_result"
                                        v-model="form.actual_result"
                                        placeholder="What actually happens"
                                        rows="2"
                                    />
                                    <InputError :message="form.errors.actual_result" />
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div class="space-y-2">
                                    <Label>Severity</Label>
                                    <Select v-model="form.severity">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="critical">Critical</SelectItem>
                                            <SelectItem value="major">Major</SelectItem>
                                            <SelectItem value="minor">Minor</SelectItem>
                                            <SelectItem value="trivial">Trivial</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label>Priority</Label>
                                    <Select v-model="form.priority">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="high">High</SelectItem>
                                            <SelectItem value="medium">Medium</SelectItem>
                                            <SelectItem value="low">Low</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label>Status</Label>
                                    <Select v-model="form.status">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="new">New</SelectItem>
                                            <SelectItem value="open">Open</SelectItem>
                                            <SelectItem value="in_progress">In Progress</SelectItem>
                                            <SelectItem value="resolved">Resolved</SelectItem>
                                            <SelectItem value="closed">Closed</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="environment">Environment</Label>
                                <Input
                                    id="environment"
                                    v-model="form.environment"
                                    type="text"
                                    placeholder="e.g., Chrome 120, Windows 11, Production"
                                />
                                <InputError :message="form.errors.environment" />
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Create Bug Report
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/bugreports`)">
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
