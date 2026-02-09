<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, FolderOpen, ClipboardList, TestTube, Play } from 'lucide-vue-next';

defineProps<{
    projects: Project[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
];
</script>

<template>
    <Head title="Projects" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Projects</h1>
                    <p class="text-muted-foreground">Manage your QA projects and test suites</p>
                </div>
                <Link href="/projects/create">
                    <Button variant="cta" class="gap-2">
                        <Plus class="h-4 w-4" />
                        New Project
                    </Button>
                </Link>
            </div>

            <div v-if="projects.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <FolderOpen class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No projects yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Get started by creating your first project.</p>
                    <Link href="/projects/create" class="mt-4 inline-block">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Create Project
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link v-for="project in projects" :key="project.id" :href="`/projects/${project.id}`">
                    <Card class="transition-all hover:border-primary hover:shadow-lg cursor-pointer h-full">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <FolderOpen class="h-5 w-5 text-primary" />
                                {{ project.name }}
                            </CardTitle>
                            <CardDescription>
                                Created {{ new Date(project.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex gap-4 text-sm text-muted-foreground">
                                <div class="flex items-center gap-1">
                                    <ClipboardList class="h-4 w-4" />
                                    {{ project.checklists_count || 0 }} Checklists
                                </div>
                                <div class="flex items-center gap-1">
                                    <TestTube class="h-4 w-4" />
                                    {{ project.test_suites_count || 0 }} Suites
                                </div>
                                <div class="flex items-center gap-1">
                                    <Play class="h-4 w-4" />
                                    {{ project.test_runs_count || 0 }} Runs
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
