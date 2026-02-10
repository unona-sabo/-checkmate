<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { FileText, Edit, Trash2, ChevronRight } from 'lucide-vue-next';

interface Documentation {
    id: number;
    title: string;
    content: string | null;
    category: string | null;
    order: number;
    children?: Documentation[];
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    project: Project;
    documentation: Documentation;
    allDocs: Documentation[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Documentations', href: `/projects/${props.project.id}/documentations` },
    { title: props.documentation.title, href: `/projects/${props.project.id}/documentations/${props.documentation.id}` },
];
</script>

<template>
    <Head :title="documentation.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                    <FileText class="h-6 w-6 text-primary" />
                    {{ documentation.title }}
                </h1>
                <div class="flex gap-2">
                    <Link :href="`/projects/${project.id}/documentations/${documentation.id}/edit`">
                        <Button variant="outline" class="gap-2">
                            <Edit class="h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                    <Link
                        :href="`/projects/${project.id}/documentations/${documentation.id}`"
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

            <div class="grid gap-6 lg:grid-cols-4">
                <!-- Sidebar with navigation -->
                <div class="lg:col-span-1">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm">Navigation</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-1">
                            <template v-for="doc in allDocs" :key="doc.id">
                                <Link
                                    :href="`/projects/${project.id}/documentations/${doc.id}`"
                                    :class="[
                                        'flex items-center gap-2 px-2 py-1.5 rounded text-sm transition-colors',
                                        doc.id === documentation.id
                                            ? 'bg-primary text-primary-foreground'
                                            : 'hover:bg-muted'
                                    ]"
                                >
                                    <FileText class="h-4 w-4" />
                                    {{ doc.title }}
                                </Link>
                                <template v-if="doc.children && doc.children.length > 0">
                                    <Link
                                        v-for="child in doc.children"
                                        :key="child.id"
                                        :href="`/projects/${project.id}/documentations/${child.id}`"
                                        :class="[
                                            'flex items-center gap-2 px-2 py-1.5 pl-6 rounded text-sm transition-colors',
                                            child.id === documentation.id
                                                ? 'bg-primary text-primary-foreground'
                                                : 'hover:bg-muted'
                                        ]"
                                    >
                                        <ChevronRight class="h-3 w-3" />
                                        {{ child.title }}
                                    </Link>
                                </template>
                            </template>
                        </CardContent>
                    </Card>
                </div>

                <!-- Main content -->
                <div class="lg:col-span-3">
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle>{{ documentation.title }}</CardTitle>
                                <span v-if="documentation.category" class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ documentation.category }}
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="documentation.content" class="prose prose-sm max-w-none whitespace-pre-wrap">
                                {{ documentation.content }}
                            </div>
                            <p v-else class="text-muted-foreground italic">No content yet.</p>
                        </CardContent>
                    </Card>

                    <!-- Child documents -->
                    <div v-if="documentation.children && documentation.children.length > 0" class="mt-6">
                        <h3 class="text-lg font-semibold mb-4">Related Documents</h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <Card v-for="child in documentation.children" :key="child.id" class="hover:shadow-md transition-shadow">
                                <CardHeader class="pb-2">
                                    <Link :href="`/projects/${project.id}/documentations/${child.id}`" class="hover:underline">
                                        <CardTitle class="text-base flex items-center gap-2">
                                            <FileText class="h-4 w-4 text-primary" />
                                            {{ child.title }}
                                        </CardTitle>
                                    </Link>
                                </CardHeader>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
