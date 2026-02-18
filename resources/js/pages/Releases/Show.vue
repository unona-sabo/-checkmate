<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, Deferred, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import type { Release, ReleaseFeature, ReleaseChecklistItem, ReleaseMetricsSnapshot, ProjectFeature, TestRun, ReleaseLiveMetrics } from '@/types/checkmate';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Progress } from '@/components/ui/progress';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Rocket,
    Pencil,
    RefreshCw,
    CheckCircle,
    AlertTriangle,
    Bug,
    ClipboardCheck,
    Plus,
    Trash2,
    X,
    Play,
    Link2,
    Unlink,
    ChevronDown,
    ChevronRight,
    Save,
    BarChart3,
    Target,
    Clock,
    Shield,
    TrendingUp,
    TrendingDown,
    Minus,
} from 'lucide-vue-next';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { releaseStatusVariant, releaseDecisionVariant } from '@/lib/badge-variants';

interface WorkspaceMember {
    id: number;
    name: string;
    email: string;
}

const props = defineProps<{
    project: Project;
    release: Release;
    blockers: number;
    liveMetrics: ReleaseLiveMetrics;
    projectFeatures?: { id: number; name: string; module: string[] | null }[];
    projectTestRuns?: { id: number; name: string; status: string; environment: string | null }[];
    workspaceMembers?: WorkspaceMember[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Releases', href: `/projects/${props.project.id}/releases` },
    { title: `v${props.release.version}`, href: `/projects/${props.project.id}/releases/${props.release.id}` },
];

// Tabs
type TabKey = 'overview' | 'features' | 'checklist' | 'decision';
const activeTab = ref<TabKey>('overview');
const tabs: { key: TabKey; label: string; icon: typeof BarChart3 }[] = [
    { key: 'overview', label: 'Overview', icon: BarChart3 },
    { key: 'features', label: 'Features', icon: Target },
    { key: 'checklist', label: 'Checklist', icon: ClipboardCheck },
    { key: 'decision', label: 'Decision', icon: CheckCircle },
];

// Edit release
const showEditDialog = ref(false);
const editForm = ref({
    version: props.release.version,
    name: props.release.name,
    description: props.release.description || '',
    planned_date: props.release.planned_date?.split('T')[0] || '',
    actual_date: props.release.actual_date?.split('T')[0] || '',
    status: props.release.status,
});

watch(() => props.release, (r) => {
    editForm.value = {
        version: r.version,
        name: r.name,
        description: r.description || '',
        planned_date: r.planned_date?.split('T')[0] || '',
        actual_date: r.actual_date?.split('T')[0] || '',
        status: r.status,
    };
});

const updateRelease = () => {
    router.put(`/projects/${props.project.id}/releases/${props.release.id}`, {
        ...editForm.value,
        description: editForm.value.description || null,
        planned_date: editForm.value.planned_date || null,
        actual_date: editForm.value.actual_date || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { showEditDialog.value = false; },
    });
};

// Refresh metrics
const refreshing = ref(false);
const refreshMetrics = () => {
    refreshing.value = true;
    router.post(`/projects/${props.project.id}/releases/${props.release.id}/refresh-metrics`, {}, {
        preserveScroll: true,
        onFinish: () => { refreshing.value = false; },
    });
};

// Stats
const features = computed(() => props.release.features || []);
const checklistItems = computed(() => props.release.checklist_items || []);
const latestMetrics = computed(() => props.release.latest_metrics || props.release.metrics_snapshots?.[0] || null);
const linkedTestRuns = computed(() => props.release.test_runs || []);

const checklistProgress = computed(() => {
    const total = checklistItems.value.length;
    if (total === 0) return 0;
    const completed = checklistItems.value.filter((i) => i.status === 'completed').length;
    return Math.round((completed / total) * 100);
});

// Features
const showAddFeatureDialog = ref(false);
const featureForm = ref({ feature_id: '', feature_name: '', description: '' });
const showEditFeatureDialog = ref(false);
const editingFeature = ref<ReleaseFeature | null>(null);
const editFeatureForm = ref({ feature_name: '', description: '', status: 'planned', tests_planned: 0, tests_executed: 0, tests_passed: 0 });

const addFeature = () => {
    router.post(`/projects/${props.project.id}/releases/${props.release.id}/features`, {
        feature_id: featureForm.value.feature_id ? Number(featureForm.value.feature_id) : null,
        feature_name: featureForm.value.feature_name,
        description: featureForm.value.description || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAddFeatureDialog.value = false;
            featureForm.value = { feature_id: '', feature_name: '', description: '' };
        },
    });
};

const startEditFeature = (f: ReleaseFeature) => {
    editingFeature.value = f;
    editFeatureForm.value = {
        feature_name: f.feature_name,
        description: f.description || '',
        status: f.status,
        tests_planned: f.tests_planned,
        tests_executed: f.tests_executed,
        tests_passed: f.tests_passed,
    };
    showEditFeatureDialog.value = true;
};

const updateFeature = () => {
    if (!editingFeature.value) return;
    router.put(`/projects/${props.project.id}/releases/${props.release.id}/features/${editingFeature.value.id}`, {
        ...editFeatureForm.value,
        description: editFeatureForm.value.description || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showEditFeatureDialog.value = false;
            editingFeature.value = null;
        },
    });
};

const deleteFeature = (featureId: number) => {
    router.delete(`/projects/${props.project.id}/releases/${props.release.id}/features/${featureId}`, {
        preserveScroll: true,
    });
};

const onProjectFeatureSelect = (val: string) => {
    featureForm.value.feature_id = val;
    const pf = props.projectFeatures?.find((f) => f.id === Number(val));
    if (pf) {
        featureForm.value.feature_name = pf.name;
    }
};

// Checklist
const collapsedCategories = ref<Set<string>>(new Set());
const toggleCategory = (cat: string) => {
    if (collapsedCategories.value.has(cat)) {
        collapsedCategories.value.delete(cat);
    } else {
        collapsedCategories.value.add(cat);
    }
};

const categorizedChecklist = computed(() => {
    const groups: Record<string, ReleaseChecklistItem[]> = {};
    for (const item of checklistItems.value) {
        if (!groups[item.category]) groups[item.category] = [];
        groups[item.category].push(item);
    }
    return groups;
});

const updateChecklistItem = (item: ReleaseChecklistItem, data: Partial<ReleaseChecklistItem>) => {
    router.put(`/projects/${props.project.id}/releases/${props.release.id}/checklist-items/${item.id}`, data, {
        preserveScroll: true,
    });
};

const cycleStatus = (item: ReleaseChecklistItem) => {
    const order = ['pending', 'in_progress', 'completed', 'na'];
    const idx = order.indexOf(item.status);
    const next = order[(idx + 1) % order.length];
    updateChecklistItem(item, { status: next });
};

// Add checklist item
const showAddChecklistDialog = ref(false);
const checklistForm = ref({ title: '', category: 'testing', description: '', priority: 'medium', is_blocker: false });

const addChecklistItem = () => {
    router.post(`/projects/${props.project.id}/releases/${props.release.id}/checklist-items`, {
        ...checklistForm.value,
        description: checklistForm.value.description || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAddChecklistDialog.value = false;
            checklistForm.value = { title: '', category: 'testing', description: '', priority: 'medium', is_blocker: false };
        },
    });
};

// Edit checklist item
const showEditChecklistDialog = ref(false);
const editingChecklistItem = ref<ReleaseChecklistItem | null>(null);
const editChecklistForm = ref({ title: '', category: '', description: '', priority: 'medium', is_blocker: false, notes: '' });

const startEditChecklistItem = (item: ReleaseChecklistItem) => {
    editingChecklistItem.value = item;
    editChecklistForm.value = {
        title: item.title,
        category: item.category,
        description: item.description || '',
        priority: item.priority,
        is_blocker: item.is_blocker,
        notes: item.notes || '',
    };
    showEditChecklistDialog.value = true;
};

const saveChecklistItem = () => {
    if (!editingChecklistItem.value) return;
    router.put(`/projects/${props.project.id}/releases/${props.release.id}/checklist-items/${editingChecklistItem.value.id}`, {
        ...editChecklistForm.value,
        description: editChecklistForm.value.description || null,
        notes: editChecklistForm.value.notes || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showEditChecklistDialog.value = false;
            editingChecklistItem.value = null;
        },
    });
};

// Delete checklist item
const showDeleteChecklistDialog = ref(false);
const checklistItemToDelete = ref<ReleaseChecklistItem | null>(null);

const confirmDeleteChecklistItem = (item: ReleaseChecklistItem) => {
    checklistItemToDelete.value = item;
    showDeleteChecklistDialog.value = true;
};

const deleteChecklistItem = () => {
    if (!checklistItemToDelete.value) return;
    router.delete(`/projects/${props.project.id}/releases/${props.release.id}/checklist-items/${checklistItemToDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            showDeleteChecklistDialog.value = false;
            checklistItemToDelete.value = null;
        },
    });
};

// Test run linking
const showLinkTestRunDialog = ref(false);

const isTestRunLinked = (testRunId: number): boolean => {
    return linkedTestRuns.value.some((tr) => tr.id === testRunId);
};

const toggleTestRunLink = (testRunId: number) => {
    if (isTestRunLinked(testRunId)) {
        router.delete(`/projects/${props.project.id}/releases/${props.release.id}/test-runs/${testRunId}`, {
            preserveScroll: true,
        });
    } else {
        router.post(`/projects/${props.project.id}/releases/${props.release.id}/test-runs`, {
            test_run_id: testRunId,
        }, { preserveScroll: true });
    }
};

// Delete release
const showDeleteReleaseDialog = ref(false);

const confirmDeleteRelease = () => {
    showEditDialog.value = false;
    showDeleteReleaseDialog.value = true;
};

const deleteRelease = () => {
    router.delete(`/projects/${props.project.id}/releases/${props.release.id}`, {
        onFinish: () => {
            showDeleteReleaseDialog.value = false;
        },
    });
};

// Decision
const decisionForm = ref({
    decision: props.release.decision,
    decision_notes: props.release.decision_notes || '',
});

const saveDecision = () => {
    router.put(`/projects/${props.project.id}/releases/${props.release.id}`, {
        decision: decisionForm.value.decision,
        decision_notes: decisionForm.value.decision_notes || null,
    }, { preserveScroll: true });
};

const autoRecommendation = computed(() => {
    const m = latestMetrics.value;
    if (!m) return { decision: 'pending', reason: 'No metrics available. Refresh metrics to get a recommendation.' };
    if (m.critical_bugs > 0) return { decision: 'no_go', reason: `${m.critical_bugs} critical bug(s) remain open.` };
    if (m.test_pass_rate < 70) return { decision: 'no_go', reason: `Test pass rate is ${m.test_pass_rate}% (below 70% threshold).` };
    if (m.test_pass_rate >= 95 && m.critical_bugs === 0) return { decision: 'go', reason: `Pass rate ${m.test_pass_rate}%, no critical bugs.` };
    return { decision: 'conditional', reason: `Pass rate ${m.test_pass_rate}%. Review remaining items.` };
});

// Helpers
const getHealthColor = (h: string) => {
    if (h === 'green') return 'bg-emerald-500';
    if (h === 'red') return 'bg-red-500';
    return 'bg-amber-500';
};

const getHealthText = (h: string) => {
    if (h === 'green') return 'text-emerald-600 dark:text-emerald-400';
    if (h === 'red') return 'text-red-600 dark:text-red-400';
    return 'text-amber-600 dark:text-amber-400';
};

const getCategoryLabel = (cat: string): string => {
    return cat.charAt(0).toUpperCase() + cat.slice(1);
};

const getChecklistStatusIcon = (status: string) => {
    if (status === 'completed') return CheckCircle;
    if (status === 'in_progress') return Play;
    return ClipboardCheck;
};

const getChecklistStatusColor = (status: string): string => {
    if (status === 'completed') return 'text-emerald-500';
    if (status === 'in_progress') return 'text-blue-500';
    if (status === 'na') return 'text-muted-foreground/50';
    return 'text-muted-foreground';
};

const getDecisionLabel = (d: string): string => {
    const labels: Record<string, string> = { pending: 'Pending', go: 'Go', no_go: 'No-Go', conditional: 'Conditional' };
    return labels[d] || d;
};

const formatDate = (d: string | null): string => {
    if (!d) return 'Not set';
    return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

// Live metrics helpers
const securityBadgeVariant = computed((): 'default' | 'secondary' | 'destructive' | 'outline' => {
    const s = props.liveMetrics.blockers_and_risks.security_status;
    if (s === 'passed') return 'default';
    if (s === 'pending') return 'secondary';
    if (s === 'in_progress') return 'outline';
    return 'secondary';
});

const formatDiff = (val: number): string => {
    if (val > 0) return `+${val}`;
    return String(val);
};

const diffColor = (val: number, invertForBugs = false): string => {
    const positive = invertForBugs ? val < 0 : val > 0;
    const negative = invertForBugs ? val > 0 : val < 0;
    if (positive) return 'text-emerald-600 dark:text-emerald-400';
    if (negative) return 'text-red-600 dark:text-red-400';
    return 'text-muted-foreground';
};

const trendIcon = computed(() => {
    const trend = props.liveMetrics.comparison?.trend;
    if (trend === 'better') return TrendingUp;
    if (trend === 'worse') return TrendingDown;
    return Minus;
});

const trendVariant = computed((): 'default' | 'secondary' | 'destructive' => {
    const trend = props.liveMetrics.comparison?.trend;
    if (trend === 'better') return 'default';
    if (trend === 'worse') return 'destructive';
    return 'secondary';
});

// Test run progress helpers
const getTestRunTotal = (tr: TestRun): number => {
    if (!tr.stats) return 0;
    return Object.values(tr.stats).reduce((sum: number, v) => sum + (Number(v) || 0), 0);
};

const getTestRunPassRate = (tr: TestRun): number => {
    const total = getTestRunTotal(tr);
    if (total === 0) return 0;
    return Math.round(((tr.stats?.passed ?? 0) / total) * 100);
};

const getTestRunPassRateColor = (tr: TestRun): string => {
    const rate = getTestRunPassRate(tr);
    if (rate >= 90) return 'text-emerald-600 dark:text-emerald-400';
    if (rate >= 70) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
};

const breakdownLabels: Record<string, string> = {
    test_completion: 'Test Completion',
    pass_rate: 'Pass Rate',
    critical_bugs: 'Critical Bugs',
    blockers: 'Blockers',
};
</script>

<template>
    <Head :title="`v${release.version} - ${project.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <Rocket class="h-6 w-6" />
                        <Badge variant="outline" class="font-mono text-sm">v{{ release.version }}</Badge>
                        <h1 class="text-2xl font-bold text-foreground">{{ release.name }}</h1>
                    </div>
                    <div class="mt-2 flex items-center gap-3">
                        <Badge :variant="releaseStatusVariant(release.status)">{{ release.status }}</Badge>
                        <div class="flex items-center gap-1.5">
                            <div class="h-3 w-3 rounded-full" :class="getHealthColor(release.health)" />
                            <span class="text-sm font-medium capitalize" :class="getHealthText(release.health)">{{ release.health }}</span>
                        </div>
                        <Badge :variant="releaseDecisionVariant(release.decision)">
                            {{ getDecisionLabel(release.decision) }}
                        </Badge>
                    </div>
                </div>
                <div class="flex gap-2">
                    <RestrictedAction>
                        <Button variant="outline" @click="refreshMetrics" :disabled="refreshing" class="cursor-pointer">
                            <RefreshCw class="mr-1 h-4 w-4" :class="{ 'animate-spin': refreshing }" />
                            Refresh Metrics
                        </Button>
                    </RestrictedAction>
                    <RestrictedAction>
                        <Button variant="outline" @click="showEditDialog = true" class="cursor-pointer">
                            <Pencil class="mr-1 h-4 w-4" />
                            Edit
                        </Button>
                    </RestrictedAction>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-lg bg-blue-500/10 p-3">
                            <BarChart3 class="h-6 w-6 text-blue-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Test Completion</p>
                            <p class="text-2xl font-bold">{{ latestMetrics?.test_completion_percentage ?? 0 }}%</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-lg bg-emerald-500/10 p-3">
                            <CheckCircle class="h-6 w-6 text-emerald-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Pass Rate</p>
                            <p class="text-2xl font-bold">{{ latestMetrics?.test_pass_rate ?? 0 }}%</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-lg bg-red-500/10 p-3">
                            <Bug class="h-6 w-6 text-red-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Open Bugs</p>
                            <p class="text-2xl font-bold">{{ latestMetrics?.total_bugs ?? 0 }}</p>
                            <p v-if="latestMetrics && latestMetrics.critical_bugs > 0" class="text-xs text-red-500">
                                {{ latestMetrics.critical_bugs }} critical
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-lg bg-amber-500/10 p-3">
                            <ClipboardCheck class="h-6 w-6 text-amber-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Checklist</p>
                            <div class="flex items-center gap-2">
                                <p class="text-2xl font-bold">{{ checklistProgress }}%</p>
                                <span v-if="blockers > 0" class="text-xs text-red-500">({{ blockers }} blockers)</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs -->
            <Card>
                <div class="border-b">
                    <nav class="flex gap-0 overflow-x-auto px-4" aria-label="Tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            class="flex cursor-pointer items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
                            :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:border-muted-foreground/30 hover:text-foreground'"
                        >
                            <component :is="tab.icon" class="h-4 w-4" />
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <!-- Tab: Overview -->
                <div v-if="activeTab === 'overview'" class="p-6 space-y-6">
                    <!-- Decision-Support Cards -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <!-- Card 1: Release Readiness -->
                        <div class="rounded-lg border p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-muted-foreground">Release Readiness</p>
                                <Badge :variant="liveMetrics.readiness.color === 'green' ? 'default' : liveMetrics.readiness.color === 'red' ? 'destructive' : 'secondary'">
                                    {{ liveMetrics.readiness.score }}%
                                </Badge>
                            </div>
                            <Progress :model-value="liveMetrics.readiness.score" class="h-2" />
                            <div class="space-y-1">
                                <div
                                    v-for="(item, key) in liveMetrics.readiness.breakdown"
                                    :key="key"
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="text-muted-foreground">{{ breakdownLabels[key] || key }}</span>
                                    <span class="font-medium">{{ item.weighted }}/{{ item.weight }}</span>
                                </div>
                            </div>
                            <div v-if="liveMetrics.readiness.days_to_deadline !== null" class="flex items-center gap-1.5 text-xs pt-1 border-t">
                                <Clock class="h-3.5 w-3.5" :class="liveMetrics.readiness.on_track ? 'text-muted-foreground' : 'text-red-500'" />
                                <span :class="liveMetrics.readiness.on_track ? 'text-muted-foreground' : 'text-red-600 dark:text-red-400 font-medium'">
                                    {{ liveMetrics.readiness.on_track
                                        ? `${liveMetrics.readiness.days_to_deadline} days to deadline`
                                        : `${Math.abs(liveMetrics.readiness.days_to_deadline)} days overdue`
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Card 2: Blockers & Risks -->
                        <div class="rounded-lg border p-4 space-y-3">
                            <p class="text-sm font-medium text-muted-foreground">Blockers & Risks</p>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm">Checklist Blockers</span>
                                    <Badge :variant="liveMetrics.blockers_and_risks.blocker_count > 0 ? 'destructive' : 'default'">
                                        {{ liveMetrics.blockers_and_risks.blocker_count }}
                                    </Badge>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm">Critical Bugs</span>
                                    <Badge :variant="liveMetrics.blockers_and_risks.critical_bugs > 0 ? 'destructive' : 'default'">
                                        {{ liveMetrics.blockers_and_risks.critical_bugs }}
                                    </Badge>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <Shield class="h-4 w-4 text-muted-foreground" />
                                        <span class="text-sm">Security</span>
                                    </div>
                                    <Badge :variant="securityBadgeVariant" class="capitalize">
                                        {{ liveMetrics.blockers_and_risks.security_status.replace('_', ' ') }}
                                    </Badge>
                                </div>
                            </div>
                            <div v-if="liveMetrics.blockers_and_risks.risks.length" class="space-y-1 pt-2 border-t">
                                <div
                                    v-for="(risk, i) in liveMetrics.blockers_and_risks.risks"
                                    :key="i"
                                    class="flex items-start gap-1.5 text-xs"
                                >
                                    <AlertTriangle class="h-3.5 w-3.5 text-amber-500 mt-0.5 shrink-0" />
                                    <span class="text-muted-foreground">{{ risk }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: vs Previous Release -->
                        <div class="rounded-lg border p-4 space-y-3">
                            <p class="text-sm font-medium text-muted-foreground">vs Previous Release</p>
                            <template v-if="liveMetrics.comparison">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-xs text-muted-foreground">Compared to v{{ liveMetrics.comparison.previous_version }}</span>
                                    <Badge :variant="trendVariant" class="capitalize text-xs">
                                        <component :is="trendIcon" class="mr-1 h-3 w-3" />
                                        {{ liveMetrics.comparison.trend }}
                                    </Badge>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">Pass Rate</span>
                                        <span class="font-medium" :class="diffColor(liveMetrics.comparison.pass_rate_diff)">
                                            {{ formatDiff(liveMetrics.comparison.pass_rate_diff) }}%
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">Open Bugs</span>
                                        <span class="font-medium" :class="diffColor(liveMetrics.comparison.bugs_diff, true)">
                                            {{ formatDiff(liveMetrics.comparison.bugs_diff) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">Completion</span>
                                        <span class="font-medium" :class="diffColor(liveMetrics.comparison.test_completion_diff)">
                                            {{ formatDiff(liveMetrics.comparison.test_completion_diff) }}%
                                        </span>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="flex items-center justify-center py-4 text-sm text-muted-foreground">
                                <p>No previous release to compare</p>
                            </div>
                        </div>
                    </div>

                    <!-- Linked Test Runs -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-semibold">Linked Test Runs</h3>
                            <RestrictedAction>
                                <Button variant="outline" size="sm" @click="showLinkTestRunDialog = true" class="cursor-pointer">
                                    <Link2 class="mr-1 h-4 w-4" />
                                    Link Test Run
                                </Button>
                            </RestrictedAction>
                        </div>
                        <div v-if="linkedTestRuns.length" class="space-y-2">
                            <div
                                v-for="tr in linkedTestRuns"
                                :key="tr.id"
                                class="rounded-lg border p-3 transition-colors hover:border-primary"
                            >
                                <div class="flex items-center justify-between">
                                    <Link :href="`/projects/${project.id}/test-runs/${tr.id}`" class="flex items-center gap-3 flex-1 min-w-0 cursor-pointer">
                                        <Play class="h-4 w-4 text-muted-foreground shrink-0" />
                                        <span class="font-medium text-sm truncate">{{ tr.name }}</span>
                                        <Badge :variant="releaseStatusVariant(tr.status)" class="text-xs shrink-0">{{ tr.status }}</Badge>
                                        <span v-if="tr.environment" class="text-xs text-muted-foreground shrink-0">{{ tr.environment }}</span>
                                    </Link>
                                    <RestrictedAction>
                                        <Button variant="ghost" size="sm" @click.prevent="toggleTestRunLink(tr.id)" class="cursor-pointer text-muted-foreground hover:text-destructive shrink-0">
                                            <X class="h-4 w-4" />
                                        </Button>
                                    </RestrictedAction>
                                </div>
                                <div v-if="tr.stats && getTestRunTotal(tr) > 0" class="mt-2 space-y-1.5 pl-7">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1">
                                            <Progress :model-value="getTestRunPassRate(tr)" class="h-1.5" />
                                        </div>
                                        <span class="text-xs font-medium min-w-[70px] text-right" :class="getTestRunPassRateColor(tr)">
                                            {{ getTestRunPassRate(tr) }}% ({{ tr.stats.passed ?? 0 }}/{{ getTestRunTotal(tr) }})
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                        <span class="text-emerald-600 dark:text-emerald-400">{{ tr.stats.passed ?? 0 }} passed</span>
                                        <span v-if="(tr.stats.failed ?? 0) > 0" class="text-red-600 dark:text-red-400">{{ tr.stats.failed }} failed</span>
                                        <span v-if="(tr.stats.blocked ?? 0) > 0" class="text-amber-600 dark:text-amber-400">{{ tr.stats.blocked }} blocked</span>
                                        <span v-if="(tr.stats.skipped ?? 0) > 0">{{ tr.stats.skipped }} skipped</span>
                                        <span v-if="(tr.stats.untested ?? 0) + (tr.stats.retest ?? 0) > 0">{{ (tr.stats.untested ?? 0) + (tr.stats.retest ?? 0) }} remaining</span>
                                    </div>
                                </div>
                                <p v-else-if="!tr.stats || getTestRunTotal(tr) === 0" class="mt-1.5 pl-7 text-xs text-muted-foreground italic">No tests executed yet</p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">No test runs linked yet.</p>
                    </div>

                    <!-- Release info -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg border p-4">
                            <p class="text-sm text-muted-foreground mb-1">Planned Date</p>
                            <p class="font-medium">{{ formatDate(release.planned_date) }}</p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-sm text-muted-foreground mb-1">Actual Date</p>
                            <p class="font-medium">{{ formatDate(release.actual_date) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tab: Features -->
                <div v-if="activeTab === 'features'" class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">Release Features ({{ features.length }})</h3>
                        <RestrictedAction>
                            <Button variant="outline" @click="showAddFeatureDialog = true" class="cursor-pointer">
                                <Plus class="mr-1 h-4 w-4" />
                                Add Feature
                            </Button>
                        </RestrictedAction>
                    </div>

                    <div v-if="features.length" class="space-y-3">
                        <div
                            v-for="f in features"
                            :key="f.id"
                            class="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-medium">{{ f.feature_name }}</span>
                                    <Badge :variant="releaseStatusVariant(f.status)" class="text-xs">{{ f.status }}</Badge>
                                </div>
                                <p v-if="f.description" class="text-sm text-muted-foreground mb-2">{{ f.description }}</p>
                                <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                    <span>Tests: {{ f.tests_passed }}/{{ f.tests_executed }}/{{ f.tests_planned }}</span>
                                    <span>Coverage: {{ f.test_coverage_percentage }}%</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <RestrictedAction>
                                    <Button variant="ghost" size="sm" @click="startEditFeature(f)" class="cursor-pointer">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                </RestrictedAction>
                                <RestrictedAction>
                                    <Button variant="ghost" size="sm" @click="deleteFeature(f.id)" class="cursor-pointer text-muted-foreground hover:text-destructive">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </RestrictedAction>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-12 text-center text-muted-foreground">
                        <Target class="mx-auto mb-3 h-12 w-12 opacity-30" />
                        <p class="text-lg font-medium">No features added yet</p>
                        <p class="mt-1 text-sm">Add features to track for this release</p>
                    </div>
                </div>

                <!-- Tab: Checklist -->
                <div v-if="activeTab === 'checklist'" class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">Quality Checklist</h3>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <Progress :model-value="checklistProgress" class="h-2 w-24" />
                                <span>{{ checklistProgress }}%</span>
                            </div>
                            <RestrictedAction>
                                <Button variant="outline" size="sm" @click="showAddChecklistDialog = true" class="cursor-pointer">
                                    <Plus class="mr-1 h-4 w-4" />
                                    Add Item
                                </Button>
                            </RestrictedAction>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div v-for="(items, category) in categorizedChecklist" :key="category">
                            <button
                                @click="toggleCategory(category)"
                                class="flex w-full items-center gap-2 rounded-lg bg-muted/50 px-4 py-2 text-sm font-semibold cursor-pointer hover:bg-muted/70 transition-colors"
                            >
                                <component :is="collapsedCategories.has(category) ? ChevronRight : ChevronDown" class="h-4 w-4" />
                                {{ getCategoryLabel(category) }}
                                <span class="text-xs font-normal text-muted-foreground">
                                    ({{ items.filter((i: ReleaseChecklistItem) => i.status === 'completed').length }}/{{ items.length }})
                                </span>
                            </button>
                            <div v-if="!collapsedCategories.has(category)" class="mt-2 space-y-1 pl-2">
                                <div
                                    v-for="item in items"
                                    :key="item.id"
                                    class="group flex items-center gap-3 rounded-lg px-3 py-2 transition-colors hover:bg-muted/30"
                                    :class="{ 'border-l-2 border-red-500 bg-red-50/50 dark:bg-red-950/10': item.is_blocker && item.status !== 'completed' }"
                                >
                                    <RestrictedAction>
                                        <button @click="cycleStatus(item)" class="cursor-pointer shrink-0">
                                            <component
                                                :is="getChecklistStatusIcon(item.status)"
                                                class="h-5 w-5"
                                                :class="getChecklistStatusColor(item.status)"
                                            />
                                        </button>
                                    </RestrictedAction>
                                    <div class="flex-1 min-w-0">
                                        <span
                                            class="text-sm"
                                            :class="{ 'line-through text-muted-foreground': item.status === 'completed' }"
                                        >
                                            {{ item.title }}
                                        </span>
                                        <p v-if="item.description" class="text-xs text-muted-foreground mt-0.5">{{ item.description }}</p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <Badge v-if="item.is_blocker && item.status !== 'completed'" variant="destructive" class="text-[10px]">Blocker</Badge>
                                            <span v-if="item.assignee" class="text-xs text-muted-foreground">{{ item.assignee.name }}</span>
                                            <span v-if="item.notes" class="text-xs text-muted-foreground italic truncate max-w-[200px]">{{ item.notes }}</span>
                                        </div>
                                    </div>
                                    <Badge variant="outline" class="text-xs capitalize shrink-0">{{ item.priority }}</Badge>
                                    <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                                        <RestrictedAction>
                                            <button
                                                @click="startEditChecklistItem(item)"
                                                class="cursor-pointer rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                title="Edit"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                            </button>
                                        </RestrictedAction>
                                        <RestrictedAction>
                                            <button
                                                @click="confirmDeleteChecklistItem(item)"
                                                class="cursor-pointer rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                title="Delete"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </RestrictedAction>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Decision -->
                <div v-if="activeTab === 'decision'" class="p-6 space-y-6">
                    <!-- Auto recommendation -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <AlertTriangle class="h-5 w-5" />
                                Recommendation
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-3 mb-2">
                                <Badge :variant="releaseDecisionVariant(autoRecommendation.decision)" class="text-sm">
                                    {{ getDecisionLabel(autoRecommendation.decision) }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">{{ autoRecommendation.reason }}</p>
                        </CardContent>
                    </Card>

                    <!-- Manual decision -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Release Decision</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <Label>Decision</Label>
                                <Select v-model="decisionForm.decision">
                                    <SelectTrigger class="mt-1">
                                        <SelectValue placeholder="Select decision" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="pending">Pending</SelectItem>
                                        <SelectItem value="go">Go</SelectItem>
                                        <SelectItem value="no_go">No-Go</SelectItem>
                                        <SelectItem value="conditional">Conditional</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Decision Notes</Label>
                                <Textarea v-model="decisionForm.decision_notes" placeholder="Add notes about this decision..." class="mt-1" rows="4" />
                            </div>
                            <RestrictedAction>
                                <Button @click="saveDecision" class="cursor-pointer">
                                    <Save class="mr-1 h-4 w-4" />
                                    Save Decision
                                </Button>
                            </RestrictedAction>
                        </CardContent>
                    </Card>
                </div>
            </Card>
        </div>

        <!-- Edit Release Dialog -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Release</DialogTitle>
                    <DialogDescription>Update release details.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Version</Label>
                            <Input v-model="editForm.version" class="mt-1" />
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select v-model="editForm.status">
                                <SelectTrigger class="mt-1"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="planning">Planning</SelectItem>
                                    <SelectItem value="development">Development</SelectItem>
                                    <SelectItem value="testing">Testing</SelectItem>
                                    <SelectItem value="staging">Staging</SelectItem>
                                    <SelectItem value="ready">Ready</SelectItem>
                                    <SelectItem value="released">Released</SelectItem>
                                    <SelectItem value="cancelled">Cancelled</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div>
                        <Label>Name</Label>
                        <Input v-model="editForm.name" class="mt-1" />
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea v-model="editForm.description" class="mt-1" rows="3" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Planned Date</Label>
                            <Input v-model="editForm.planned_date" type="date" class="mt-1" />
                        </div>
                        <div>
                            <Label>Actual Date</Label>
                            <Input v-model="editForm.actual_date" type="date" class="mt-1" />
                        </div>
                    </div>
                </div>
                <DialogFooter class="flex items-center justify-between sm:justify-between">
                    <Button
                        variant="ghost"
                        class="cursor-pointer text-destructive hover:text-destructive hover:bg-destructive/10"
                        @click="confirmDeleteRelease"
                    >
                        <Trash2 class="mr-1 h-4 w-4" />
                        Delete
                    </Button>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="showEditDialog = false" class="cursor-pointer">Cancel</Button>
                        <Button @click="updateRelease" class="cursor-pointer">Save</Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Release Confirmation Dialog -->
        <Dialog v-model:open="showDeleteReleaseDialog">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Delete Release?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ release.name }}" (v{{ release.version }})? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-4 sm:justify-end">
                    <Button variant="secondary" @click="showDeleteReleaseDialog = false" class="flex-1 sm:flex-none cursor-pointer">
                        No
                    </Button>
                    <Button variant="destructive" @click="deleteRelease" class="flex-1 sm:flex-none cursor-pointer">
                        Yes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Feature Dialog -->
        <Dialog v-model:open="showAddFeatureDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Release Feature</DialogTitle>
                    <DialogDescription>Add a feature to track in this release.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div v-if="projectFeatures?.length">
                        <Label>From Project Features</Label>
                        <Select @update:model-value="onProjectFeatureSelect">
                            <SelectTrigger class="mt-1"><SelectValue placeholder="Select a feature (optional)" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="pf in projectFeatures" :key="pf.id" :value="String(pf.id)">
                                    {{ pf.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Feature Name *</Label>
                        <Input v-model="featureForm.feature_name" placeholder="Feature name" class="mt-1" />
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea v-model="featureForm.description" placeholder="Description..." class="mt-1" rows="2" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddFeatureDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button @click="addFeature" :disabled="!featureForm.feature_name" class="cursor-pointer">Add</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Feature Dialog -->
        <Dialog v-model:open="showEditFeatureDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Feature</DialogTitle>
                    <DialogDescription>Update feature details and test counts.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div>
                        <Label>Feature Name</Label>
                        <Input v-model="editFeatureForm.feature_name" class="mt-1" />
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea v-model="editFeatureForm.description" class="mt-1" rows="2" />
                    </div>
                    <div>
                        <Label>Status</Label>
                        <Select v-model="editFeatureForm.status">
                            <SelectTrigger class="mt-1"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="planned">Planned</SelectItem>
                                <SelectItem value="in_progress">In Progress</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="deferred">Deferred</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <Label>Tests Planned</Label>
                            <Input v-model.number="editFeatureForm.tests_planned" type="number" min="0" class="mt-1" />
                        </div>
                        <div>
                            <Label>Tests Executed</Label>
                            <Input v-model.number="editFeatureForm.tests_executed" type="number" min="0" class="mt-1" />
                        </div>
                        <div>
                            <Label>Tests Passed</Label>
                            <Input v-model.number="editFeatureForm.tests_passed" type="number" min="0" class="mt-1" />
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showEditFeatureDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button @click="updateFeature" class="cursor-pointer">Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Link Test Run Dialog -->
        <Dialog v-model:open="showLinkTestRunDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Link2 class="h-5 w-5" />
                        Link Test Runs
                    </DialogTitle>
                    <DialogDescription>Toggle test runs to link or unlink from this release.</DialogDescription>
                </DialogHeader>
                <Deferred data="projectTestRuns">
                    <template #fallback>
                        <div class="space-y-2 py-4">
                            <div v-for="i in 3" :key="i" class="h-10 w-full animate-pulse rounded-md bg-muted" />
                        </div>
                    </template>
                    <div class="max-h-96 space-y-1 overflow-y-auto py-4">
                        <div
                            v-for="tr in projectTestRuns"
                            :key="tr.id"
                            class="flex items-center justify-between rounded border px-3 py-2 transition-colors hover:bg-muted/30"
                        >
                            <div class="flex items-center gap-2">
                                <Play class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm">{{ tr.name }}</span>
                                <Badge variant="outline" class="text-xs">{{ tr.status }}</Badge>
                            </div>
                            <Button
                                v-if="isTestRunLinked(tr.id)"
                                variant="outline"
                                size="sm"
                                @click="toggleTestRunLink(tr.id)"
                                class="cursor-pointer border-emerald-500 text-emerald-600 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/20"
                            >
                                <Unlink class="mr-1 h-3.5 w-3.5" />
                                Linked
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                @click="toggleTestRunLink(tr.id)"
                                class="cursor-pointer"
                            >
                                <Link2 class="mr-1 h-3.5 w-3.5" />
                                Link
                            </Button>
                        </div>
                        <p v-if="projectTestRuns?.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            No test runs available.
                        </p>
                    </div>
                </Deferred>
                <DialogFooter>
                    <Button @click="showLinkTestRunDialog = false" class="cursor-pointer">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Checklist Item Dialog -->
        <Dialog v-model:open="showAddChecklistDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Checklist Item</DialogTitle>
                    <DialogDescription>Add a new item to the release checklist.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div>
                        <Label>Title *</Label>
                        <Input v-model="checklistForm.title" placeholder="Checklist item title" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Category</Label>
                            <Select v-model="checklistForm.category">
                                <SelectTrigger class="mt-1"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="testing">Testing</SelectItem>
                                    <SelectItem value="security">Security</SelectItem>
                                    <SelectItem value="performance">Performance</SelectItem>
                                    <SelectItem value="deployment">Deployment</SelectItem>
                                    <SelectItem value="documentation">Documentation</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Priority</Label>
                            <Select v-model="checklistForm.priority">
                                <SelectTrigger class="mt-1"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="critical">Critical</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="low">Low</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea v-model="checklistForm.description" placeholder="Optional description..." class="mt-1" rows="2" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            :checked="checklistForm.is_blocker"
                            @update:checked="checklistForm.is_blocker = $event"
                            id="add-is-blocker"
                        />
                        <Label for="add-is-blocker" class="cursor-pointer">Release blocker</Label>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddChecklistDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button @click="addChecklistItem" :disabled="!checklistForm.title.trim()" class="cursor-pointer">Add</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Checklist Item Dialog -->
        <Dialog v-model:open="showEditChecklistDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Checklist Item</DialogTitle>
                    <DialogDescription>Update the checklist item details.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div>
                        <Label>Title</Label>
                        <Input v-model="editChecklistForm.title" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Category</Label>
                            <Select v-model="editChecklistForm.category">
                                <SelectTrigger class="mt-1"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="testing">Testing</SelectItem>
                                    <SelectItem value="security">Security</SelectItem>
                                    <SelectItem value="performance">Performance</SelectItem>
                                    <SelectItem value="deployment">Deployment</SelectItem>
                                    <SelectItem value="documentation">Documentation</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Priority</Label>
                            <Select v-model="editChecklistForm.priority">
                                <SelectTrigger class="mt-1"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="critical">Critical</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="low">Low</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea v-model="editChecklistForm.description" placeholder="Optional description..." class="mt-1" rows="2" />
                    </div>
                    <div>
                        <Label>Notes</Label>
                        <Textarea v-model="editChecklistForm.notes" placeholder="Add notes..." class="mt-1" rows="2" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            :checked="editChecklistForm.is_blocker"
                            @update:checked="editChecklistForm.is_blocker = $event"
                            id="edit-is-blocker"
                        />
                        <Label for="edit-is-blocker" class="cursor-pointer">Release blocker</Label>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showEditChecklistDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button @click="saveChecklistItem" class="cursor-pointer">Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Checklist Item Dialog -->
        <Dialog v-model:open="showDeleteChecklistDialog">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Delete Checklist Item?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ checklistItemToDelete?.title }}"? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-4 sm:justify-end">
                    <Button variant="secondary" @click="showDeleteChecklistDialog = false" class="flex-1 sm:flex-none cursor-pointer">
                        No
                    </Button>
                    <Button variant="destructive" @click="deleteChecklistItem" class="flex-1 sm:flex-none cursor-pointer">
                        Yes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
