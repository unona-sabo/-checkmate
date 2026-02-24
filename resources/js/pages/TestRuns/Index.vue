<script setup lang="ts">
import { Head, Link, Deferred } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSearch } from '@/composables/useSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestRun } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Plus, Play, CheckCircle2, Archive, Search, X, Calendar, User, Pause, Timer, Filter, FileText, ListChecks, ChevronDown } from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { priorityVariant, testRunStatusVariant } from '@/lib/badge-variants';

const props = defineProps<{
    project: Project;
    testRuns: TestRun[];
    users?: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Runs', href: `/projects/${props.project.id}/test-runs` },
];

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'active': return Play;
        case 'completed': return CheckCircle2;
        case 'archived': return Archive;
        default: return Play;
    }
};

const { searchQuery, highlight } = useSearch();

// Filters
const showFilters = ref(false);
const filterStatus = ref('');
const filterSource = ref('');
const filterPriority = ref('');
const filterEnvironment = ref('');
const filterAuthor = ref('');
const filterCreatedFrom = ref('');
const filterCreatedTo = ref('');
const filterCompletedFrom = ref('');
const filterCompletedTo = ref('');
const filterPassedMin = ref('');
const filterPassedMax = ref('');
const filterFailedMin = ref('');
const filterFailedMax = ref('');
const filterTotalMin = ref('');
const filterTotalMax = ref('');

const allFilters = [
    filterStatus, filterSource, filterPriority, filterEnvironment, filterAuthor,
    filterCreatedFrom, filterCreatedTo, filterCompletedFrom, filterCompletedTo,
    filterPassedMin, filterPassedMax, filterFailedMin, filterFailedMax,
    filterTotalMin, filterTotalMax,
];

const activeFilterCount = computed(() => allFilters.filter(f => f.value !== '').length);

const clearFilters = () => {
    allFilters.forEach(f => { f.value = ''; });
};

const uniqueEnvironments = computed(() => {
    const envs = new Set<string>();
    props.testRuns.forEach(run => {
        if (run.environment) envs.add(run.environment);
    });
    return [...envs].sort();
});

const filteredTestRuns = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const hasSearch = query.length > 0;
    const hasFilters = activeFilterCount.value > 0;

    if (!hasSearch && !hasFilters) return props.testRuns;

    return props.testRuns.filter(run => {
        // Search filter
        if (hasSearch &&
            !run.name.toLowerCase().includes(query) &&
            !run.environment?.toLowerCase().includes(query) &&
            !run.milestone?.toLowerCase().includes(query) &&
            !run.status.toLowerCase().includes(query)
        ) return false;

        // Status filter
        if (filterStatus.value && run.status !== filterStatus.value) return false;

        // Source filter
        if (filterSource.value && run.source !== filterSource.value) return false;

        // Priority filter
        if (filterPriority.value && run.priority !== filterPriority.value) return false;

        // Environment filter
        if (filterEnvironment.value && run.environment !== filterEnvironment.value) return false;

        // Author filter
        if (filterAuthor.value && String(run.creator?.id) !== filterAuthor.value) return false;

        // Created date filters
        if (filterCreatedFrom.value && run.created_at < filterCreatedFrom.value) return false;
        if (filterCreatedTo.value && run.created_at.slice(0, 10) > filterCreatedTo.value) return false;

        // Completed date filters
        if (filterCompletedFrom.value) {
            if (!run.completed_at || run.completed_at < filterCompletedFrom.value) return false;
        }
        if (filterCompletedTo.value) {
            if (!run.completed_at || run.completed_at.slice(0, 10) > filterCompletedTo.value) return false;
        }

        // Passed range
        const passed = run.stats?.passed ?? 0;
        if (filterPassedMin.value && passed < Number(filterPassedMin.value)) return false;
        if (filterPassedMax.value && passed > Number(filterPassedMax.value)) return false;

        // Failed range
        const failed = run.stats?.failed ?? 0;
        if (filterFailedMin.value && failed < Number(filterFailedMin.value)) return false;
        if (filterFailedMax.value && failed > Number(filterFailedMax.value)) return false;

        // Total checks range
        const total = run.test_run_cases_count ?? 0;
        if (filterTotalMin.value && total < Number(filterTotalMin.value)) return false;
        if (filterTotalMax.value && total > Number(filterTotalMax.value)) return false;

        return true;
    });
});

const formatElapsed = (seconds: number | null | undefined): string | null => {
    if (seconds == null || seconds < 0) return null;
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes}m`;
};

const formatDate = (dateStr: string | null): string | null => {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

// Live timer: tick every second for active non-paused runs
const tick = ref(0);
let timerInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    timerInterval = setInterval(() => { tick.value++; }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

const getLiveElapsed = (run: TestRun): number | null => {
    // Force reactivity on tick
    tick.value;
    if (run.status === 'completed' || run.status === 'archived') {
        return run.elapsed_seconds ?? null;
    }
    // For active runs, compute client-side
    const start = run.started_at ?? run.created_at;
    if (!start) return null;
    const startMs = new Date(start).getTime();
    const nowMs = Date.now();
    let total = Math.floor((nowMs - startMs) / 1000);
    let paused = run.total_paused_seconds ?? 0;
    if (run.is_paused && run.paused_at) {
        paused += Math.floor((nowMs - new Date(run.paused_at).getTime()) / 1000);
    }
    return Math.max(0, total - paused);
};

const pauseRun = (run: TestRun) => {
    router.post(`/projects/${props.project.id}/test-runs/${run.id}/pause`, {}, { preserveScroll: true });
};

const resumeRun = (run: TestRun) => {
    router.post(`/projects/${props.project.id}/test-runs/${run.id}/resume`, {}, { preserveScroll: true });
};

</script>

<template>
    <Head title="Test Runs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-start gap-2 text-2xl font-bold tracking-tight">
                        <Play class="h-6 w-6 shrink-0 mt-1 text-primary" />
                        Test Runs
                    </h1>
                    <p class="text-muted-foreground">Execute and track test case results</p>
                </div>
                <div class="flex items-center gap-2">
                    <template v-if="testRuns.length > 0">
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search test runs..."
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
                        <Button
                            variant="outline"
                            class="gap-2 relative cursor-pointer"
                            @click="showFilters = !showFilters"
                        >
                            <Filter class="h-4 w-4" />
                            Filter
                            <Badge
                                v-if="activeFilterCount > 0"
                                class="absolute -top-2 -right-2 h-5 w-5 p-0 flex items-center justify-center text-[10px] rounded-full"
                            >
                                {{ activeFilterCount }}
                            </Badge>
                        </Button>
                    </template>
                    <RestrictedAction>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="cta" class="gap-2 cursor-pointer">
                                    <Plus class="h-4 w-4" />
                                    New Test Run
                                    <ChevronDown class="h-3 w-3 ml-0.5" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem as-child class="cursor-pointer">
                                    <Link :href="`/projects/${project.id}/test-runs/create`" class="flex items-center gap-2">
                                        <FileText class="h-4 w-4" />
                                        From Test Cases
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child class="cursor-pointer">
                                    <Link :href="`/projects/${project.id}/test-runs/create?source=checklist`" class="flex items-center gap-2">
                                        <ListChecks class="h-4 w-4" />
                                        From Checklist
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </RestrictedAction>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="relative -mt-3">
                <div v-if="showFilters" class="fixed inset-0 z-10" @click="showFilters = false" />
                <div v-if="showFilters" class="absolute top-0 right-0 z-20 w-full md:w-[calc(66%-0.3125rem)] rounded-xl border bg-card shadow-lg p-4 animate-in fade-in slide-in-from-top-2 duration-200">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium flex items-center gap-2">
                            <Filter class="h-4 w-4 text-primary" />
                            Filters
                            <Badge v-if="activeFilterCount > 0" class="h-5 px-1.5 text-[10px] rounded-full">{{ activeFilterCount }}</Badge>
                        </span>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="activeFilterCount > 0"
                                variant="ghost"
                                size="sm"
                                class="h-6 gap-1 text-xs text-muted-foreground hover:text-destructive cursor-pointer"
                                @click="clearFilters"
                            >
                                <X class="h-3 w-3" />
                                Clear All
                            </Button>
                            <button @click="showFilters = false" class="p-1 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <!-- Row 1: Status, Source, Priority -->
                    <div class="grid grid-cols-3 gap-x-3 gap-y-2.5">
                        <div class="relative">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Status</Label>
                            <Select v-model="filterStatus">
                                <SelectTrigger class="h-8 text-xs cursor-pointer" :class="filterStatus ? 'pr-7' : ''">
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="completed">Completed</SelectItem>
                                    <SelectItem value="archived">Archived</SelectItem>
                                </SelectContent>
                            </Select>
                            <button v-if="filterStatus" @click="filterStatus = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                        <div class="relative">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Source</Label>
                            <Select v-model="filterSource">
                                <SelectTrigger class="h-8 text-xs cursor-pointer" :class="filterSource ? 'pr-7' : ''">
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="test-cases">Test Cases</SelectItem>
                                    <SelectItem value="checklist">Checklist</SelectItem>
                                </SelectContent>
                            </Select>
                            <button v-if="filterSource" @click="filterSource = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                        <div class="relative">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Priority</Label>
                            <Select v-model="filterPriority">
                                <SelectTrigger class="h-8 text-xs cursor-pointer" :class="filterPriority ? 'pr-7' : ''">
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Low</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="critical">Critical</SelectItem>
                                </SelectContent>
                            </Select>
                            <button v-if="filterPriority" @click="filterPriority = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                    <!-- Row 2: Environment, Created From, Completed From -->
                    <div class="grid grid-cols-3 gap-x-3 gap-y-2.5 mt-2.5">
                        <div class="relative">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Environment</Label>
                            <Select v-model="filterEnvironment">
                                <SelectTrigger class="h-8 text-xs cursor-pointer" :class="filterEnvironment ? 'pr-7' : ''">
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="env in uniqueEnvironments" :key="env" :value="env">{{ env }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <button v-if="filterEnvironment" @click="filterEnvironment = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                        <div class="relative">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Created From</Label>
                            <Input v-model="filterCreatedFrom" type="date" class="h-8 text-xs" :class="filterCreatedFrom ? 'pr-7' : ''" />
                            <button v-if="filterCreatedFrom" @click="filterCreatedFrom = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                        <div class="relative">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Completed From</Label>
                            <Input v-model="filterCompletedFrom" type="date" class="h-8 text-xs" :class="filterCompletedFrom ? 'pr-7' : ''" />
                            <button v-if="filterCompletedFrom" @click="filterCompletedFrom = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                    <!-- Row 3: Author, Created To, Completed To -->
                    <div class="grid grid-cols-3 gap-x-3 gap-y-2.5 mt-2.5">
                        <Deferred data="users">
                            <template #fallback>
                                <div>
                                    <Label class="text-[11px] text-muted-foreground mb-1 block">Author</Label>
                                    <div class="h-8 w-full animate-pulse rounded-md bg-muted" />
                                </div>
                            </template>
                            <div class="relative">
                                <Label class="text-[11px] text-muted-foreground mb-1 block">Author</Label>
                                <Select v-model="filterAuthor">
                                    <SelectTrigger class="h-8 text-xs cursor-pointer" :class="filterAuthor ? 'pr-7' : ''">
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="user in users"
                                            :key="user.id"
                                            :value="String(user.id)"
                                        >
                                            {{ user.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <button v-if="filterAuthor" @click="filterAuthor = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                        </Deferred>
                        <div class="relative">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Created To</Label>
                            <Input v-model="filterCreatedTo" type="date" class="h-8 text-xs" :class="filterCreatedTo ? 'pr-7' : ''" />
                            <button v-if="filterCreatedTo" @click="filterCreatedTo = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                        <div class="relative">
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Completed To</Label>
                            <Input v-model="filterCompletedTo" type="date" class="h-8 text-xs" :class="filterCompletedTo ? 'pr-7' : ''" />
                            <button v-if="filterCompletedTo" @click="filterCompletedTo = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                    <!-- Row 4: Passed, Failed, Total Checks -->
                    <div class="grid grid-cols-3 gap-x-3 gap-y-2.5 mt-2.5">
                        <div>
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Passed</Label>
                            <div class="flex gap-1.5">
                                <Input v-model="filterPassedMin" type="number" min="0" placeholder="Min" class="h-8 text-xs" />
                                <Input v-model="filterPassedMax" type="number" min="0" placeholder="Max" class="h-8 text-xs" />
                            </div>
                        </div>
                        <div>
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Failed</Label>
                            <div class="flex gap-1.5">
                                <Input v-model="filterFailedMin" type="number" min="0" placeholder="Min" class="h-8 text-xs" />
                                <Input v-model="filterFailedMax" type="number" min="0" placeholder="Max" class="h-8 text-xs" />
                            </div>
                        </div>
                        <div>
                            <Label class="text-[11px] text-muted-foreground mb-1 block">Total Checks</Label>
                            <div class="flex gap-1.5">
                                <Input v-model="filterTotalMin" type="number" min="0" placeholder="Min" class="h-8 text-xs" />
                                <Input v-model="filterTotalMax" type="number" min="0" placeholder="Max" class="h-8 text-xs" />
                            </div>
                        </div>
                    </div>
                    <!-- Footer: Results count -->
                    <div class="flex justify-end mt-3">
                        <span class="text-sm text-muted-foreground">
                            Found <span class="font-semibold text-foreground">{{ filteredTestRuns.length }}</span> {{ filteredTestRuns.length === 1 ? 'run' : 'runs' }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="testRuns.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <Play class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No test runs yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Create a test run to start executing your test cases.</p>
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/test-runs/create`" class="mt-4 inline-block">
                            <Button variant="cta" class="gap-2">
                                <Plus class="h-4 w-4" />
                                Create Test Run
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <div v-else-if="filteredTestRuns.length === 0" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                <Search class="h-12 w-12 mb-3" />
                <p class="font-semibold">No results found</p>
                <p v-if="searchQuery.trim()" class="text-sm max-w-full truncate px-4">No test runs match "{{ searchQuery }}"</p>
                <p v-else class="text-sm">No test runs match the selected filters</p>
                <Button v-if="activeFilterCount > 0" variant="outline" size="sm" class="mt-3 cursor-pointer" @click="clearFilters">
                    Clear Filters
                </Button>
            </div>

            <div v-else class="space-y-3">
                <Link
                    v-for="run in filteredTestRuns"
                    :key="run.id"
                    :href="`/projects/${project.id}/test-runs/${run.id}`"
                    class="block"
                >
                    <Card class="transition-all hover:border-primary cursor-pointer">
                        <CardContent class="px-3 py-[5px]">
                            <div class="flex items-center justify-between">
                                <div class="flex items-start gap-3 min-w-0 flex-1">
                                    <component :is="getStatusIcon(run.status)" class="h-4 w-4 shrink-0 mt-0.5 text-primary" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-sm font-semibold truncate" v-html="highlight(run.name)" />
                                            <Badge :variant="testRunStatusVariant(run.status)" class="text-[10px] px-1.5 py-0 h-4 shrink-0">
                                                {{ run.status }}
                                            </Badge>
                                            <Badge v-if="run.source === 'test-cases'" variant="cyan" class="text-[10px] px-1.5 py-0 h-4 shrink-0">
                                                test cases
                                            </Badge>
                                            <Badge v-else-if="run.source === 'checklist'" variant="purple" class="text-[10px] px-1.5 py-0 h-4 shrink-0">
                                                checklist
                                            </Badge>
                                            <Badge v-if="run.priority" :variant="priorityVariant(run.priority)" class="text-[10px] px-1.5 py-0 h-4 shrink-0">
                                                {{ run.priority }}
                                            </Badge>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs text-muted-foreground mt-1.5">
                                            <span v-if="run.environment" v-html="highlight(run.environment)" />
                                            <span v-if="run.milestone" v-html="highlight(run.milestone)" />
                                            <span>{{ run.test_run_cases_count || 0 }} cases</span>
                                            <!-- Elapsed time for all runs -->
                                            <span class="text-muted-foreground/50">|</span>
                                            <span v-if="formatElapsed(getLiveElapsed(run))" class="flex items-center gap-0.5">
                                                <Pause v-if="run.is_paused" class="h-3 w-3 text-yellow-500" />
                                                <Timer v-else class="h-3 w-3" />
                                                {{ formatElapsed(getLiveElapsed(run)) }}
                                            </span>
                                            <!-- Created date & author for all runs -->
                                            <span v-if="formatDate(run.created_at)" class="flex items-center gap-0.5">
                                                <Calendar class="h-3 w-3" />
                                                {{ formatDate(run.created_at) }}
                                            </span>
                                            <span v-if="run.creator" class="flex items-center gap-0.5">
                                                <User class="h-3 w-3" />
                                                {{ run.creator.name }}
                                            </span>
                                            <!-- Completed date & user for completed/archived -->
                                            <template v-if="run.status === 'completed' || run.status === 'archived'">
                                                <span v-if="formatDate(run.completed_at)" class="flex items-center gap-0.5">
                                                    <CheckCircle2 class="h-3 w-3" />
                                                    {{ formatDate(run.completed_at) }}
                                                </span>
                                                <span v-if="run.completed_by_user" class="flex items-center gap-0.5">
                                                    <User class="h-3 w-3" />
                                                    {{ run.completed_by_user.name }}
                                                </span>
                                            </template>
                                            <!-- Inline Stats -->
                                            <template v-if="run.stats">
                                                <span class="text-muted-foreground/50">|</span>
                                                <span v-if="run.stats.passed" class="flex items-center gap-0.5">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                                    {{ run.stats.passed }}
                                                </span>
                                                <span v-if="run.stats.failed" class="flex items-center gap-0.5">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                                    {{ run.stats.failed }}
                                                </span>
                                                <span v-if="run.stats.blocked" class="flex items-center gap-0.5">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-orange-500"></span>
                                                    {{ run.stats.blocked }}
                                                </span>
                                                <span v-if="run.stats.untested" class="flex items-center gap-0.5">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                                    {{ run.stats.untested }}
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <RestrictedAction>
                                        <button
                                            v-if="run.status === 'active' && !run.is_paused"
                                            @click.prevent.stop="pauseRun(run)"
                                            class="p-1.5 rounded-md text-muted-foreground hover:text-yellow-500 hover:bg-yellow-500/10 transition-colors cursor-pointer"
                                            title="Pause"
                                        >
                                            <Pause class="h-4 w-4" />
                                        </button>
                                    </RestrictedAction>
                                    <RestrictedAction>
                                        <button
                                            v-if="run.status === 'active' && run.is_paused"
                                            @click.prevent.stop="resumeRun(run)"
                                            class="p-1.5 rounded-md text-yellow-500 hover:text-green-500 hover:bg-green-500/10 transition-colors cursor-pointer"
                                            title="Resume"
                                        >
                                            <Play class="h-4 w-4" />
                                        </button>
                                    </RestrictedAction>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-primary">{{ run.progress }}%</div>
                                        <Progress :model-value="run.progress" class="w-24 h-2" />
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
