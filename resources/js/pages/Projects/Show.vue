<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type ProjectSearchResponse, type ProjectSearchResultGroup } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    ClipboardList,
    Layers,
    Play,
    Plus,
    Edit,
    FolderOpen,
    ArrowRight,
    Bug,
    Search,
    X,
    FileText,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps<{
    project: Project;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
];

// Search state
const searchQuery = ref('');
const isSearching = ref(false);
const searchResults = ref<ProjectSearchResponse | null>(null);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const isSearchActive = computed(() => searchQuery.value.trim().length > 0);

watch(searchQuery, (value) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    const trimmed = value.trim();
    if (trimmed.length < 2) {
        searchResults.value = null;
        isSearching.value = false;
        return;
    }

    isSearching.value = true;
    searchTimeout = setTimeout(async () => {
        try {
            const response = await axios.get<ProjectSearchResponse>(
                `/projects/${props.project.id}/search`,
                { params: { q: trimmed } }
            );
            searchResults.value = response.data;
        } catch {
            searchResults.value = null;
        } finally {
            isSearching.value = false;
        }
    }, 300);
});

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'test_suites': return Layers;
        case 'test_cases': return FileText;
        case 'checklists': return ClipboardList;
        case 'test_runs': return Play;
        case 'bugreports': return Bug;
        case 'documentations': return FileText;
        default: return FileText;
    }
};

const getBadgeColor = (type: string, value: string) => {
    if (type === 'test_runs') {
        switch (value) {
            case 'active': return 'bg-green-500/10 text-green-600 border-green-500/20';
            case 'completed': return 'bg-blue-500/10 text-blue-600 border-blue-500/20';
            case 'archived': return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        }
    }
    if (type === 'bugreports') {
        switch (value) {
            case 'new': return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'open': return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'in_progress': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'resolved': return 'bg-green-100 text-green-800 border-green-200';
            case 'closed': return 'bg-gray-100 text-gray-800 border-gray-200';
            case 'reopened': return 'bg-red-100 text-red-800 border-red-200';
            case 'blocker': case 'critical': return 'bg-red-100 text-red-800 border-red-200';
            case 'major': return 'bg-orange-100 text-orange-800 border-orange-200';
            case 'minor': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'trivial': return 'bg-gray-100 text-gray-600 border-gray-200';
        }
    }
    if (type === 'test_cases') {
        switch (value) {
            case 'critical': return 'bg-red-100 text-red-800 border-red-200';
            case 'high': return 'bg-orange-100 text-orange-800 border-orange-200';
            case 'medium': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'low': return 'bg-gray-100 text-gray-600 border-gray-200';
        }
    }
    return 'bg-muted text-muted-foreground border-border';
};

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
                    <h1 class="flex items-start gap-2 text-2xl font-bold tracking-tight">
                        <FolderOpen class="h-6 w-6 shrink-0 mt-1 text-primary" />
                        {{ project.name }}
                    </h1>
                    <p class="text-muted-foreground">
                        Created {{ new Date(project.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search project..."
                            class="pl-9 pr-8 w-72 bg-background/60"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <Link :href="`/projects/${project.id}/edit`">
                        <Button variant="outline" class="gap-2">
                            <Edit class="h-4 w-4" />
                            Edit Project
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Search Results -->
            <div v-if="isSearchActive">
                <!-- Loading skeleton -->
                <div v-if="isSearching && !searchResults" class="space-y-4">
                    <div class="h-5 w-48 animate-pulse rounded bg-muted"></div>
                    <div v-for="i in 3" :key="i" class="space-y-2">
                        <div class="h-6 w-32 animate-pulse rounded bg-muted"></div>
                        <div class="rounded-lg border">
                            <div v-for="j in 2" :key="j" class="flex items-center gap-3 border-b last:border-b-0 px-4 py-3">
                                <div class="h-4 flex-1 animate-pulse rounded bg-muted"></div>
                                <div class="h-5 w-16 animate-pulse rounded bg-muted"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                <div v-else-if="searchResults">
                    <p class="mb-4 text-sm text-muted-foreground">
                        <span v-if="searchResults.total > 0">
                            {{ searchResults.total }} result{{ searchResults.total !== 1 ? 's' : '' }} for "<span class="font-medium text-foreground">{{ searchResults.query }}</span>"
                        </span>
                    </p>

                    <!-- Grouped results -->
                    <div v-if="searchResults.results.length > 0" class="space-y-6">
                        <div v-for="group in searchResults.results" :key="group.type">
                            <div class="mb-2 flex items-center gap-2">
                                <component :is="getTypeIcon(group.type)" class="h-4 w-4 text-muted-foreground" />
                                <h3 class="text-sm font-semibold">{{ group.label }}</h3>
                                <span class="text-xs text-muted-foreground">({{ group.count }})</span>
                            </div>
                            <div class="space-y-[3px]">
                                <a
                                    v-for="item in group.items"
                                    :key="item.id"
                                    :href="item.url"
                                    class="group/item flex items-center justify-between px-4 py-2.5 rounded-xl border bg-card hover:border-primary/50 hover:shadow-sm transition-all duration-150 cursor-pointer"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="h-7 w-7 rounded-lg bg-muted/50 flex items-center justify-center shrink-0 group-hover/item:bg-primary/10 transition-colors">
                                            <component :is="getTypeIcon(group.type)" class="h-3.5 w-3.5 text-muted-foreground group-hover/item:text-primary transition-colors" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-normal truncate group-hover/item:text-primary transition-colors">{{ item.title }}</p>
                                            <p v-if="item.subtitle" class="text-xs text-muted-foreground">{{ item.subtitle }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0 ml-4">
                                        <Badge
                                            v-if="item.badge"
                                            variant="outline"
                                            :class="getBadgeColor(group.type, item.badge)"
                                            class="text-[10px] px-1.5 h-4 font-medium"
                                        >
                                            {{ item.badge.replace('_', ' ') }}
                                        </Badge>
                                        <Badge
                                            v-if="item.extra_badge"
                                            variant="secondary"
                                            :class="getBadgeColor(group.type, item.extra_badge)"
                                            class="text-[10px] px-1.5 h-4 font-normal"
                                        >
                                            {{ item.extra_badge.replace('_', ' ') }}
                                        </Badge>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-else class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                        <Search class="h-12 w-12 mb-3" />
                        <p class="text-lg font-medium">No results found</p>
                        <p class="text-sm">No items match "{{ searchResults.query }}"</p>
                    </div>
                </div>

                <!-- Waiting for min chars -->
                <div v-else-if="searchQuery.trim().length < 2" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                    <Search class="h-12 w-12 mb-3" />
                    <p class="text-sm">Type at least 2 characters to search</p>
                </div>
            </div>

            <!-- Card Grid (hidden during search) -->
            <div v-else class="grid gap-6 md:grid-cols-3">
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
                                <span class="font-medium truncate min-w-0">{{ run.name }}</span>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs text-muted-foreground">{{ run.progress }}%</span>
                                    <Badge :class="getStatusColor(run.status)" variant="outline" class="text-xs px-1.5 py-0 h-5">
                                        {{ run.status }}
                                    </Badge>
                                    <ArrowRight class="h-4 w-4 text-muted-foreground" />
                                </div>
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
                                <FileText class="h-5 w-5 text-primary" />
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
