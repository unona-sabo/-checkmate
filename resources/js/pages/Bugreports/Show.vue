<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Bug, Edit, Trash2 } from 'lucide-vue-next';

interface Bugreport {
    id: number;
    title: string;
    description: string | null;
    steps_to_reproduce: string | null;
    expected_result: string | null;
    actual_result: string | null;
    severity: 'critical' | 'major' | 'minor' | 'trivial';
    priority: 'high' | 'medium' | 'low';
    status: 'new' | 'open' | 'in_progress' | 'resolved' | 'closed' | 'reopened';
    environment: string | null;
    reporter: { id: number; name: string } | null;
    assignee: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    project: Project;
    bugreport: Bugreport;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Bugreports', href: `/projects/${props.project.id}/bugreports` },
    { title: props.bugreport.title, href: `/projects/${props.project.id}/bugreports/${props.bugreport.id}` },
];

const getSeverityColor = (severity: string) => {
    switch (severity) {
        case 'critical': return 'bg-red-100 text-red-800';
        case 'major': return 'bg-orange-100 text-orange-800';
        case 'minor': return 'bg-yellow-100 text-yellow-800';
        case 'trivial': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'new': return 'bg-blue-100 text-blue-800';
        case 'open': return 'bg-purple-100 text-purple-800';
        case 'in_progress': return 'bg-yellow-100 text-yellow-800';
        case 'resolved': return 'bg-green-100 text-green-800';
        case 'closed': return 'bg-gray-100 text-gray-800';
        case 'reopened': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'high': return 'bg-red-100 text-red-800';
        case 'medium': return 'bg-yellow-100 text-yellow-800';
        case 'low': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head :title="bugreport.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                    <Bug class="h-6 w-6 text-primary" />
                    {{ bugreport.title }}
                </h1>
                <div class="flex gap-2">
                    <Link :href="`/projects/${project.id}/bugreports/${bugreport.id}/edit`">
                        <Button variant="outline" class="gap-2">
                            <Edit class="h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                    <Link
                        :href="`/projects/${project.id}/bugreports/${bugreport.id}`"
                        method="delete"
                        as="button"
                    >
                        <Button variant="destructive" class="gap-2">
                            <Trash2 class="h-4 w-4" />
                            Delete
                        </Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Description</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="whitespace-pre-wrap">{{ bugreport.description || 'No description provided.' }}</p>
                        </CardContent>
                    </Card>

                    <Card v-if="bugreport.steps_to_reproduce">
                        <CardHeader>
                            <CardTitle>Steps to Reproduce</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="whitespace-pre-wrap">{{ bugreport.steps_to_reproduce }}</p>
                        </CardContent>
                    </Card>

                    <div class="grid gap-6 md:grid-cols-2">
                        <Card v-if="bugreport.expected_result">
                            <CardHeader>
                                <CardTitle>Expected Result</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="whitespace-pre-wrap">{{ bugreport.expected_result }}</p>
                            </CardContent>
                        </Card>

                        <Card v-if="bugreport.actual_result">
                            <CardHeader>
                                <CardTitle>Actual Result</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="whitespace-pre-wrap">{{ bugreport.actual_result }}</p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <p class="text-sm text-muted-foreground">Status</p>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getStatusColor(bugreport.status)]">
                                    {{ bugreport.status.replace('_', ' ') }}
                                </span>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Severity</p>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getSeverityColor(bugreport.severity)]">
                                    {{ bugreport.severity }}
                                </span>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Priority</p>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getPriorityColor(bugreport.priority)]">
                                    {{ bugreport.priority }}
                                </span>
                            </div>
                            <div v-if="bugreport.environment">
                                <p class="text-sm text-muted-foreground">Environment</p>
                                <p class="font-medium">{{ bugreport.environment }}</p>
                            </div>
                            <div v-if="bugreport.reporter">
                                <p class="text-sm text-muted-foreground">Reported by</p>
                                <p class="font-medium">{{ bugreport.reporter.name }}</p>
                            </div>
                            <div v-if="bugreport.assignee">
                                <p class="text-sm text-muted-foreground">Assigned to</p>
                                <p class="font-medium">{{ bugreport.assignee.name }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
