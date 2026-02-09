<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    ClipboardList,
    TestTube,
    Play,
    Plus,
    Edit,
    FolderOpen,
    ArrowRight
} from 'lucide-vue-next';

const props = defineProps<{
    project: Project;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
];

const getStatusColor = (status: string) => {
    switch (status) {
        case 'active': return 'bg-green-500/10 text-green-500 border-green-500/20';
        case 'completed': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        case 'archived': return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        default: return '';
    }
};
</script>

<template>
    <Head :title="project.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <FolderOpen class="h-6 w-6 text-primary" />
                        {{ project.name }}
                    </h1>
                    <p class="text-muted-foreground">
                        Created {{ new Date(project.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                    </p>
                </div>
                <Link :href="`/projects/${project.id}/edit`">
                    <Button variant="outline" class="gap-2">
                        <Edit class="h-4 w-4" />
                        Edit Project
                    </Button>
                </Link>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <!-- Checklists Section -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <ClipboardList class="h-5 w-5 text-primary" />
                                Checklists
                            </CardTitle>
                            <Link :href="`/projects/${project.id}/checklists/create`">
                                <Button size="sm" variant="ghost" class="h-8 w-8 p-0">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                        <CardDescription>
                            {{ project.checklists?.length || 0 }} checklists
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="project.checklists?.length" class="space-y-2">
                            <Link
                                v-for="checklist in project.checklists"
                                :key="checklist.id"
                                :href="`/projects/${project.id}/checklists/${checklist.id}`"
                                class="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted/50"
                            >
                                <span class="font-medium">{{ checklist.name }}</span>
                                <ArrowRight class="h-4 w-4 text-muted-foreground" />
                            </Link>
                        </div>
                        <div v-else class="py-4 text-center text-sm text-muted-foreground">
                            No checklists yet
                        </div>
                        <Link :href="`/projects/${project.id}/checklists`" class="mt-4 block">
                            <Button variant="outline" class="w-full">View All Checklists</Button>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Test Suites Section -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <TestTube class="h-5 w-5 text-primary" />
                                Test Suites
                            </CardTitle>
                            <Link :href="`/projects/${project.id}/test-suites/create`">
                                <Button size="sm" variant="ghost" class="h-8 w-8 p-0">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                        <CardDescription>
                            {{ project.test_suites?.length || 0 }} test suites
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="project.test_suites?.length" class="space-y-2">
                            <Link
                                v-for="suite in project.test_suites"
                                :key="suite.id"
                                :href="`/projects/${project.id}/test-suites/${suite.id}`"
                                class="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted/50"
                            >
                                <span class="font-medium">{{ suite.name }}</span>
                                <ArrowRight class="h-4 w-4 text-muted-foreground" />
                            </Link>
                        </div>
                        <div v-else class="py-4 text-center text-sm text-muted-foreground">
                            No test suites yet
                        </div>
                        <Link :href="`/projects/${project.id}/test-suites`" class="mt-4 block">
                            <Button variant="outline" class="w-full">View All Test Suites</Button>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Test Runs Section -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <Play class="h-5 w-5 text-primary" />
                                Test Runs
                            </CardTitle>
                            <Link :href="`/projects/${project.id}/test-runs/create`">
                                <Button size="sm" variant="ghost" class="h-8 w-8 p-0">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                        <CardDescription>
                            Recent test runs
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="project.test_runs?.length" class="space-y-2">
                            <Link
                                v-for="run in project.test_runs"
                                :key="run.id"
                                :href="`/projects/${project.id}/test-runs/${run.id}`"
                                class="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-muted/50"
                            >
                                <div>
                                    <span class="font-medium">{{ run.name }}</span>
                                    <div class="flex items-center gap-2 mt-1">
                                        <Badge :class="getStatusColor(run.status)" variant="outline">
                                            {{ run.status }}
                                        </Badge>
                                        <span class="text-xs text-muted-foreground">{{ run.progress }}%</span>
                                    </div>
                                </div>
                                <ArrowRight class="h-4 w-4 text-muted-foreground" />
                            </Link>
                        </div>
                        <div v-else class="py-4 text-center text-sm text-muted-foreground">
                            No test runs yet
                        </div>
                        <Link :href="`/projects/${project.id}/test-runs`" class="mt-4 block">
                            <Button variant="outline" class="w-full">View All Test Runs</Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
