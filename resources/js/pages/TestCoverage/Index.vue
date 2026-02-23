<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import type {
    ProjectFeature,
    CoverageAnalysis,
    CoverageModuleStats,
    CoverageStatistics,
    CoverageGap,
    AIGap,
    AIAnalysisData,
    TestCaseSummary,
    Checklist,
} from '@/types/checkmate';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Progress } from '@/components/ui/progress';
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
    BarChart3,
    Plus,
    RefreshCw,
    Search,
    X,
    AlertTriangle,
    CheckCircle,
    Target,
    Brain,
    TrendingUp,
    Shield,
    Loader2,
    Trash2,
    Edit,
    ChevronRight,
    ChevronDown,
    Sparkles,
    Eye,
    Link2,
    Wand2,
    Unlink,
    FileText,
    ClipboardList,
} from 'lucide-vue-next';
import { Checkbox } from '@/components/ui/checkbox';
import { ref, computed, watch } from 'vue';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { priorityVariant } from '@/lib/badge-variants';

const MODULE_OPTIONS = ['UI', 'API', 'Backend', 'Database', 'Integration'] as const;

const props = defineProps<{
    project: Project;
    statistics: CoverageStatistics;
    coverageByModule: CoverageModuleStats[];
    latestAnalysis: CoverageAnalysis | null;
    features: ProjectFeature[];
    gaps: CoverageGap[];
    hasAnthropicKey: boolean;
    allTestCases: TestCaseSummary[];
    allChecklists: Pick<Checklist, 'id' | 'name'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Coverage', href: `/projects/${props.project.id}/test-coverage` },
];

// Tabs
type TabKey = 'overview' | 'ai-analysis' | 'gaps' | 'recommendations' | 'history';
const activeTab = ref<TabKey>('overview');
const tabs: { key: TabKey; label: string; icon: typeof BarChart3 }[] = [
    { key: 'overview', label: 'Overview', icon: BarChart3 },
    { key: 'ai-analysis', label: 'AI Analysis', icon: Brain },
    { key: 'gaps', label: 'Coverage Gaps', icon: AlertTriangle },
    { key: 'recommendations', label: 'Recommendations', icon: Target },
    { key: 'history', label: 'History', icon: TrendingUp },
];

// AI Analysis state
const isAnalyzing = ref(false);
const analysisResults = ref<AIAnalysisData | null>(props.latestAnalysis?.analysis_data as AIAnalysisData | null);
const generatingForGap = ref<string | null>(null);
const generatedTestCases = ref<Record<string, unknown>[]>([]);
const showGeneratedModal = ref(false);
const currentGapName = ref('');

// Manage features
const showAddFeatureDialog = ref(false);
const featureForm = ref({
    name: '',
    description: '',
    module: [] as string[],
    category: '',
    priority: 'medium' as string,
});
const editingFeature = ref<ProjectFeature | null>(null);
const showEditFeatureDialog = ref(false);
const editForm = ref({
    name: '',
    description: '',
    module: [] as string[],
    category: '',
    priority: 'medium' as string,
});

// Search
const featureSearch = ref('');

// Selected feature filter
const selectedFeatureId = ref<number | null>(null);

const selectedFeature = computed(() => {
    if (!selectedFeatureId.value) return null;
    return props.features.find((f) => f.id === selectedFeatureId.value) ?? null;
});

const selectFeature = (featureId: number) => {
    selectedFeatureId.value = selectedFeatureId.value === featureId ? null : featureId;
};

const clearFeatureSelection = () => {
    selectedFeatureId.value = null;
};

const displayedCoverageByModule = computed(() => {
    if (!selectedFeature.value) return props.coverageByModule;

    const f = selectedFeature.value;
    const isCovered = (f.test_cases_count ?? 0) > 0 || (f.checklists_count ?? 0) > 0;

    // Build module stats from test case modules
    const moduleMap = new Map<string, { test_cases_count: number; checklists_count: number }>();

    // Seed with feature-level modules
    const featureModules = f.module?.length ? f.module : ['Uncategorized'];
    for (const mod of featureModules) {
        moduleMap.set(mod, { test_cases_count: 0, checklists_count: f.checklists_count ?? 0 });
    }

    // Count test cases by their own module
    for (const tc of f.test_cases ?? []) {
        const tcModules = tc.module?.length ? tc.module : featureModules;
        for (const mod of tcModules) {
            const existing = moduleMap.get(mod);
            if (existing) {
                existing.test_cases_count++;
            } else {
                moduleMap.set(mod, { test_cases_count: 1, checklists_count: 0 });
            }
        }
    }

    return Array.from(moduleMap.entries()).map(([mod, stats]) => ({
        module: mod,
        total_features: 1,
        covered_features: isCovered ? 1 : 0,
        test_cases_count: stats.test_cases_count,
        checklists_count: stats.checklists_count,
        coverage_percentage: isCovered ? 100 : 0,
    }));
});

const filteredFeatures = computed(() => {
    let results = props.features;
    if (featureSearch.value) {
        const q = featureSearch.value.toLowerCase();
        results = results.filter(
            (f) =>
                f.name.toLowerCase().includes(q) ||
                f.module?.some((m) => m.toLowerCase().includes(q)) ||
                f.category?.toLowerCase().includes(q),
        );
    }
    return results;
});

// Coverage history
const coverageHistory = ref<{ date: string; coverage: number; features: number; gaps: number }[]>([]);
const loadingHistory = ref(false);

const loadHistory = async () => {
    if (coverageHistory.value.length > 0) return;
    loadingHistory.value = true;
    try {
        const response = await fetch(`/projects/${props.project.id}/test-coverage/history`);
        coverageHistory.value = await response.json();
    } finally {
        loadingHistory.value = false;
    }
};

watch(activeTab, (tab) => {
    if (tab === 'history') {
        loadHistory();
    }
});

// Helpers
const getCoverageColor = (coverage: number): string => {
    if (coverage >= 80) return 'text-emerald-600 dark:text-emerald-400';
    if (coverage >= 50) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
};

const getCoverageBg = (coverage: number): string => {
    if (coverage >= 80) return 'bg-emerald-500';
    if (coverage >= 50) return 'bg-amber-500';
    return 'bg-red-500';
};

const formatDate = (date: string | null): string => {
    if (!date) return 'Never';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

// AI Analysis
const runAnalysis = async () => {
    isAnalyzing.value = true;
    try {
        const response = await fetch(`/projects/${props.project.id}/test-coverage/ai-analysis`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json();
        analysisResults.value = data.analysis;
        activeTab.value = 'recommendations';
        router.reload({ only: ['statistics', 'coverageByModule', 'latestAnalysis', 'features', 'gaps'] });
    } catch (error) {
        console.error('Analysis error:', error);
    } finally {
        isAnalyzing.value = false;
    }
};

const generateTestCases = async (gap: AIGap) => {
    generatingForGap.value = gap.id;
    currentGapName.value = gap.feature;
    try {
        const response = await fetch(`/projects/${props.project.id}/test-coverage/generate-test-cases`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(gap),
        });
        const data = await response.json();
        generatedTestCases.value = data.test_cases;
        showGeneratedModal.value = true;
    } catch (error) {
        console.error('Generation error:', error);
    } finally {
        generatingForGap.value = null;
    }
};

// Feature CRUD
const addFeature = () => {
    router.post(`/projects/${props.project.id}/test-coverage/features`, featureForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showAddFeatureDialog.value = false;
            featureForm.value = { name: '', description: '', module: [], category: '', priority: 'medium' };
        },
    });
};

const startEdit = (feature: ProjectFeature) => {
    editingFeature.value = feature;
    editForm.value = {
        name: feature.name,
        description: feature.description || '',
        module: feature.module || [],
        category: feature.category || '',
        priority: feature.priority,
    };
    showEditFeatureDialog.value = true;
};

const updateFeature = () => {
    if (!editingFeature.value) return;
    router.put(`/projects/${props.project.id}/test-coverage/features/${editingFeature.value.id}`, editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showEditFeatureDialog.value = false;
            editingFeature.value = null;
        },
    });
};

const deleteFeature = (featureId: number) => {
    router.delete(`/projects/${props.project.id}/test-coverage/features/${featureId}`, {
        preserveScroll: true,
    });
};

// Expanded feature rows
const expandedFeatureId = ref<number | null>(null);
const toggleExpanded = (featureId: number) => {
    expandedFeatureId.value = expandedFeatureId.value === featureId ? null : featureId;
};

// Link Test Cases dialog
const showLinkTestCasesDialog = ref(false);
const linkingTestCaseFeature = ref<ProjectFeature | null>(null);
const linkTestCaseSearch = ref('');
const linkTestCaseSuiteFilter = ref('all');
const pendingTestCaseIds = ref(new Set<number>());
const linkedTestCaseSnapshot = ref(new Set<number>());

const availableSuitesForLink = computed(() => {
    const suiteMap = new Map<number, string>();
    for (const tc of props.allTestCases) {
        if (tc.test_suite) {
            suiteMap.set(tc.test_suite.id, tc.test_suite.name);
        }
    }
    return Array.from(suiteMap, ([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name));
});

const filteredAllTestCases = computed(() => {
    let results = props.allTestCases;

    if (linkTestCaseSuiteFilter.value !== 'all') {
        const suiteId = Number(linkTestCaseSuiteFilter.value);
        results = results.filter((tc) => tc.test_suite?.id === suiteId);
    }

    if (linkTestCaseSearch.value) {
        const q = linkTestCaseSearch.value.toLowerCase();
        results = results.filter(
            (tc) => tc.title.toLowerCase().includes(q) || tc.test_suite?.name.toLowerCase().includes(q),
        );
    }

    return results;
});

const isLinked = (_feature: ProjectFeature, testCaseId: number): boolean => {
    return linkedTestCaseSnapshot.value.has(testCaseId);
};

const openLinkTestCasesDialog = (feature: ProjectFeature) => {
    linkingTestCaseFeature.value = feature;
    linkTestCaseSearch.value = '';
    linkTestCaseSuiteFilter.value = 'all';
    linkedTestCaseSnapshot.value = new Set(feature.test_cases?.map((tc) => tc.id) ?? []);
    showLinkTestCasesDialog.value = true;
};

const toggleLink = (testCaseId: number) => {
    if (!linkingTestCaseFeature.value) return;
    const feature = linkingTestCaseFeature.value;
    const wasLinked = linkedTestCaseSnapshot.value.has(testCaseId);
    pendingTestCaseIds.value.add(testCaseId);

    const onFinish = () => {
        pendingTestCaseIds.value.delete(testCaseId);
        if (wasLinked) {
            linkedTestCaseSnapshot.value.delete(testCaseId);
        } else {
            linkedTestCaseSnapshot.value.add(testCaseId);
        }
    };

    if (wasLinked) {
        router.delete(`/projects/${props.project.id}/test-coverage/features/${feature.id}/test-cases/${testCaseId}`, {
            preserveScroll: true,
            onFinish,
        });
    } else {
        router.post(
            `/projects/${props.project.id}/test-coverage/features/${feature.id}/link-test-case`,
            { test_case_id: testCaseId },
            { preserveScroll: true, onFinish },
        );
    }
};

const unlinkTestCase = (featureId: number, testCaseId: number) => {
    router.delete(`/projects/${props.project.id}/test-coverage/features/${featureId}/test-cases/${testCaseId}`, {
        preserveScroll: true,
    });
};

// Link Checklists dialog
const showLinkChecklistsDialog = ref(false);
const linkingChecklistFeature = ref<ProjectFeature | null>(null);
const linkChecklistSearch = ref('');
const pendingChecklistIds = ref(new Set<number>());
const linkedChecklistSnapshot = ref(new Set<number>());

const filteredAllChecklists = computed(() => {
    if (!linkChecklistSearch.value) return props.allChecklists;
    const q = linkChecklistSearch.value.toLowerCase();
    return props.allChecklists.filter((cl) => cl.name.toLowerCase().includes(q));
});

const isChecklistLinked = (_feature: ProjectFeature, checklistId: number): boolean => {
    return linkedChecklistSnapshot.value.has(checklistId);
};

const openLinkChecklistsDialog = (feature: ProjectFeature) => {
    linkingChecklistFeature.value = feature;
    linkChecklistSearch.value = '';
    linkedChecklistSnapshot.value = new Set(feature.checklists?.map((cl) => cl.id) ?? []);
    showLinkChecklistsDialog.value = true;
};

const toggleChecklistLink = (checklistId: number) => {
    if (!linkingChecklistFeature.value) return;
    const feature = linkingChecklistFeature.value;
    const wasLinked = linkedChecklistSnapshot.value.has(checklistId);
    pendingChecklistIds.value.add(checklistId);

    const onFinish = () => {
        pendingChecklistIds.value.delete(checklistId);
        if (wasLinked) {
            linkedChecklistSnapshot.value.delete(checklistId);
        } else {
            linkedChecklistSnapshot.value.add(checklistId);
        }
    };

    if (wasLinked) {
        router.delete(`/projects/${props.project.id}/test-coverage/features/${feature.id}/checklists/${checklistId}`, {
            preserveScroll: true,
            onFinish,
        });
    } else {
        router.post(
            `/projects/${props.project.id}/test-coverage/features/${feature.id}/link-checklist`,
            { checklist_id: checklistId },
            { preserveScroll: true, onFinish },
        );
    }
};

const unlinkChecklist = (featureId: number, checklistId: number) => {
    router.delete(`/projects/${props.project.id}/test-coverage/features/${featureId}/checklists/${checklistId}`, {
        preserveScroll: true,
    });
};

// Auto-link
const autoLinkSingle = (featureId: number) => {
    router.post(`/projects/${props.project.id}/test-coverage/features/${featureId}/auto-link`, {}, {
        preserveScroll: true,
    });
};

const autoLinkAll = () => {
    router.post(`/projects/${props.project.id}/test-coverage/auto-link-all`, {}, {
        preserveScroll: true,
    });
};

const refreshData = () => {
    router.reload({ only: ['statistics', 'coverageByModule', 'latestAnalysis', 'features', 'gaps', 'allTestCases', 'allChecklists'] });
};
</script>

<template>
    <Head :title="`Test Coverage - ${project.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold text-foreground">
                        <BarChart3 class="h-6 w-6" />
                        Test Coverage Analytics
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">AI-powered insights into your test coverage</p>
                </div>
                <div class="flex gap-2">
                    <RestrictedAction>
                        <Button variant="outline" @click="autoLinkAll" class="cursor-pointer">
                            <Wand2 class="mr-1 h-4 w-4" />
                            Auto-Link All
                        </Button>
                    </RestrictedAction>
                    <RestrictedAction>
                        <Button variant="outline" @click="showAddFeatureDialog = true" class="cursor-pointer">
                            <Plus class="mr-1 h-4 w-4" />
                            Add Feature
                        </Button>
                    </RestrictedAction>
                    <Button variant="outline" @click="refreshData" class="cursor-pointer">
                        <RefreshCw class="mr-1 h-4 w-4" />
                        Refresh
                    </Button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                <Card>
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-lg bg-primary/10 p-3">
                            <BarChart3 class="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Overall Coverage</p>
                            <p class="text-2xl font-bold" :class="getCoverageColor(statistics.overall_coverage)">
                                {{ statistics.overall_coverage }}%
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-lg bg-blue-500/10 p-3">
                            <FileText class="h-6 w-6 text-blue-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Total Test Cases</p>
                            <p class="text-2xl font-bold text-foreground">{{ statistics.total_test_cases }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-lg bg-violet-500/10 p-3">
                            <ClipboardList class="h-6 w-6 text-violet-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Total Checklists</p>
                            <p class="text-2xl font-bold text-foreground">{{ statistics.total_checklists }}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-lg bg-emerald-500/10 p-3">
                            <CheckCircle class="h-6 w-6 text-emerald-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Features Covered</p>
                            <p class="text-2xl font-bold text-foreground">
                                {{ statistics.covered_features }}
                                <span class="text-sm font-normal text-muted-foreground">/ {{ statistics.total_features }}</span>
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="rounded-lg bg-amber-500/10 p-3">
                            <AlertTriangle class="h-6 w-6 text-amber-500" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Coverage Gaps</p>
                            <p class="text-2xl font-bold text-foreground">{{ statistics.gaps_count }}</p>
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
                            :class="
                                activeTab === tab.key
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:border-muted-foreground/30 hover:text-foreground'
                            "
                        >
                            <component :is="tab.icon" class="h-4 w-4" />
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <!-- Tab: Overview -->
                <div v-if="activeTab === 'overview'" class="p-6">
                    <!-- Selected feature banner -->
                    <div
                        v-if="selectedFeature"
                        class="mb-6 flex items-center justify-between rounded-lg border border-primary/30 bg-primary/5 px-4 py-3"
                    >
                        <div class="flex items-center gap-3">
                            <Target class="h-5 w-5 text-primary" />
                            <div>
                                <span class="font-medium text-foreground">{{ selectedFeature.name }}</span>
                                <span class="ml-2 text-sm text-muted-foreground">
                                    {{ selectedFeature.test_cases_count ?? 0 }} test cases<span v-if="(selectedFeature.checklists_count ?? 0) > 0"> · {{ selectedFeature.checklists_count }} checklists</span>
                                </span>
                            </div>
                        </div>
                        <Button variant="outline" size="sm" class="cursor-pointer" @click="clearFeatureSelection">
                            <X class="mr-1 h-3.5 w-3.5" />
                            Show All
                        </Button>
                    </div>

                    <!-- Coverage by Module -->
                    <div v-if="displayedCoverageByModule.length > 0" class="mb-8">
                        <h3 class="mb-4 text-lg font-semibold text-foreground">
                            {{ selectedFeature ? 'Coverage in Feature Modules' : 'Coverage by Module' }}
                        </h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <Card v-for="mod in displayedCoverageByModule" :key="mod.module">
                                <CardContent class="p-5">
                                    <div class="mb-3 flex items-center justify-between">
                                        <h4 class="font-medium text-foreground">{{ mod.module }}</h4>
                                        <span class="text-xl font-bold" :class="getCoverageColor(mod.coverage_percentage)">
                                            {{ mod.coverage_percentage }}%
                                        </span>
                                    </div>
                                    <Progress :model-value="mod.coverage_percentage" class="mb-2" />
                                    <div class="flex justify-between text-xs text-muted-foreground">
                                        <span>{{ mod.covered_features }} / {{ mod.total_features }} features</span>
                                        <span>{{ mod.test_cases_count }} test cases<span v-if="mod.checklists_count"> · {{ mod.checklists_count }} checklists</span></span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <!-- Features list -->
                    <div>
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-foreground">Project Features</h3>
                            <div class="relative w-64">
                                <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="featureSearch"
                                    placeholder="Search features..."
                                    class="pl-9"
                                />
                                <button
                                    v-if="featureSearch"
                                    @click="featureSearch = ''"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div v-if="filteredFeatures.length === 0" class="py-12 text-center text-muted-foreground">
                            <Target class="mx-auto mb-3 h-12 w-12 opacity-30" />
                            <p class="text-lg font-medium">No features defined yet</p>
                            <p class="mt-1 text-sm">Add project features to start tracking test coverage</p>
                        </div>

                        <div v-else class="space-y-2">
                            <div v-for="feature in filteredFeatures" :key="feature.id">
                                <div
                                    class="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-muted/30"
                                    :class="{
                                        'rounded-b-none border-b-0': expandedFeatureId === feature.id,
                                        'border-primary/50 bg-primary/5': selectedFeatureId === feature.id,
                                    }"
                                >
                                    <div class="flex-1 cursor-pointer" @click="selectFeature(feature.id)">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-foreground">{{ feature.name }}</span>
                                            <Badge :variant="priorityVariant(feature.priority)" class="text-xs uppercase">
                                                {{ feature.priority }}
                                            </Badge>
                                            <Badge v-for="mod in (feature.module || [])" :key="mod" variant="outline" class="text-xs">
                                                {{ mod }}
                                            </Badge>
                                            <Badge v-if="feature.category" variant="secondary" class="text-xs">
                                                {{ feature.category }}
                                            </Badge>
                                        </div>
                                        <p v-if="feature.description" class="mt-1 text-sm text-muted-foreground">
                                            {{ feature.description }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button
                                            @click="toggleExpanded(feature.id)"
                                            class="cursor-pointer text-sm font-medium"
                                            :class="(feature.test_cases_count ?? 0) > 0 || (feature.checklists_count ?? 0) > 0 ? 'text-emerald-600 dark:text-emerald-400 hover:underline' : 'text-muted-foreground'"
                                        >
                                            <span class="flex items-center gap-1">
                                                <component
                                                    :is="expandedFeatureId === feature.id ? ChevronDown : ChevronRight"
                                                    class="h-4 w-4"
                                                />
                                                {{ feature.test_cases_count ?? 0 }} test cases<span v-if="(feature.checklists_count ?? 0) > 0"> · {{ feature.checklists_count }} checklists</span>
                                            </span>
                                        </button>
                                        <RestrictedAction>
                                            <Button variant="ghost" size="sm" @click="autoLinkSingle(feature.id)" class="cursor-pointer" title="Auto-link matching test cases">
                                                <Wand2 class="h-4 w-4" />
                                            </Button>
                                        </RestrictedAction>
                                        <RestrictedAction>
                                            <Button variant="ghost" size="sm" @click="openLinkTestCasesDialog(feature)" class="cursor-pointer" title="Link test cases">
                                                <Link2 class="h-4 w-4" />
                                            </Button>
                                        </RestrictedAction>
                                        <RestrictedAction>
                                            <Button variant="ghost" size="sm" @click="openLinkChecklistsDialog(feature)" class="cursor-pointer" title="Link checklists">
                                                <ClipboardList class="h-4 w-4" />
                                            </Button>
                                        </RestrictedAction>
                                        <RestrictedAction>
                                            <Button variant="ghost" size="sm" @click="startEdit(feature)" class="cursor-pointer">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </RestrictedAction>
                                        <RestrictedAction>
                                            <Button variant="ghost" size="sm" @click="deleteFeature(feature.id)" class="cursor-pointer text-destructive hover:text-destructive">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </RestrictedAction>
                                    </div>
                                </div>
                                <!-- Expanded linked test cases & checklists -->
                                <div
                                    v-if="expandedFeatureId === feature.id"
                                    class="rounded-b-lg border border-t-0 bg-muted/20 px-4 py-3"
                                >
                                    <div v-if="feature.test_cases && feature.test_cases.length > 0" class="space-y-2">
                                        <div
                                            v-for="tc in feature.test_cases"
                                            :key="tc.id"
                                            class="flex items-center justify-between rounded border bg-background px-3 py-2"
                                        >
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-muted/50">
                                                    <FileText class="h-3.5 w-3.5 text-muted-foreground" />
                                                </div>
                                                <span class="text-sm text-foreground">{{ tc.title }}</span>
                                                <Badge v-if="tc.test_suite" variant="outline" class="text-xs">
                                                    {{ tc.test_suite.name }}
                                                </Badge>
                                            </div>
                                            <RestrictedAction>
                                                <button
                                                    @click="unlinkTestCase(feature.id, tc.id)"
                                                    class="cursor-pointer text-muted-foreground hover:text-destructive"
                                                    title="Unlink test case"
                                                >
                                                    <X class="h-4 w-4" />
                                                </button>
                                            </RestrictedAction>
                                        </div>
                                    </div>
                                    <div v-if="feature.checklists && feature.checklists.length > 0" class="space-y-2" :class="{ 'mt-3 border-t pt-3': feature.test_cases && feature.test_cases.length > 0 }">
                                        <p class="text-xs font-medium text-muted-foreground uppercase">Checklists</p>
                                        <div
                                            v-for="cl in feature.checklists"
                                            :key="cl.id"
                                            class="flex items-center justify-between rounded border bg-background px-3 py-2"
                                        >
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-violet-500/10">
                                                    <ClipboardList class="h-3.5 w-3.5 text-violet-500" />
                                                </div>
                                                <a :href="`/projects/${project.id}/checklists/${cl.id}`" class="text-sm text-foreground hover:underline">{{ cl.name }}</a>
                                            </div>
                                            <RestrictedAction>
                                                <button
                                                    @click="unlinkChecklist(feature.id, cl.id)"
                                                    class="cursor-pointer text-muted-foreground hover:text-destructive"
                                                    title="Unlink checklist"
                                                >
                                                    <X class="h-4 w-4" />
                                                </button>
                                            </RestrictedAction>
                                        </div>
                                    </div>
                                    <p v-if="(!feature.test_cases || feature.test_cases.length === 0) && (!feature.checklists || feature.checklists.length === 0)" class="text-sm text-muted-foreground">No test cases or checklists linked. Use auto-link or link manually.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: AI Analysis -->
                <div v-if="activeTab === 'ai-analysis'" class="p-6">
                    <!-- AI Analysis Trigger -->
                    <div class="mb-8 rounded-lg bg-gradient-to-r from-violet-600 to-blue-600 p-8 text-white">
                        <div class="flex items-center justify-between gap-6">
                            <div class="flex-1">
                                <h2 class="mb-2 flex items-center gap-2 text-2xl font-bold">
                                    <Sparkles class="h-6 w-6" />
                                    AI-Powered Coverage Analysis
                                </h2>
                                <p class="text-violet-100">
                                    Leverage Claude AI to analyze your test coverage, identify gaps, and get intelligent recommendations.
                                </p>
                                <div v-if="!hasAnthropicKey" class="mt-4 rounded-lg border border-red-300 bg-red-500/20 p-3">
                                    <p class="text-sm">
                                        Anthropic API key not configured. Add ANTHROPIC_API_KEY to your .env file.
                                    </p>
                                </div>
                                <p v-if="latestAnalysis?.analyzed_at" class="mt-2 text-sm text-violet-200">
                                    Last analysis: {{ formatDate(latestAnalysis.analyzed_at) }}
                                </p>
                            </div>
                            <RestrictedAction>
                                <Button
                                    @click="runAnalysis"
                                    :disabled="isAnalyzing || !hasAnthropicKey"
                                    class="cursor-pointer bg-white px-8 py-6 font-bold text-violet-600 hover:bg-violet-50"
                                >
                                    <Loader2 v-if="isAnalyzing" class="mr-2 h-5 w-5 animate-spin" />
                                    <Search v-else class="mr-2 h-5 w-5" />
                                    {{ isAnalyzing ? 'Analyzing...' : 'Run AI Analysis' }}
                                </Button>
                            </RestrictedAction>
                        </div>
                    </div>

                    <!-- Analysis Results -->
                    <div v-if="analysisResults" class="space-y-6">
                        <!-- Summary -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Eye class="h-5 w-5" />
                                    Analysis Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="leading-relaxed text-muted-foreground">{{ analysisResults.summary }}</p>
                            </CardContent>
                        </Card>

                        <!-- Coverage by Category -->
                        <Card v-if="analysisResults.coverage_by_category">
                            <CardHeader>
                                <CardTitle>Coverage by Category</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                                    <div
                                        v-for="(percentage, category) in analysisResults.coverage_by_category"
                                        :key="category"
                                        class="text-center"
                                    >
                                        <div class="mb-1 text-2xl font-bold" :class="getCoverageColor(percentage as number)">
                                            {{ percentage }}%
                                        </div>
                                        <div class="mb-2 text-xs capitalize text-muted-foreground">{{ category }}</div>
                                        <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="getCoverageBg(percentage as number)"
                                                :style="{ width: percentage + '%' }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Well-Covered Areas -->
                        <Card v-if="analysisResults.well_covered?.length">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <CheckCircle class="h-5 w-5 text-emerald-500" />
                                    Well-Covered Areas ({{ analysisResults.well_covered.length }})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div
                                        v-for="area in analysisResults.well_covered"
                                        :key="area.feature"
                                        class="rounded-lg border-l-4 border-emerald-500 bg-emerald-50 p-4 dark:bg-emerald-950/20"
                                    >
                                        <div class="mb-2 flex items-center justify-between">
                                            <h4 class="font-bold text-emerald-900 dark:text-emerald-100">{{ area.feature }}</h4>
                                            <span class="text-xl font-bold text-emerald-600">{{ area.coverage }}%</span>
                                        </div>
                                        <p class="mb-1 text-sm text-emerald-700 dark:text-emerald-300">{{ area.test_count }} test cases</p>
                                        <p class="text-sm text-muted-foreground">{{ area.strength }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Risks -->
                        <Card v-if="analysisResults.risks?.length">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Shield class="h-5 w-5 text-amber-500" />
                                    Risk Assessment ({{ analysisResults.risks.length }})
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-for="risk in analysisResults.risks"
                                    :key="risk.id"
                                    class="rounded-lg border bg-amber-50/50 p-4 dark:bg-amber-950/10"
                                >
                                    <div class="mb-2 flex items-center gap-2">
                                        <h4 class="font-bold text-foreground">{{ risk.area }}</h4>
                                        <Badge :variant="priorityVariant(risk.level)" class="uppercase">
                                            {{ risk.level }}
                                        </Badge>
                                    </div>
                                    <p class="mb-1 text-sm text-muted-foreground">
                                        <strong>Reason:</strong> {{ risk.reason }}
                                    </p>
                                    <p class="mb-1 text-sm text-muted-foreground">
                                        <strong>Impact:</strong> {{ risk.impact }}
                                    </p>
                                    <p class="text-sm text-blue-600 dark:text-blue-400">
                                        <strong>Recommendation:</strong> {{ risk.recommendation }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="py-16 text-center">
                        <Brain class="mx-auto mb-4 h-16 w-16 text-muted-foreground/30" />
                        <p class="text-lg font-medium text-muted-foreground">No AI analysis yet</p>
                        <p class="mt-1 text-sm text-muted-foreground">Run an AI analysis to get intelligent coverage insights</p>
                    </div>
                </div>

                <!-- Tab: Coverage Gaps -->
                <div v-if="activeTab === 'gaps'" class="p-6">
                    <div v-if="analysisResults?.gaps?.length" class="space-y-4">
                        <h3 class="flex items-center gap-2 text-lg font-semibold text-foreground">
                            <AlertTriangle class="h-5 w-5 text-amber-500" />
                            Coverage Gaps ({{ analysisResults.gaps.length }})
                        </h3>
                        <div
                            v-for="gap in analysisResults.gaps"
                            :key="gap.id"
                            class="rounded-r-lg border-l-4 p-5 transition-shadow hover:shadow-md"
                            :class="{
                                'border-red-600 bg-red-50 dark:bg-red-950/10': gap.priority === 'critical',
                                'border-orange-500 bg-orange-50 dark:bg-orange-950/10': gap.priority === 'high',
                                'border-amber-500 bg-amber-50 dark:bg-amber-950/10': gap.priority === 'medium',
                                'border-blue-500 bg-blue-50 dark:bg-blue-950/10': gap.priority === 'low',
                            }"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="mb-2 flex items-center gap-3">
                                        <h4 class="text-lg font-bold text-foreground">{{ gap.feature }}</h4>
                                        <Badge :variant="priorityVariant(gap.priority)" class="uppercase">
                                            {{ gap.priority }}
                                        </Badge>
                                        <Badge v-if="gap.category" variant="outline">{{ gap.category }}</Badge>
                                    </div>
                                    <p class="mb-3 text-muted-foreground">{{ gap.description }}</p>
                                    <div class="space-y-1 text-sm text-muted-foreground">
                                        <p><strong>Module:</strong> {{ gap.module || 'N/A' }}</p>
                                        <p><strong>Suggested tests:</strong> {{ gap.suggested_test_count || 3 }}</p>
                                        <p><strong>Reasoning:</strong> {{ gap.reasoning }}</p>
                                    </div>
                                </div>
                                <RestrictedAction>
                                    <Button
                                        @click="generateTestCases(gap)"
                                        :disabled="generatingForGap === gap.id"
                                        class="cursor-pointer whitespace-nowrap"
                                    >
                                        <Loader2 v-if="generatingForGap === gap.id" class="mr-1 h-4 w-4 animate-spin" />
                                        <Sparkles v-else class="mr-1 h-4 w-4" />
                                        {{ generatingForGap === gap.id ? 'Generating...' : 'Generate Tests' }}
                                    </Button>
                                </RestrictedAction>
                            </div>
                        </div>
                    </div>

                    <!-- DB-sourced gaps (features without test cases) -->
                    <div v-else-if="gaps.length" class="space-y-4">
                        <h3 class="flex items-center gap-2 text-lg font-semibold text-foreground">
                            <AlertTriangle class="h-5 w-5 text-amber-500" />
                            Uncovered Features ({{ gaps.length }})
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            These project features have no linked test cases or checklists. Run an AI analysis for detailed gap insights.
                        </p>
                        <div
                            v-for="gap in gaps"
                            :key="gap.id"
                            class="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-foreground">{{ gap.feature }}</span>
                                    <Badge :variant="priorityVariant(gap.priority)" class="text-xs uppercase">
                                        {{ gap.priority }}
                                    </Badge>
                                    <Badge v-for="mod in (gap.module || [])" :key="mod" variant="outline" class="text-xs">{{ mod }}</Badge>
                                </div>
                                <p v-if="gap.description" class="mt-1 text-sm text-muted-foreground">{{ gap.description }}</p>
                            </div>
                            <ChevronRight class="h-5 w-5 text-muted-foreground" />
                        </div>
                    </div>

                    <div v-else class="py-16 text-center">
                        <CheckCircle class="mx-auto mb-4 h-16 w-16 text-emerald-500/30" />
                        <p class="text-lg font-medium text-muted-foreground">No coverage gaps</p>
                        <p class="mt-1 text-sm text-muted-foreground">All features are covered or no features defined yet</p>
                    </div>
                </div>

                <!-- Tab: Recommendations -->
                <div v-if="activeTab === 'recommendations'" class="p-6">
                    <div v-if="analysisResults?.recommendations?.length" class="space-y-4">
                        <h3 class="mb-4 text-lg font-semibold text-foreground">AI Recommendations</h3>
                        <div
                            v-for="(rec, index) in analysisResults.recommendations"
                            :key="index"
                            class="flex items-start gap-4 rounded-lg border p-5"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
                            >
                                {{ rec.priority }}
                            </div>
                            <div class="flex-1">
                                <h4 class="font-medium text-foreground">{{ rec.action }}</h4>
                                <p class="mt-1 text-sm text-muted-foreground">{{ rec.benefit }}</p>
                            </div>
                            <Badge variant="outline" class="uppercase">{{ rec.effort }} effort</Badge>
                        </div>
                    </div>
                    <div v-else class="py-16 text-center">
                        <Target class="mx-auto mb-4 h-16 w-16 text-muted-foreground/30" />
                        <p class="text-lg font-medium text-muted-foreground">No recommendations yet</p>
                        <p class="mt-1 text-sm text-muted-foreground">Run an AI analysis to get recommendations</p>
                    </div>
                </div>

                <!-- Tab: History -->
                <div v-if="activeTab === 'history'" class="p-6">
                    <div v-if="loadingHistory" class="flex items-center justify-center py-16">
                        <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                    </div>
                    <div v-else-if="coverageHistory.length" class="space-y-4">
                        <h3 class="mb-4 text-lg font-semibold text-foreground">Coverage History</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b">
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Date</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Coverage</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Features</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Gaps</th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Visual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="entry in coverageHistory" :key="entry.date" class="border-b">
                                        <td class="px-4 py-3 text-foreground">{{ entry.date }}</td>
                                        <td class="px-4 py-3">
                                            <span class="font-medium" :class="getCoverageColor(entry.coverage)">
                                                {{ entry.coverage }}%
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-foreground">{{ entry.features }}</td>
                                        <td class="px-4 py-3 text-foreground">{{ entry.gaps }}</td>
                                        <td class="px-4 py-3">
                                            <div class="h-2 w-32 overflow-hidden rounded-full bg-secondary">
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="getCoverageBg(entry.coverage)"
                                                    :style="{ width: entry.coverage + '%' }"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="py-16 text-center">
                        <TrendingUp class="mx-auto mb-4 h-16 w-16 text-muted-foreground/30" />
                        <p class="text-lg font-medium text-muted-foreground">No history yet</p>
                        <p class="mt-1 text-sm text-muted-foreground">Run AI analyses to build coverage history</p>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Add Feature Dialog -->
        <Dialog v-model:open="showAddFeatureDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Project Feature</DialogTitle>
                    <DialogDescription>Define a feature to track its test coverage.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div>
                        <Label>Name *</Label>
                        <Input v-model="featureForm.name" placeholder="Feature name" class="mt-1" />
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea v-model="featureForm.description" placeholder="Feature description" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="flex items-center justify-between">
                                <Label>Modules</Label>
                                <div class="flex gap-2 text-xs">
                                    <button type="button" class="cursor-pointer text-primary hover:underline" @click="featureForm.module = [...MODULE_OPTIONS]">Select All</button>
                                    <button type="button" class="cursor-pointer text-muted-foreground hover:underline" @click="featureForm.module = []">Clear</button>
                                </div>
                            </div>
                            <div class="mt-1 rounded-md border p-2 space-y-1">
                                <label v-for="opt in MODULE_OPTIONS" :key="opt" class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 hover:bg-muted/50">
                                    <Checkbox
                                        :model-value="featureForm.module.includes(opt)"
                                        @update:model-value="featureForm.module.includes(opt) ? featureForm.module = featureForm.module.filter(m => m !== opt) : featureForm.module = [...featureForm.module, opt]"
                                    />
                                    <span class="text-sm">{{ opt }}</span>
                                </label>
                            </div>
                            <div v-if="featureForm.module.length" class="mt-1.5 flex flex-wrap gap-1">
                                <Badge v-for="mod in featureForm.module" :key="mod" variant="secondary" class="gap-1 pr-1">
                                    {{ mod }}
                                    <button type="button" class="ml-0.5 cursor-pointer rounded-full p-0.5 hover:bg-muted" @click="featureForm.module = featureForm.module.filter(m => m !== mod)">
                                        <X class="h-3 w-3" />
                                    </button>
                                </Badge>
                            </div>
                        </div>
                        <div>
                            <Label>Category</Label>
                            <Input v-model="featureForm.category" placeholder="e.g. Authentication" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <Label>Priority *</Label>
                        <Select v-model="featureForm.priority">
                            <SelectTrigger class="mt-1">
                                <SelectValue placeholder="Select priority" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="critical">Critical</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="low">Low</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddFeatureDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button @click="addFeature" :disabled="!featureForm.name" class="cursor-pointer">Add Feature</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Feature Dialog -->
        <Dialog v-model:open="showEditFeatureDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Feature</DialogTitle>
                    <DialogDescription>Update feature details.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div>
                        <Label>Name *</Label>
                        <Input v-model="editForm.name" placeholder="Feature name" class="mt-1" />
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea v-model="editForm.description" placeholder="Feature description" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="flex items-center justify-between">
                                <Label>Modules</Label>
                                <div class="flex gap-2 text-xs">
                                    <button type="button" class="cursor-pointer text-primary hover:underline" @click="editForm.module = [...MODULE_OPTIONS]">Select All</button>
                                    <button type="button" class="cursor-pointer text-muted-foreground hover:underline" @click="editForm.module = []">Clear</button>
                                </div>
                            </div>
                            <div class="mt-1 rounded-md border p-2 space-y-1">
                                <label v-for="opt in MODULE_OPTIONS" :key="opt" class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 hover:bg-muted/50">
                                    <Checkbox
                                        :model-value="editForm.module.includes(opt)"
                                        @update:model-value="editForm.module.includes(opt) ? editForm.module = editForm.module.filter(m => m !== opt) : editForm.module = [...editForm.module, opt]"
                                    />
                                    <span class="text-sm">{{ opt }}</span>
                                </label>
                            </div>
                            <div v-if="editForm.module.length" class="mt-1.5 flex flex-wrap gap-1">
                                <Badge v-for="mod in editForm.module" :key="mod" variant="secondary" class="gap-1 pr-1">
                                    {{ mod }}
                                    <button type="button" class="ml-0.5 cursor-pointer rounded-full p-0.5 hover:bg-muted" @click="editForm.module = editForm.module.filter(m => m !== mod)">
                                        <X class="h-3 w-3" />
                                    </button>
                                </Badge>
                            </div>
                        </div>
                        <div>
                            <Label>Category</Label>
                            <Input v-model="editForm.category" placeholder="e.g. Authentication" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <Label>Priority *</Label>
                        <Select v-model="editForm.priority">
                            <SelectTrigger class="mt-1">
                                <SelectValue placeholder="Select priority" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="critical">Critical</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="low">Low</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showEditFeatureDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button @click="updateFeature" :disabled="!editForm.name" class="cursor-pointer">Save Changes</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Link Test Cases Dialog -->
        <Dialog v-model:open="showLinkTestCasesDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Link2 class="h-5 w-5" />
                        Link Test Cases to {{ linkingTestCaseFeature?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Filter by test suite, search and toggle test cases to link or unlink from this feature.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <!-- Suite filter + search -->
                    <div class="flex gap-3">
                        <div class="w-48 shrink-0">
                            <Select v-model="linkTestCaseSuiteFilter">
                                <SelectTrigger>
                                    <SelectValue placeholder="All test suites" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All test suites</SelectItem>
                                    <SelectItem
                                        v-for="suite in availableSuitesForLink"
                                        :key="suite.id"
                                        :value="suite.id.toString()"
                                    >
                                        {{ suite.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="relative flex-1">
                            <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="linkTestCaseSearch" placeholder="Search test cases..." class="pl-9" />
                            <button
                                v-if="linkTestCaseSearch"
                                @click="linkTestCaseSearch = ''"
                                class="absolute top-1/2 right-3 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div class="max-h-96 space-y-1 overflow-y-auto">
                        <div
                            v-for="tc in filteredAllTestCases"
                            :key="tc.id"
                            class="flex items-start gap-3 rounded border px-3 py-2 transition-colors hover:bg-muted/30"
                        >
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-muted/50">
                                <FileText class="h-3.5 w-3.5 text-muted-foreground" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <span class="text-sm leading-snug text-foreground">{{ tc.title }}</span>
                                <Badge v-if="tc.test_suite" variant="outline" class="mt-0.5 text-xs">
                                    {{ tc.test_suite.name }}
                                </Badge>
                            </div>
                            <Button
                                v-if="pendingTestCaseIds.has(tc.id)"
                                variant="outline"
                                size="sm"
                                disabled
                                class="mt-0.5 shrink-0"
                            >
                                <Loader2 class="mr-1 h-3.5 w-3.5 animate-spin" />
                                ...
                            </Button>
                            <Button
                                v-else-if="linkingTestCaseFeature && isLinked(linkingTestCaseFeature, tc.id)"
                                variant="outline"
                                size="sm"
                                @click="toggleLink(tc.id)"
                                class="mt-0.5 shrink-0 cursor-pointer border-emerald-500 text-emerald-600 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/20"
                            >
                                <Unlink class="mr-1 h-3.5 w-3.5" />
                                Linked
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                @click="toggleLink(tc.id)"
                                class="mt-0.5 shrink-0 cursor-pointer"
                            >
                                <Link2 class="mr-1 h-3.5 w-3.5" />
                                Link
                            </Button>
                        </div>
                        <p v-if="filteredAllTestCases.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            No test cases found.
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <div class="flex w-full items-center justify-between">
                        <span class="text-sm text-muted-foreground">{{ linkedTestCaseSnapshot.size }} linked</span>
                        <Button @click="showLinkTestCasesDialog = false" class="cursor-pointer">Close</Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Link Checklists Dialog -->
        <Dialog v-model:open="showLinkChecklistsDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ClipboardList class="h-5 w-5" />
                        Link Checklists to {{ linkingChecklistFeature?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Search and toggle checklists to link or unlink from this feature.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="linkChecklistSearch" placeholder="Search checklists..." class="pl-9" />
                        <button
                            v-if="linkChecklistSearch"
                            @click="linkChecklistSearch = ''"
                            class="absolute top-1/2 right-3 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="max-h-96 space-y-1 overflow-y-auto">
                        <div
                            v-for="cl in filteredAllChecklists"
                            :key="cl.id"
                            class="flex items-center justify-between gap-3 rounded border px-3 py-2 transition-colors hover:bg-muted/30"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-violet-500/10">
                                    <ClipboardList class="h-3.5 w-3.5 text-violet-500" />
                                </div>
                                <span class="truncate text-sm text-foreground" :title="cl.name">{{ cl.name }}</span>
                            </div>
                            <Button
                                v-if="pendingChecklistIds.has(cl.id)"
                                variant="outline"
                                size="sm"
                                disabled
                                class="shrink-0"
                            >
                                <Loader2 class="mr-1 h-3.5 w-3.5 animate-spin" />
                                Updating...
                            </Button>
                            <Button
                                v-else-if="linkingChecklistFeature && isChecklistLinked(linkingChecklistFeature, cl.id)"
                                variant="outline"
                                size="sm"
                                @click="toggleChecklistLink(cl.id)"
                                class="shrink-0 cursor-pointer border-emerald-500 text-emerald-600 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/20"
                            >
                                <Unlink class="mr-1 h-3.5 w-3.5" />
                                Linked
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                @click="toggleChecklistLink(cl.id)"
                                class="shrink-0 cursor-pointer"
                            >
                                <Link2 class="mr-1 h-3.5 w-3.5" />
                                Link
                            </Button>
                        </div>
                        <p v-if="filteredAllChecklists.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            No checklists found.
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button @click="showLinkChecklistsDialog = false" class="cursor-pointer">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Generated Test Cases Modal -->
        <Dialog v-model:open="showGeneratedModal">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Sparkles class="h-5 w-5" />
                        Generated Test Cases for {{ currentGapName }}
                    </DialogTitle>
                    <DialogDescription>
                        AI-generated test cases have been saved. Review them below.
                    </DialogDescription>
                </DialogHeader>
                <div class="max-h-96 space-y-4 overflow-y-auto py-4">
                    <div
                        v-for="(tc, idx) in generatedTestCases"
                        :key="idx"
                        class="rounded-lg border p-4"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <h4 class="font-medium text-foreground">{{ (tc as Record<string, unknown>).title }}</h4>
                            <Badge :variant="priorityVariant(String((tc as Record<string, unknown>).priority || 'medium'))" class="text-xs uppercase">
                                {{ (tc as Record<string, unknown>).priority }}
                            </Badge>
                            <Badge variant="outline" class="text-xs uppercase">
                                {{ (tc as Record<string, unknown>).type }}
                            </Badge>
                        </div>
                        <div v-if="(tc as Record<string, unknown>).preconditions" class="mb-2 text-sm text-muted-foreground">
                            <strong>Preconditions:</strong> {{ (tc as Record<string, unknown>).preconditions }}
                        </div>
                        <div class="mb-2">
                            <strong class="text-sm text-foreground">Steps:</strong>
                            <ol class="mt-1 list-inside list-decimal space-y-1 text-sm text-muted-foreground">
                                <li v-for="(step, sIdx) in ((tc as Record<string, unknown>).test_steps as string[])" :key="sIdx">{{ step }}</li>
                            </ol>
                        </div>
                        <div class="text-sm">
                            <strong class="text-foreground">Expected Result:</strong>
                            <span class="text-muted-foreground"> {{ (tc as Record<string, unknown>).expected_result }}</span>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button @click="showGeneratedModal = false" class="cursor-pointer">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
