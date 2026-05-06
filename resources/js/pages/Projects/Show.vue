<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    type BreadcrumbItem,
    type Project,
    type ProjectSearchResponse,
    type ProjectSearchResultGroup,
} from '@/types';
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
    Rocket,
    Palette,
    StickyNote,
    Database,
    BarChart3,
    Drama,
    Terminal,
    Link2,
} from 'lucide-vue-next';
import { releaseStatusVariant } from '@/lib/badge-variants';
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { escapeHtml, escapeRegExp } from '@/composables/useSearch';
import RestrictedAction from '@/components/RestrictedAction.vue';

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

const highlight = (text: string): string => {
    const safe = escapeHtml(text);
    if (!searchQuery.value.trim()) return safe;
    const query = escapeRegExp(searchQuery.value.trim());
    return safe.replace(
        new RegExp(`(${query})`, 'gi'),
        '<mark class="search-highlight">$1</mark>',
    );
};

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
                { params: { q: trimmed } },
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
        case 'test_suites':
            return Layers;
        case 'test_cases':
            return FileText;
        case 'checklists':
            return ClipboardList;
        case 'test_runs':
            return Play;
        case 'bugreports':
            return Bug;
        case 'documentations':
            return FileText;
        case 'releases':
            return Rocket;
        case 'design_links':
            return Palette;
        case 'notes':
            return StickyNote;
        case 'test_data_users':
            return Database;
        case 'test_data_commands':
            return Terminal;
        case 'test_data_links':
            return Link2;
        case 'project_features':
            return Drama;
        case 'automation_results':
            return BarChart3;
        default:
            return FileText;
    }
};

const getBadgeColor = (type: string, value: string) => {
    if (type === 'test_runs') {
        switch (value) {
            case 'active':
                return 'bg-green-500/10 text-green-600 border-green-500/20';
            case 'completed':
                return 'bg-blue-500/10 text-blue-600 border-blue-500/20';
            case 'archived':
                return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        }
    }
    if (type === 'bugreports') {
        switch (value) {
            case 'to_do':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'in_progress':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'in_review':
                return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'needs_changes':
                return 'bg-red-100 text-red-800 border-red-200';
            case 'cancelled':
                return 'bg-gray-100 text-gray-800 border-gray-200';
            case 'done':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'blocker':
            case 'critical':
                return 'bg-red-100 text-red-800 border-red-200';
            case 'major':
                return 'bg-orange-100 text-orange-800 border-orange-200';
            case 'minor':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'trivial':
                return 'bg-gray-100 text-gray-600 border-gray-200';
        }
    }
    if (type === 'test_cases' || type === 'project_features') {
        switch (value) {
            case 'critical':
                return 'bg-red-100 text-red-800 border-red-200';
            case 'high':
                return 'bg-orange-100 text-orange-800 border-orange-200';
            case 'medium':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'low':
                return 'bg-gray-100 text-gray-600 border-gray-200';
        }
    }
    if (type === 'releases') {
        switch (value) {
            case 'planned':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'in_progress':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'released':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'cancelled':
                return 'bg-red-100 text-red-800 border-red-200';
        }
    }
    if (type === 'notes') {
        switch (value) {
            case 'draft':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'published':
                return 'bg-green-100 text-green-800 border-green-200';
        }
    }
    if (type === 'automation_results') {
        switch (value) {
            case 'passed':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'failed':
                return 'bg-red-100 text-red-800 border-red-200';
            case 'skipped':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'error':
                return 'bg-orange-100 text-orange-800 border-orange-200';
        }
    }
    return 'bg-muted text-muted-foreground border-border';
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-500/10 text-green-500 border-green-500/20';
        case 'completed':
            return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        case 'archived':
            return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        default:
            return '';
    }
};

const getBugStatusColor = (status: string) => {
    switch (status) {
        case 'to_do':
            return 'bg-blue-100 text-blue-800';
        case 'in_progress':
            return 'bg-yellow-100 text-yellow-800';
        case 'in_review':
            return 'bg-purple-100 text-purple-800';
        case 'needs_changes':
            return 'bg-red-100 text-red-800';
        case 'cancelled':
            return 'bg-gray-100 text-gray-800';
        case 'done':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head :title="project.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="flex items-start gap-2 text-2xl font-bold tracking-tight"
                    >
                        <FolderOpen
                            class="mt-1 h-6 w-6 shrink-0 text-primary"
                        />
                        {{ project.name }}
                    </h1>
                    <p class="text-muted-foreground">
                        Created
                        {{
                            new Date(project.created_at).toLocaleDateString(
                                'en-US',
                                {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                },
                            )
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search project..."
                            class="w-72 bg-background/60 pr-8 pl-9"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/edit`">
                            <Button variant="outline" class="gap-2">
                                <Edit class="h-4 w-4" />
                                Edit Project
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <!-- Search Results -->
            <div v-if="isSearchActive">
                <!-- Loading skeleton -->
                <div v-if="isSearching && !searchResults" class="space-y-4">
                    <div class="h-5 w-48 animate-pulse rounded bg-muted"></div>
                    <div v-for="i in 3" :key="i" class="space-y-2">
                        <div
                            class="h-6 w-32 animate-pulse rounded bg-muted"
                        ></div>
                        <div class="rounded-lg border">
                            <div
                                v-for="j in 2"
                                :key="j"
                                class="flex items-center gap-3 border-b px-4 py-3 last:border-b-0"
                            >
                                <div
                                    class="h-4 flex-1 animate-pulse rounded bg-muted"
                                ></div>
                                <div
                                    class="h-5 w-16 animate-pulse rounded bg-muted"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                <div v-else-if="searchResults">
                    <p class="mb-4 text-sm text-muted-foreground">
                        <span v-if="searchResults.total > 0">
                            {{ searchResults.total }} result{{
                                searchResults.total !== 1 ? 's' : ''
                            }}
                            for "<span class="font-medium text-foreground">{{
                                searchResults.query
                            }}</span
                            >"
                        </span>
                    </p>

                    <!-- Grouped results -->
                    <div
                        v-if="searchResults.results.length > 0"
                        class="space-y-6"
                    >
                        <div
                            v-for="group in searchResults.results"
                            :key="group.type"
                        >
                            <div class="mb-2 flex items-center gap-2">
                                <component
                                    :is="getTypeIcon(group.type)"
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                <h3 class="text-sm font-semibold">
                                    {{ group.label }}
                                </h3>
                                <span
                                    class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-muted px-1 text-[10px] font-medium text-muted-foreground"
                                    >{{ group.count }}</span
                                >
                            </div>
                            <div class="space-y-[3px]">
                                <a
                                    v-for="item in group.items"
                                    :key="item.id"
                                    :href="item.url"
                                    class="group/item flex cursor-pointer items-center justify-between rounded-xl border bg-card px-4 py-2.5 transition-all duration-150 hover:border-primary/50 hover:shadow-sm"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <div
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-muted/50 transition-colors group-hover/item:bg-primary/10"
                                        >
                                            <component
                                                :is="getTypeIcon(group.type)"
                                                class="h-3.5 w-3.5 text-muted-foreground transition-colors group-hover/item:text-primary"
                                            />
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-normal transition-colors group-hover/item:text-primary"
                                                v-html="highlight(item.title)"
                                            />
                                            <p
                                                v-if="item.subtitle"
                                                class="text-xs text-muted-foreground"
                                                v-html="
                                                    highlight(item.subtitle)
                                                "
                                            />
                                        </div>
                                    </div>
                                    <div
                                        class="ml-4 flex shrink-0 items-center gap-2"
                                    >
                                        <Badge
                                            v-if="item.badge"
                                            variant="outline"
                                            :class="
                                                getBadgeColor(
                                                    group.type,
                                                    item.badge,
                                                )
                                            "
                                            class="h-4 px-1.5 text-[10px] font-medium"
                                        >
                                            {{ item.badge.replace('_', ' ') }}
                                        </Badge>
                                        <Badge
                                            v-if="item.extra_badge"
                                            variant="secondary"
                                            :class="
                                                getBadgeColor(
                                                    group.type,
                                                    item.extra_badge,
                                                )
                                            "
                                            class="h-4 px-1.5 text-[10px] font-normal"
                                        >
                                            {{
                                                item.extra_badge.replace(
                                                    '_',
                                                    ' ',
                                                )
                                            }}
                                        </Badge>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-16 text-muted-foreground"
                    >
                        <Search class="mb-3 h-12 w-12" />
                        <p class="text-lg font-medium">No results found</p>
                        <p class="max-w-full truncate px-4 text-sm">
                            No items match "{{ searchResults.query }}"
                        </p>
                    </div>
                </div>

                <!-- Waiting for min chars -->
                <div
                    v-else-if="searchQuery.trim().length < 2"
                    class="flex flex-col items-center justify-center py-16 text-muted-foreground"
                >
                    <Search class="mb-3 h-12 w-12" />
                    <p class="text-sm">Type at least 2 characters to search</p>
                </div>
            </div>

            <!-- Card Grid (hidden during search) -->
            <div v-else class="grid gap-6 md:grid-cols-3">
                <!-- Checklists Section -->
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                <ClipboardList class="h-5 w-5 text-primary" />
                                Checklists
                                <span
                                    class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-xs font-medium text-muted-foreground"
                                    >{{ project.checklists_count ?? 0 }}</span
                                >
                            </CardTitle>
                            <RestrictedAction>
                                <Link
                                    :href="`/projects/${project.id}/checklists/create`"
                                >
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        class="cursor-pointer p-0"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </RestrictedAction>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col p-5 pt-0">
                        <div
                            v-if="project.checklists?.length"
                            class="space-y-1.5"
                        >
                            <Link
                                v-for="checklist in project.checklists.slice(
                                    0,
                                    5,
                                )"
                                :key="checklist.id"
                                :href="`/projects/${project.id}/checklists/${checklist.id}`"
                                class="flex cursor-pointer items-center justify-between rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50"
                            >
                                <span class="truncate font-medium">{{
                                    checklist.name
                                }}</span>
                                <ArrowRight
                                    class="h-4 w-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </div>
                        <div
                            v-else
                            class="py-3 text-center text-sm text-muted-foreground"
                        >
                            No checklists yet
                        </div>
                        <Link
                            :href="`/projects/${project.id}/checklists`"
                            class="mt-auto block pt-3"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                class="w-full cursor-pointer text-sm"
                                >View All</Button
                            >
                        </Link>
                    </CardContent>
                </Card>

                <!-- Test Suites Section -->
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                <Layers class="h-5 w-5 text-primary" />
                                Test Suites
                                <span
                                    class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-xs font-medium text-muted-foreground"
                                    >{{ project.test_suites_count ?? 0 }}</span
                                >
                            </CardTitle>
                            <RestrictedAction>
                                <Link
                                    :href="`/projects/${project.id}/test-suites/create`"
                                >
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        class="cursor-pointer p-0"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </RestrictedAction>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col p-5 pt-0">
                        <div
                            v-if="project.test_suites?.length"
                            class="space-y-1.5"
                        >
                            <Link
                                v-for="suite in project.test_suites.slice(0, 5)"
                                :key="suite.id"
                                :href="`/projects/${project.id}/test-suites/${suite.id}`"
                                class="flex cursor-pointer items-center justify-between rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50"
                            >
                                <span class="truncate font-medium">{{
                                    suite.name
                                }}</span>
                                <ArrowRight
                                    class="h-4 w-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </div>
                        <div
                            v-else
                            class="py-3 text-center text-sm text-muted-foreground"
                        >
                            No test suites yet
                        </div>
                        <Link
                            :href="`/projects/${project.id}/test-suites`"
                            class="mt-auto block pt-3"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                class="w-full cursor-pointer text-sm"
                                >View All</Button
                            >
                        </Link>
                    </CardContent>
                </Card>

                <!-- Test Runs Section -->
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                <Play class="h-5 w-5 text-primary" />
                                Test Runs
                                <span
                                    class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-xs font-medium text-muted-foreground"
                                    >{{ project.test_runs_count ?? 0 }}</span
                                >
                            </CardTitle>
                            <RestrictedAction>
                                <Link
                                    :href="`/projects/${project.id}/test-runs/create`"
                                >
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        class="cursor-pointer p-0"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </RestrictedAction>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col p-5 pt-0">
                        <div
                            v-if="project.test_runs?.length"
                            class="space-y-1.5"
                        >
                            <Link
                                v-for="run in project.test_runs.slice(0, 5)"
                                :key="run.id"
                                :href="`/projects/${project.id}/test-runs/${run.id}`"
                                class="flex cursor-pointer items-center justify-between rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50"
                            >
                                <span class="min-w-0 truncate font-medium">{{
                                    run.name
                                }}</span>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="text-xs text-muted-foreground"
                                        >{{ run.progress }}%</span
                                    >
                                    <Badge
                                        :class="getStatusColor(run.status)"
                                        variant="outline"
                                        class="h-5 px-1.5 py-0 text-xs"
                                    >
                                        {{ run.status }}
                                    </Badge>
                                    <ArrowRight
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                </div>
                            </Link>
                        </div>
                        <div
                            v-else
                            class="py-3 text-center text-sm text-muted-foreground"
                        >
                            No test runs yet
                        </div>
                        <Link
                            :href="`/projects/${project.id}/test-runs`"
                            class="mt-auto block pt-3"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                class="w-full cursor-pointer text-sm"
                                >View All</Button
                            >
                        </Link>
                    </CardContent>
                </Card>

                <!-- Bug Reports Section -->
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                <Bug class="h-5 w-5 text-primary" />
                                Bug Reports
                                <span
                                    class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-xs font-medium text-muted-foreground"
                                    >{{ project.bugreports_count ?? 0 }}</span
                                >
                            </CardTitle>
                            <RestrictedAction>
                                <Link
                                    :href="`/projects/${project.id}/bugreports/create`"
                                >
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        class="cursor-pointer p-0"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </RestrictedAction>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col p-5 pt-0">
                        <div
                            v-if="project.bugreports?.length"
                            class="space-y-1.5"
                        >
                            <Link
                                v-for="bug in project.bugreports.slice(0, 5)"
                                :key="bug.id"
                                :href="`/projects/${project.id}/bugreports/${bug.id}`"
                                class="flex cursor-pointer items-center justify-between gap-6 rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50"
                            >
                                <span class="min-w-0 truncate font-medium">{{
                                    bug.title
                                }}</span>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span
                                        :class="[
                                            'inline-flex h-4 items-center rounded px-1.5 py-0 text-[10px] font-medium',
                                            getBugStatusColor(bug.status),
                                        ]"
                                    >
                                        {{
                                            bug.status
                                                .split('_')
                                                .map(
                                                    (w) =>
                                                        w
                                                            .charAt(0)
                                                            .toUpperCase() +
                                                        w.slice(1),
                                                )
                                                .join(' ')
                                        }}
                                    </span>
                                    <ArrowRight
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                </div>
                            </Link>
                        </div>
                        <div
                            v-else
                            class="py-3 text-center text-sm text-muted-foreground"
                        >
                            No bug reports yet
                        </div>
                        <Link
                            :href="`/projects/${project.id}/bugreports`"
                            class="mt-auto block pt-3"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                class="w-full cursor-pointer text-sm"
                                >View All</Button
                            >
                        </Link>
                    </CardContent>
                </Card>

                <!-- Releases Section -->
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                <Rocket class="h-5 w-5 text-primary" />
                                Releases
                                <span
                                    class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-xs font-medium text-muted-foreground"
                                    >{{ project.releases_count ?? 0 }}</span
                                >
                            </CardTitle>
                            <RestrictedAction>
                                <Link
                                    :href="`/projects/${project.id}/releases`"
                                >
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        class="cursor-pointer p-0"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </RestrictedAction>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col p-5 pt-0">
                        <div
                            v-if="project.releases?.length"
                            class="space-y-1.5"
                        >
                            <Link
                                v-for="release in project.releases.slice(0, 5)"
                                :key="release.id"
                                :href="`/projects/${project.id}/releases/${release.id}`"
                                class="flex cursor-pointer items-center justify-between gap-6 rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50"
                            >
                                <span
                                    class="flex min-w-0 items-center gap-2 truncate"
                                >
                                    <Badge
                                        variant="outline"
                                        class="shrink-0 font-mono text-xs"
                                        >v{{ release.version }}</Badge
                                    >
                                    <span class="truncate font-medium">{{
                                        release.name
                                    }}</span>
                                </span>
                                <div class="flex shrink-0 items-center gap-2">
                                    <Badge
                                        :variant="
                                            releaseStatusVariant(release.status)
                                        "
                                        class="h-5 px-1.5 py-0 text-xs"
                                    >
                                        {{ release.status?.replace('_', ' ') }}
                                    </Badge>
                                    <ArrowRight
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                </div>
                            </Link>
                        </div>
                        <div
                            v-else
                            class="py-3 text-center text-sm text-muted-foreground"
                        >
                            No releases yet
                        </div>
                        <Link
                            :href="`/projects/${project.id}/releases`"
                            class="mt-auto block pt-3"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                class="w-full cursor-pointer text-sm"
                                >View All</Button
                            >
                        </Link>
                    </CardContent>
                </Card>

                <!-- Documentations Section -->
                <Card class="flex flex-col">
                    <CardHeader class="p-5 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                <FileText class="h-5 w-5 text-primary" />
                                Documentations
                                <span
                                    class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-xs font-medium text-muted-foreground"
                                    >{{
                                        project.documentations_count ?? 0
                                    }}</span
                                >
                            </CardTitle>
                            <RestrictedAction>
                                <Link
                                    :href="`/projects/${project.id}/documentations/create`"
                                >
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        class="cursor-pointer p-0"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </RestrictedAction>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col p-5 pt-0">
                        <div
                            v-if="project.documentations?.length"
                            class="space-y-1.5"
                        >
                            <Link
                                v-for="doc in project.documentations.slice(
                                    0,
                                    5,
                                )"
                                :key="doc.id"
                                :href="`/projects/${project.id}/documentations/${doc.id}`"
                                class="flex cursor-pointer items-center justify-between rounded border px-3 py-2 text-sm transition-colors hover:bg-muted/50"
                            >
                                <span class="truncate font-medium">{{
                                    doc.title
                                }}</span>
                                <ArrowRight
                                    class="h-4 w-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </div>
                        <div
                            v-else
                            class="py-3 text-center text-sm text-muted-foreground"
                        >
                            No documentations yet
                        </div>
                        <Link
                            :href="`/projects/${project.id}/documentations`"
                            class="mt-auto block pt-3"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                class="w-full cursor-pointer text-sm"
                                >View All</Button
                            >
                        </Link>
                    </CardContent>
                </Card>
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
