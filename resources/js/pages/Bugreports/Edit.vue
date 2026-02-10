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

interface Bugreport {
    id: number;
    title: string;
    description: string | null;
    steps_to_reproduce: string | null;
    expected_result: string | null;
    actual_result: string | null;
    severity: string;
    priority: string;
    status: string;
    environment: string | null;
    assigned_to: number | null;
}

const props = defineProps<{
    project: Project;
    bugreport: Bugreport;
    users: User[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Bugreports', href: `/projects/${props.project.id}/bugreports` },
    { title: props.bugreport.title, href: `/projects/${props.project.id}/bugreports/${props.bugreport.id}` },
    { title: 'Edit', href: `/projects/${props.project.id}/bugreports/${props.bugreport.id}/edit` },
];

const form = useForm({
    title: props.bugreport.title,
    description: props.bugreport.description || '',
    steps_to_reproduce: props.bugreport.steps_to_reproduce || '',
    expected_result: props.bugreport.expected_result || '',
    actual_result: props.bugreport.actual_result || '',
    severity: props.bugreport.severity,
    priority: props.bugreport.priority,
    status: props.bugreport.status,
    environment: props.bugreport.environment || '',
    assigned_to: props.bugreport.assigned_to,
});

const submit = () => {
    form.put(`/projects/${props.project.id}/bugreports/${props.bugreport.id}`);
};
</script>

<template>
    <Head title="Edit Bug Report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Bug class="h-5 w-5 text-primary" />
                            Edit Bug Report
                        </CardTitle>
                        <CardDescription>
                            Update the bug report details.
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

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="3"
                                />
                                <InputError :message="form.errors.description" />
                            </div>

                            <div class="space-y-2">
                                <Label for="steps_to_reproduce">Steps to Reproduce</Label>
                                <Textarea
                                    id="steps_to_reproduce"
                                    v-model="form.steps_to_reproduce"
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
                                        rows="2"
                                    />
                                    <InputError :message="form.errors.expected_result" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="actual_result">Actual Result</Label>
                                    <Textarea
                                        id="actual_result"
                                        v-model="form.actual_result"
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
                                            <SelectItem value="reopened">Reopened</SelectItem>
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
                                />
                                <InputError :message="form.errors.environment" />
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Update Bug Report
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/bugreports/${bugreport.id}`)">
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
