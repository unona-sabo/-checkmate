<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    ClipboardList,
    Layers,
    Play,
    Plus,
    Edit,
    FolderOpen,
    ArrowRight,
    Bug,
    BookOpen
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

const getBugStatusColor = (status: string) => {
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
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-lg font-semibold">
                                <ClipboardList class="h-5 w-5 text-primary" />
                                Checklists
                                <span class="text-sm font-normal text-muted-foreground">({{ project.checklists?.length || 0 }})</span>
                            </CardTitle>
                            <Link :href="`/projects/${project.id}/checklists/create`">
                                <Button size="icon-sm" variant="ghost" class="p-0 cursor-pointer">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent class="p-5 pt-0 flex flex-col flex-1">
                        <div v-if="project.checklists?.length" class="space-y-1.5">
                            <Link
                                v-for="checklist in project.checklists.slice(0, 5)"
                                :key="checklist.id"
                                :href="`/projects/${project.id}/checklists/${checklist.id}`"
                                class="flex items-center justify-between rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50 cursor-pointer"
                            >
                                <span class="font-medium truncate">{{ checklist.name }}</span>
                                <ArrowRight class="h-4 w-4 text-muted-foreground shrink-0" />
                            </Link>
                        </div>
                        <div v-else class="py-3 text-center text-sm text-muted-foreground">
                            No checklists yet
                        </div>
                        <Link :href="`/projects/${project.id}/checklists`" class="mt-auto pt-3 block">
                            <Button variant="outline" size="sm" class="w-full text-sm cursor-pointer">View All</Button>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Test Suites Section -->
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-lg font-semibold">
                                <Layers class="h-5 w-5 text-primary" />
                                Test Suites
                                <span class="text-sm font-normal text-muted-foreground">({{ project.test_suites?.length || 0 }})</span>
                            </CardTitle>
                            <Link :href="`/projects/${project.id}/test-suites/create`">
                                <Button size="icon-sm" variant="ghost" class="p-0 cursor-pointer">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent class="p-5 pt-0 flex flex-col flex-1">
                        <div v-if="project.test_suites?.length" class="space-y-1.5">
                            <Link
                                v-for="suite in project.test_suites.slice(0, 5)"
                                :key="suite.id"
                                :href="`/projects/${project.id}/test-suites/${suite.id}`"
                                class="flex items-center justify-between rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50 cursor-pointer"
                            >
                                <span class="font-medium truncate">{{ suite.name }}</span>
                                <ArrowRight class="h-4 w-4 text-muted-foreground shrink-0" />
                            </Link>
                        </div>
                        <div v-else class="py-3 text-center text-sm text-muted-foreground">
                            No test suites yet
                        </div>
                        <Link :href="`/projects/${project.id}/test-suites`" class="mt-auto pt-3 block">
                            <Button variant="outline" size="sm" class="w-full text-sm cursor-pointer">View All</Button>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Test Runs Section -->
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-lg font-semibold">
                                <Play class="h-5 w-5 text-primary" />
                                Test Runs
                                <span class="text-sm font-normal text-muted-foreground">({{ project.test_runs?.length || 0 }})</span>
                            </CardTitle>
                            <Link :href="`/projects/${project.id}/test-runs/create`">
                                <Button size="icon-sm" variant="ghost" class="p-0 cursor-pointer">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent class="p-5 pt-0 flex flex-col flex-1">
                        <div v-if="project.test_runs?.length" class="space-y-1.5">
                            <Link
                                v-for="run in project.test_runs.slice(0, 5)"
                                :key="run.id"
                                :href="`/projects/${project.id}/test-runs/${run.id}`"
                                class="flex items-center justify-between rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50 cursor-pointer"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-medium truncate">{{ run.name }}</span>
                                    <Badge :class="getStatusColor(run.status)" variant="outline" class="text-xs px-1.5 py-0 h-5 shrink-0">
                                        {{ run.status }}
                                    </Badge>
                                    <span class="text-xs text-muted-foreground shrink-0">{{ run.progress }}%</span>
                                </div>
                                <ArrowRight class="h-4 w-4 text-muted-foreground shrink-0" />
                            </Link>
                        </div>
                        <div v-else class="py-3 text-center text-sm text-muted-foreground">
                            No test runs yet
                        </div>
                        <Link :href="`/projects/${project.id}/test-runs`" class="mt-auto pt-3 block">
                            <Button variant="outline" size="sm" class="w-full text-sm cursor-pointer">View All</Button>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Bug Reports Section -->
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-lg font-semibold">
                                <Bug class="h-5 w-5 text-primary" />
                                Bug Reports
                                <span class="text-sm font-normal text-muted-foreground">({{ project.bugreports?.length || 0 }})</span>
                            </CardTitle>
                            <Link :href="`/projects/${project.id}/bugreports/create`">
                                <Button size="icon-sm" variant="ghost" class="p-0 cursor-pointer">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent class="p-5 pt-0 flex flex-col flex-1">
                        <div v-if="project.bugreports?.length" class="space-y-1.5">
                            <Link
                                v-for="bug in project.bugreports.slice(0, 5)"
                                :key="bug.id"
                                :href="`/projects/${project.id}/bugreports/${bug.id}`"
                                class="flex items-center justify-between gap-6 rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50 cursor-pointer"
                            >
                                <span class="font-medium truncate min-w-0">{{ bug.title }}</span>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span :class="['px-1.5 py-0 rounded text-[10px] font-medium h-4 inline-flex items-center', getBugStatusColor(bug.status)]">
                                        {{ bug.status.replace('_', ' ') }}
                                    </span>
                                    <ArrowRight class="h-4 w-4 text-muted-foreground" />
                                </div>
                            </Link>
                        </div>
                        <div v-else class="py-3 text-center text-sm text-muted-foreground">
                            No bug reports yet
                        </div>
                        <Link :href="`/projects/${project.id}/bugreports`" class="mt-auto pt-3 block">
                            <Button variant="outline" size="sm" class="w-full text-sm cursor-pointer">View All</Button>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Documentations Section -->
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-lg font-semibold">
                                <BookOpen class="h-5 w-5 text-primary" />
                                Documentations
                                <span class="text-sm font-normal text-muted-foreground">({{ project.documentations?.length || 0 }})</span>
                            </CardTitle>
                            <Link :href="`/projects/${project.id}/documentations/create`">
                                <Button size="icon-sm" variant="ghost" class="p-0 cursor-pointer">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent class="p-5 pt-0 flex flex-col flex-1">
                        <div v-if="project.documentations?.length" class="space-y-1.5">
                            <Link
                                v-for="doc in project.documentations.slice(0, 5)"
                                :key="doc.id"
                                :href="`/projects/${project.id}/documentations/${doc.id}`"
                                class="flex items-center justify-between rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50 cursor-pointer"
                            >
                                <span class="font-medium truncate">{{ doc.title }}</span>
                                <ArrowRight class="h-4 w-4 text-muted-foreground shrink-0" />
                            </Link>
                        </div>
                        <div v-else class="py-3 text-center text-sm text-muted-foreground">
                            No documentations yet
                        </div>
                        <Link :href="`/projects/${project.id}/documentations`" class="mt-auto pt-3 block">
                            <Button variant="outline" size="sm" class="w-full text-sm cursor-pointer">View All</Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
