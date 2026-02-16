<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestRun } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Plus, Play, CheckCircle2, Archive, Search, X, Clock, Calendar, User, Pause, Timer } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import RestrictedAction from '@/components/RestrictedAction.vue';

const props = defineProps<{
    project: Project;
    testRuns: TestRun[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Runs', href: `/projects/${props.project.id}/test-runs` },
];

const getStatusColor = (status: string) => {
    switch (status) {
        case 'active': return 'bg-green-500/10 text-green-500 border-green-500/20';
        case 'completed': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        case 'archived': return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        default: return '';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'active': return Play;
        case 'completed': return CheckCircle2;
        case 'archived': return Archive;
        default: return Play;
    }
};

const searchQuery = ref('');

const filteredTestRuns = computed(() => {
    if (!searchQuery.value.trim()) return props.testRuns;
    const query = searchQuery.value.toLowerCase();
    return props.testRuns.filter(run =>
        run.name.toLowerCase().includes(query) ||
        run.environment?.toLowerCase().includes(query) ||
        run.milestone?.toLowerCase().includes(query) ||
        run.status.toLowerCase().includes(query)
    );
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
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/test-runs/create`">
                            <Button variant="cta" class="gap-2">
                                <Plus class="h-4 w-4" />
                                New Test Run
                            </Button>
                        </Link>
                    </RestrictedAction>
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

            <div v-else-if="filteredTestRuns.length === 0 && searchQuery.trim()" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                <Search class="h-12 w-12 mb-3" />
                <p class="font-semibold">No results found</p>
                <p class="text-sm max-w-full truncate px-4">No test runs match "{{ searchQuery }}"</p>
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
                                            <Badge :class="getStatusColor(run.status)" variant="outline" class="text-[10px] px-1.5 py-0 h-4 shrink-0">
                                                {{ run.status }}
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
