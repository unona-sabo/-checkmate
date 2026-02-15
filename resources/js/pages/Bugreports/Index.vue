<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
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

const escapeRegExp = (str: string): string => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const escapeHtml = (str: string): string => str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const highlight = (text: string): string => {
    const safe = escapeHtml(text);
    if (!searchQuery.value.trim()) return safe;
    const query = escapeRegExp(searchQuery.value.trim());
    return safe.replace(new RegExp(`(${query})`, 'gi'), '<mark class="search-highlight">$1</mark>');
};
</script>

<template>
    <Head title="Bugreports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="flex items-start gap-2 text-2xl font-bold tracking-tight">
                    <Bug class="h-6 w-6 shrink-0 mt-1 text-primary" />
                    Bugreports
                </h1>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search bugreports..."
                            class="pl-9 pr-8 w-56 bg-background/60"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <Link :href="`/projects/${project.id}/bugreports/create`">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Report Bug
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-if="bugreports.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <Bug class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No bugreports yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Report your first bug to start tracking issues.</p>
                    <Link :href="`/projects/${project.id}/bugreports/create`" class="mt-4 inline-block">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Report Bug
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-else-if="filteredBugreports.length === 0" class="flex flex-col items-center justify-center py-12">
                <Bug class="h-12 w-12 text-muted-foreground/50 mb-4" />
                <p class="text-muted-foreground">No bugreports match your search.</p>
            </div>

            <div v-else class="grid gap-2.5">
                <Link
                    v-for="bug in filteredBugreports"
                    :key="bug.id"
                    :href="`/projects/${project.id}/bugreports/${bug.id}`"
                    class="block"
                >
                    <Card class="hover:border-primary transition-colors cursor-pointer">
                        <CardContent class="px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <Bug class="h-4 w-4 text-primary shrink-0" />
                                        <h3 class="text-base font-semibold truncate" v-html="highlight(bug.title)" />
                                        <span :class="['px-1.5 py-0 rounded text-[10px] font-medium h-4 inline-flex items-center shrink-0', getSeverityColor(bug.severity)]">
                                            {{ bug.severity }}
                                        </span>
                                        <span :class="['px-1.5 py-0 rounded text-[10px] font-medium h-4 inline-flex items-center shrink-0', getStatusColor(bug.status)]">
                                            {{ bug.status.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-muted-foreground mt-2">
                                        <span v-if="bug.reporter">{{ bug.reporter.name }}</span>
                                        <span v-if="bug.assignee" class="flex items-center gap-1">
                                            <span class="text-muted-foreground/50">→</span>
                                            {{ bug.assignee.name }}
                                        </span>
                                        <span v-if="bug.description" class="truncate max-w-xs text-muted-foreground/70" v-html="highlight(bug.description)" />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.search-highlight) {
    background-color: rgb(147 197 253 / 0.5);
    border-radius: 0.125rem;
    padding: 0.0625rem 0.125rem;
}
</style>
