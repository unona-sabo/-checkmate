<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Bug, Plus, Search, X } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { ref, computed } from 'vue';

interface Bugreport {
    id: number;
    title: string;
    description: string | null;
    severity: 'critical' | 'major' | 'minor' | 'trivial';
    priority: 'high' | 'medium' | 'low';
    status: 'new' | 'open' | 'in_progress' | 'resolved' | 'closed' | 'reopened';
    reporter: { id: number; name: string } | null;
    assignee: { id: number; name: string } | null;
    created_at: string;
}

const props = defineProps<{
    project: Project;
    bugreports: Bugreport[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Bugreports', href: `/projects/${props.project.id}/bugreports` },
];

const searchQuery = ref('');

const filteredBugreports = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.bugreports;
    }
    const query = searchQuery.value.toLowerCase();
    return props.bugreports.filter(bug =>
        bug.title.toLowerCase().includes(query) ||
        bug.description?.toLowerCase().includes(query)
    );
});

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
</script>

<template>
    <Head title="Bugreports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Bug class="h-6 w-6 text-primary" />
                        Bugreports
                    </h1>
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search bugreports..."
                            class="pl-9 pr-8 w-56"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <Link :href="`/projects/${project.id}/bugreports/create`">
                    <Button class="gap-2">
                        <Plus class="h-4 w-4" />
                        Report Bug
                    </Button>
                </Link>
            </div>

            <div v-if="filteredBugreports.length === 0" class="flex flex-col items-center justify-center py-12">
                <Bug class="h-12 w-12 text-muted-foreground/50 mb-4" />
                <p class="text-muted-foreground">No bugreports found.</p>
            </div>

            <div v-else class="grid gap-4">
                <Card v-for="bug in filteredBugreports" :key="bug.id" class="hover:shadow-md transition-shadow">
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between">
                            <Link :href="`/projects/${project.id}/bugreports/${bug.id}`" class="hover:underline">
                                <CardTitle class="text-lg">{{ bug.title }}</CardTitle>
                            </Link>
                            <div class="flex gap-2">
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getSeverityColor(bug.severity)]">
                                    {{ bug.severity }}
                                </span>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getStatusColor(bug.status)]">
                                    {{ bug.status.replace('_', ' ') }}
                                </span>
                            </div>
                        </div>
                        <CardDescription v-if="bug.description" class="line-clamp-2">
                            {{ bug.description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-4 text-sm text-muted-foreground">
                            <span v-if="bug.reporter">Reported by: {{ bug.reporter.name }}</span>
                            <span v-if="bug.assignee">Assigned to: {{ bug.assignee.name }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
