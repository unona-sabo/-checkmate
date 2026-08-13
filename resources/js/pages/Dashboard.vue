<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Bug,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    FileText,
    Flame,
    Play,
    Rocket,
    Sparkles,
    Target,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AchievementBadge from '@/components/AchievementBadge.vue';
import DashboardEventRow from '@/components/DashboardEventRow.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    ACHIEVEMENT_BADGES,
    GOOD_DAY_TOAST_VARIANTS,
    type BadgeId,
} from '@/lib/achievement-badges';
import { QA_PREDICTION_CATEGORIES } from '@/lib/qa-predictions';
import { dashboard } from '@/routes';
import { type AppPageProps, type BreadcrumbItem } from '@/types';
import {
    type DashboardActivity,
    type DashboardActivityCounts,
    type DashboardActivityProject,
} from '@/types/checkmate';

const props = defineProps<{
    activity: DashboardActivity | null;
}>();

const page = usePage<AppPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const userName = computed(() => page.props.auth.user.name.split(' ')[0]);

const greeting = 'Good day';

const goodDayImage =
    GOOD_DAY_TOAST_VARIANTS[
        Math.floor(Math.random() * GOOD_DAY_TOAST_VARIANTS.length)
    ];

const qaPredictionCategory =
    QA_PREDICTION_CATEGORIES[
        Math.floor(Math.random() * QA_PREDICTION_CATEGORIES.length)
    ];

const qaPrediction =
    qaPredictionCategory.phrases[
        Math.floor(Math.random() * qaPredictionCategory.phrases.length)
    ];

interface MetricConfig {
    key: keyof DashboardActivityCounts;
    label: string;
    icon: typeof ClipboardList;
    color: 'blue' | 'red' | 'emerald' | 'purple' | 'amber' | 'cyan';
}

const METRICS: MetricConfig[] = [
    {
        key: 'checklists',
        label: 'New Checklists',
        icon: ClipboardList,
        color: 'blue',
    },
    {
        key: 'checklists_completed',
        label: 'Checklists Completed',
        icon: ClipboardCheck,
        color: 'emerald',
    },
    { key: 'bugreports', label: 'New Bug Reports', icon: Bug, color: 'red' },
    {
        key: 'test_runs_completed',
        label: 'Runs Completed',
        icon: Play,
        color: 'emerald',
    },
    {
        key: 'releases_opened',
        label: 'Releases Opened',
        icon: Rocket,
        color: 'purple',
    },
    {
        key: 'releases_released',
        label: 'Releases Shipped',
        icon: CheckCircle2,
        color: 'emerald',
    },
    {
        key: 'features_added',
        label: 'Features Added',
        icon: Target,
        color: 'amber',
    },
    {
        key: 'test_cases_added',
        label: 'New Test Cases',
        icon: FileText,
        color: 'cyan',
    },
    {
        key: 'ai_analyses',
        label: 'AI Analyses',
        icon: Sparkles,
        color: 'purple',
    },
];

const METRIC_STYLES: Record<
    MetricConfig['color'],
    { wrap: string; icon: string }
> = {
    blue: { wrap: 'bg-blue-500/10', icon: 'text-blue-500/80' },
    red: { wrap: 'bg-red-500/10', icon: 'text-red-500/80' },
    emerald: { wrap: 'bg-emerald-500/10', icon: 'text-emerald-500/80' },
    purple: { wrap: 'bg-purple-500/10', icon: 'text-purple-500/80' },
    amber: { wrap: 'bg-amber-500/10', icon: 'text-amber-500/80' },
    cyan: { wrap: 'bg-cyan-500/10', icon: 'text-cyan-500/80' },
};

const hasAnyTotals = (counts: DashboardActivityCounts): boolean =>
    Object.values(counts).some((count) => count > 0);

const weekHasActivity = computed(
    () => !!props.activity && hasAnyTotals(props.activity.week),
);

function weekTrend(
    key: keyof DashboardActivityCounts,
): { label: string; class: string } | null {
    if (!props.activity) return null;
    const diff = props.activity.week[key] - props.activity.week_previous[key];
    if (diff === 0) {
        return { label: 'No change', class: 'text-muted-foreground' };
    }
    return {
        label: `${diff > 0 ? '+' : ''}${diff} vs last week`,
        class:
            diff > 0
                ? 'text-emerald-600 dark:text-emerald-400'
                : 'text-red-600 dark:text-red-400',
    };
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

const PROJECT_PREVIEW_COUNT = 3;
const LAST_DAY_PREVIEW_COUNT = 6;

const showAllLastDayEvents = ref(false);

const selectedProject = ref<DashboardActivityProject | null>(null);

const isProjectDialogOpen = computed({
    get: () => selectedProject.value !== null,
    set: (value: boolean) => {
        if (!value) selectedProject.value = null;
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-6">
            <!-- Greeting hero -->
            <div
                class="dashboard-greeting relative flex flex-col gap-4 overflow-hidden rounded-xl border border-amber-300/60 p-5 shadow-lg shadow-amber-900/10 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:p-6"
            >
                <div class="flex items-center gap-5">
                    <AchievementBadge
                        id="good-work-day"
                        :unlocked="true"
                        :size="72"
                        :src-override="goodDayImage"
                        class="relative z-10 shrink-0"
                    />
                    <div class="relative z-10 min-w-0">
                        <p
                            class="text-xs font-medium tracking-wide text-amber-700/80 uppercase dark:text-amber-300/80"
                        >
                            {{ greeting }}, {{ userName }}
                        </p>
                        <p
                            class="mt-1 text-lg font-semibold text-amber-950 dark:text-amber-100"
                        >
                            Wishing you a good work day!
                        </p>
                        <p
                            class="mt-1 text-sm text-amber-900/70 dark:text-amber-200/70"
                        >
                            Here's what's been happening across your workspace.
                        </p>
                    </div>
                </div>

                <!-- QA fortune: a random category + phrase on every load -->
                <div
                    class="dashboard-prediction relative z-10 w-full shrink-0 rounded-lg border border-amber-300/50 bg-white/40 p-3 sm:w-auto sm:max-w-[320px] dark:border-amber-700/40 dark:bg-black/10"
                >
                    <p
                        class="flex items-center gap-1.5 text-[11px] font-medium tracking-wide text-amber-700/80 uppercase dark:text-amber-300/80"
                    >
                        <span aria-hidden="true">{{
                            qaPredictionCategory.emoji
                        }}</span>
                        {{ qaPredictionCategory.label }}
                    </p>
                    <p
                        class="mt-1 text-sm font-medium text-amber-950 dark:text-amber-100"
                    >
                        {{ qaPrediction }}
                    </p>
                </div>
            </div>

            <template v-if="activity">
                <!-- Recent achievements -->
                <Card v-if="activity.achievements.length">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Sparkles class="h-4 w-4 text-amber-500/80" />
                            Recent Achievements
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap gap-4 pt-1.5">
                            <div
                                v-for="achievement in activity.achievements"
                                :key="achievement.key"
                                class="flex w-20 flex-col items-center gap-1.5 text-center"
                            >
                                <AchievementBadge
                                    :id="achievement.key as BadgeId"
                                    :unlocked="true"
                                    :size="48"
                                />
                                <p
                                    class="line-clamp-2 flex min-h-[28px] items-center text-[11px] leading-tight font-medium"
                                >
                                    {{
                                        ACHIEVEMENT_BADGES[
                                            achievement.key as BadgeId
                                        ]?.name
                                    }}
                                </p>
                                <p class="text-[10px] text-muted-foreground">
                                    {{ formatDate(achievement.unlocked_at) }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Last 24 hours -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Last 24 Hours</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="activity.last_day_events.length"
                            class="divide-y divide-border"
                        >
                            <DashboardEventRow
                                v-for="(event, index) in showAllLastDayEvents
                                    ? activity.last_day_events
                                    : activity.last_day_events.slice(
                                          0,
                                          LAST_DAY_PREVIEW_COUNT,
                                      )"
                                :key="index"
                                :event="event"
                                show-project
                            />
                        </div>
                        <p
                            v-else
                            class="py-4 text-center text-sm text-muted-foreground"
                        >
                            No activity in the last 24 hours.
                        </p>
                        <button
                            v-if="
                                activity.last_day_events.length >
                                LAST_DAY_PREVIEW_COUNT
                            "
                            type="button"
                            class="mt-2 cursor-pointer text-xs font-medium text-primary hover:text-primary/80"
                            @click="
                                showAllLastDayEvents = !showAllLastDayEvents
                            "
                        >
                            {{
                                showAllLastDayEvents
                                    ? 'View less'
                                    : `View all (${activity.last_day_events.length})`
                            }}
                        </button>
                    </CardContent>
                </Card>

                <!-- Last 7 days -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Last 7 Days</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="weekHasActivity"
                            class="grid grid-cols-2 gap-4 sm:grid-cols-4"
                        >
                            <div
                                v-for="metric in METRICS"
                                :key="metric.key"
                                class="flex flex-col items-center gap-1.5 rounded-lg p-2 text-center"
                            >
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-lg"
                                    :class="METRIC_STYLES[metric.color].wrap"
                                >
                                    <component
                                        :is="metric.icon"
                                        class="h-4 w-4"
                                        :class="
                                            METRIC_STYLES[metric.color].icon
                                        "
                                    />
                                </div>
                                <p class="text-lg font-bold">
                                    {{ activity.week[metric.key] }}
                                </p>
                                <p
                                    class="text-[11px] leading-tight text-muted-foreground"
                                >
                                    {{ metric.label }}
                                </p>
                                <p
                                    class="text-[10px] leading-tight"
                                    :class="weekTrend(metric.key)?.class"
                                >
                                    {{ weekTrend(metric.key)?.label }}
                                </p>
                            </div>
                        </div>
                        <p
                            v-else
                            class="py-4 text-center text-sm text-muted-foreground"
                        >
                            No activity in the last 7 days.
                        </p>
                    </CardContent>
                </Card>

                <!-- Active projects -->
                <div v-if="activity.projects.length">
                    <h2
                        class="mb-3 flex items-center gap-2 text-sm font-semibold"
                    >
                        <Flame class="h-4 w-4 text-amber-500/80" />
                        Active Projects (Last 7 Days)
                    </h2>
                    <div
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <Card
                            v-for="project in activity.projects"
                            :key="project.id"
                            class="flex flex-col"
                        >
                            <CardHeader class="pb-0">
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <CardTitle
                                        class="truncate text-sm"
                                        :title="project.name"
                                    >
                                        {{ project.name }}
                                    </CardTitle>
                                    <span
                                        class="shrink-0 text-xs text-muted-foreground"
                                    >
                                        {{ project.total }} updates
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent class="flex flex-1 flex-col">
                                <div class="divide-y divide-border">
                                    <DashboardEventRow
                                        v-for="(
                                            event, ri
                                        ) in project.recent.slice(
                                            0,
                                            PROJECT_PREVIEW_COUNT,
                                        )"
                                        :key="ri"
                                        :event="event"
                                    />
                                </div>
                                <button
                                    v-if="
                                        project.recent.length >
                                        PROJECT_PREVIEW_COUNT
                                    "
                                    type="button"
                                    class="mt-auto cursor-pointer pt-2 text-xs font-medium text-primary hover:text-primary/80"
                                    @click="selectedProject = project"
                                >
                                    View all ({{ project.recent.length }})
                                </button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </template>

            <div
                v-else
                class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border bg-card/50 px-6 py-16 text-center"
            >
                <p class="text-sm font-medium text-muted-foreground">
                    No workspace activity to show yet.
                </p>
                <p class="max-w-sm text-xs text-muted-foreground/70">
                    Join or create a workspace to see what's happening across
                    your projects.
                </p>
            </div>
        </div>

        <Dialog v-model:open="isProjectDialogOpen">
            <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ selectedProject?.name }}</DialogTitle>
                    <DialogDescription>
                        All activity in the last 7 days
                    </DialogDescription>
                </DialogHeader>
                <div class="divide-y divide-border">
                    <DashboardEventRow
                        v-for="(event, ei) in selectedProject?.recent ?? []"
                        :key="ei"
                        :event="event"
                    />
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.dashboard-greeting {
    background: linear-gradient(
        -45deg,
        #fef9c3,
        #fef08a,
        #fde047,
        #fef3c7,
        #fef08a
    );
    background-size: 300% 300%;
    animation: dashboard-greeting-shimmer 10s ease infinite;
}

:global(.dark) .dashboard-greeting {
    background: linear-gradient(
        -45deg,
        rgba(120, 53, 15, 0.45),
        rgba(146, 64, 14, 0.35),
        rgba(180, 83, 9, 0.4),
        rgba(146, 64, 14, 0.35)
    );
    background-size: 300% 300%;
    animation: dashboard-greeting-shimmer 10s ease infinite;
}

.dashboard-greeting::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        115deg,
        transparent 20%,
        rgba(255, 255, 255, 0.35) 35%,
        transparent 50%
    );
    background-size: 250% 100%;
    animation: dashboard-greeting-sweep 6s ease-in-out infinite;
}

@keyframes dashboard-greeting-shimmer {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

@keyframes dashboard-greeting-sweep {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -50% 0;
    }
}

.dashboard-prediction {
    animation: dashboard-prediction-fade-in 0.5s ease-out both;
}

@keyframes dashboard-prediction-fade-in {
    0% {
        opacity: 0;
        transform: translateY(4px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .dashboard-greeting,
    .dashboard-greeting::before,
    .dashboard-prediction {
        animation: none;
    }
}
</style>
