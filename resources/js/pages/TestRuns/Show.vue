<script setup lang="ts">
import { Head, Link, router, Deferred } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestRun, type TestRunCase, type TestSuite, type Checklist, type ChecklistRow } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Play, Edit, CheckCircle2, XCircle, AlertTriangle,
    SkipForward, RotateCcw, Circle, User, ExternalLink, Search, X, Link2, Check, Pause, Timer, ChevronDown, ChevronUp,
    Plus, Layers, FileText, Boxes, ListChecks
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { useSearch } from '@/composables/useSearch';
import { testResultVariant } from '@/lib/badge-variants';

const props = defineProps<{
    project: Project;
    testRun: TestRun;
    testSuites?: TestSuite[];
    checklists?: Checklist[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Runs', href: `/projects/${props.project.id}/test-runs` },
    { title: props.testRun.name, href: `/projects/${props.project.id}/test-runs/${props.testRun.id}` },
];

const copied = ref(false);

const titleStart = computed(() => {
    const words = props.testRun.name.split(' ');
    return words.length > 1 ? words.slice(0, -1).join(' ') + ' ' : '';
});
const titleEnd = computed(() => {
    const words = props.testRun.name.split(' ');
    return words[words.length - 1];
});

const copyLink = () => {
    const route = `/projects/${props.project.id}/test-runs/${props.testRun.id}`;
    const url = window.location.origin + route;
    const textArea = document.createElement('textarea');
    textArea.value = url;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
};

const selectedCase = ref<TestRunCase | null>(null);
const showResultDialog = ref(false);
const resultForm = ref({
    status: 'passed' as TestRunCase['status'],
    actual_result: '',
    time_spent: null as number | null,
    clickup_link: '',
    qase_link: '',
});

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'passed': return CheckCircle2;
        case 'failed': return XCircle;
        case 'blocked': return AlertTriangle;
        case 'skipped': return SkipForward;
        case 'retest': return RotateCcw;
        default: return Circle;
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'passed': return 'text-green-500';
        case 'failed': return 'text-red-500';
        case 'blocked': return 'text-orange-500';
        case 'skipped': return 'text-purple-500';
        case 'retest': return 'text-blue-500';
        default: return 'text-gray-400';
    }
};

const openResultDialog = (testRunCase: TestRunCase) => {
    selectedCase.value = testRunCase;
    resultForm.value = {
        status: testRunCase.status === 'untested' ? 'passed' : testRunCase.status,
        actual_result: testRunCase.actual_result || '',
        time_spent: testRunCase.time_spent,
        clickup_link: testRunCase.clickup_link || '',
        qase_link: testRunCase.qase_link || '',
    };
    showResultDialog.value = true;
};

const saveResult = () => {
    if (!selectedCase.value) return;

    router.put(
        `/projects/${props.project.id}/test-runs/${props.testRun.id}/cases/${selectedCase.value.id}`,
        resultForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showResultDialog.value = false;
                selectedCase.value = null;
            },
        }
    );
};

const quickStatus = (testRunCase: TestRunCase, status: TestRunCase['status']) => {
    router.put(
        `/projects/${props.project.id}/test-runs/${props.testRun.id}/cases/${testRunCase.id}`,
        { status },
        { preserveScroll: true }
    );
};

const completeRun = () => {
    router.post(`/projects/${props.project.id}/test-runs/${props.testRun.id}/complete`);
};

const pauseRun = () => {
    router.post(`/projects/${props.project.id}/test-runs/${props.testRun.id}/pause`, {}, { preserveScroll: true });
};

const resumeRun = () => {
    router.post(`/projects/${props.project.id}/test-runs/${props.testRun.id}/resume`, {}, { preserveScroll: true });
};

const formatElapsed = (seconds: number | null | undefined): string | null => {
    if (seconds == null || seconds < 0) return null;
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    if (days > 0) return `${days}d ${hours}h ${minutes}m`;
    if (hours > 0) return `${hours}h ${minutes}m`;
    if (minutes > 0) return `${minutes}m ${secs}s`;
    return `${secs}s`;
};

// Live timer
const tick = ref(0);
let timerInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    timerInterval = setInterval(() => { tick.value++; }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

const liveElapsed = computed((): number | null => {
    tick.value;
    const run = props.testRun;
    if (run.status === 'completed' || run.status === 'archived') {
        return run.elapsed_seconds ?? null;
    }
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
});

const groupedCases = computed(() => {
    const groups: Record<string, TestRunCase[]> = {};
    props.testRun.test_run_cases?.forEach(trc => {
        const suiteName = trc.test_case?.test_suite?.name || props.testRun.checklist?.name || 'Checks';
        if (!groups[suiteName]) {
            groups[suiteName] = [];
        }
        groups[suiteName].push(trc);
    });
    return groups;
});

const expandedCases = ref<Set<number>>(new Set());

const toggleExpanded = (id: number) => {
    if (expandedCases.value.has(id)) {
        expandedCases.value.delete(id);
    } else {
        expandedCases.value.add(id);
    }
};

const hasDetails = (trc: TestRunCase): boolean => {
    const tc = trc.test_case;
    if (!tc) return false;
    return !!(tc.description || tc.preconditions || tc.steps?.length || tc.expected_result);
};

const { searchQuery, highlight } = useSearch();

const filteredGroupedCases = computed(() => {
    if (!searchQuery.value.trim()) return groupedCases.value;
    const query = searchQuery.value.toLowerCase();
    const filtered: Record<string, TestRunCase[]> = {};
    for (const [suiteName, cases] of Object.entries(groupedCases.value)) {
        const matched = cases.filter(trc =>
            trc.test_case?.title?.toLowerCase().includes(query) ||
            trc.title?.toLowerCase().includes(query) ||
            trc.status.toLowerCase().includes(query) ||
            trc.assigned_user?.name?.toLowerCase().includes(query)
        );
        if (matched.length > 0) {
            filtered[suiteName] = matched;
        }
    }
    return filtered;
});

// --- Add Cases Dialog ---
const showAddCasesDialog = ref(false);
const addCasesProcessing = ref(false);

// For test-cases source
const addCaseIds = ref<number[]>([]);

const existingTestCaseIds = computed(() => {
    return new Set(
        props.testRun.test_run_cases
            ?.filter(trc => trc.test_case_id)
            .map(trc => trc.test_case_id!) ?? []
    );
});

const getAllTestCases = (suite: TestSuite): number[] => {
    const ids: number[] = [];
    if (suite.test_cases) {
        ids.push(...suite.test_cases.map(tc => tc.id));
    }
    if (suite.children) {
        suite.children.forEach(child => {
            ids.push(...getAllTestCases(child));
        });
    }
    return ids;
};

const isSuiteSelected = (suite: TestSuite) => {
    const allIds = getAllTestCases(suite).filter(id => !existingTestCaseIds.value.has(id));
    return allIds.length > 0 && allIds.every(id => addCaseIds.value.includes(id));
};

const isSuitePartiallySelected = (suite: TestSuite) => {
    const allIds = getAllTestCases(suite).filter(id => !existingTestCaseIds.value.has(id));
    const selectedCount = allIds.filter(id => addCaseIds.value.includes(id)).length;
    return selectedCount > 0 && selectedCount < allIds.length;
};

const toggleSuiteSelection = (suite: TestSuite) => {
    const allIds = getAllTestCases(suite).filter(id => !existingTestCaseIds.value.has(id));
    if (isSuiteSelected(suite)) {
        addCaseIds.value = addCaseIds.value.filter(id => !allIds.includes(id));
    } else {
        const newIds = allIds.filter(id => !addCaseIds.value.includes(id));
        addCaseIds.value = [...addCaseIds.value, ...newIds];
    }
};

const toggleAddTestCase = (testCaseId: number) => {
    if (existingTestCaseIds.value.has(testCaseId)) return;
    const index = addCaseIds.value.indexOf(testCaseId);
    if (index > -1) {
        addCaseIds.value = addCaseIds.value.filter(id => id !== testCaseId);
    } else {
        addCaseIds.value = [...addCaseIds.value, testCaseId];
    }
};

// For checklist source
const addRowTitles = ref<Set<string>>(new Set());
const selectedAddChecklistId = ref('');

const existingTitles = computed(() => {
    return new Set(
        props.testRun.test_run_cases
            ?.filter(trc => trc.title)
            .map(trc => trc.title!) ?? []
    );
});

const selectedAddChecklist = computed(() => {
    if (!selectedAddChecklistId.value) return null;
    return props.checklists?.find(c => c.id === Number(selectedAddChecklistId.value)) ?? null;
});

const textColumnKey = computed((): string | null => {
    if (!selectedAddChecklist.value?.columns_config) return null;
    const col = selectedAddChecklist.value.columns_config.find(col => col.type === 'text');
    return col?.key ?? null;
});

const checklistRows = computed((): { title: string; row: ChecklistRow }[] => {
    if (!selectedAddChecklist.value?.rows || !textColumnKey.value) return [];
    return selectedAddChecklist.value.rows
        .filter(r => r.row_type !== 'section_header')
        .map(r => ({
            title: String((r.data as Record<string, unknown>)?.[textColumnKey.value!] ?? ''),
            row: r,
        }))
        .filter(r => r.title.trim() !== '');
});

const toggleAddRowTitle = (title: string) => {
    if (existingTitles.value.has(title)) return;
    const set = new Set(addRowTitles.value);
    if (set.has(title)) {
        set.delete(title);
    } else {
        set.add(title);
    }
    addRowTitles.value = set;
};

watch(selectedAddChecklistId, () => {
    addRowTitles.value = new Set();
});

const selectAllAddRows = () => {
    const set = new Set(addRowTitles.value);
    checklistRows.value.forEach(item => {
        if (!existingTitles.value.has(item.title)) {
            set.add(item.title);
        }
    });
    addRowTitles.value = set;
};

const deselectAllAddRows = () => {
    addRowTitles.value = new Set();
};

const openAddCasesDialog = () => {
    addCaseIds.value = [];
    addRowTitles.value = new Set();
    selectedAddChecklistId.value = props.testRun.checklist_id ? String(props.testRun.checklist_id) : '';
    showAddCasesDialog.value = true;
};

const submitAddCases = () => {
    addCasesProcessing.value = true;

    const data: Record<string, unknown> = {};
    if (props.testRun.source === 'test-cases') {
        data.test_case_ids = addCaseIds.value;
    } else {
        data.titles = Array.from(addRowTitles.value);
    }

    router.post(
        `/projects/${props.project.id}/test-runs/${props.testRun.id}/add-cases`,
        data,
        {
            preserveScroll: true,
            onSuccess: () => {
                showAddCasesDialog.value = false;
                addCaseIds.value = [];
                addRowTitles.value = new Set();
            },
            onFinish: () => {
                addCasesProcessing.value = false;
            },
        }
    );
};

const addCasesCount = computed(() => {
    if (props.testRun.source === 'test-cases') {
        return addCaseIds.value.length;
    }
    return addRowTitles.value.size;
});

</script>

<template>
    <Head :title="testRun.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        <Play class="inline-block h-6 w-6 align-text-top text-primary mr-2" />{{ titleStart }}<span class="whitespace-nowrap">{{ titleEnd }}<button
                            @click="copyLink"
                            class="inline-flex align-middle ml-1.5 p-1 rounded-md text-muted-foreground hover:text-primary hover:bg-muted transition-colors cursor-pointer"
                            :title="copied ? 'Copied!' : 'Copy link'"
                        ><Check v-if="copied" class="h-4 w-4 text-green-500" /><Link2 v-else class="h-4 w-4" /></button></span>
                    </h1>
                    <p v-if="testRun.description" class="mt-1 text-muted-foreground">
                        {{ testRun.description }}
                    </p>
                    <div class="mt-2 flex items-center gap-3">
                        <Badge :variant="testResultVariant(testRun.status)">
                            {{ testRun.status }}
                        </Badge>
                        <Badge v-if="testRun.environment" variant="blue">
                            {{ testRun.environment }}
                        </Badge>
                        <span v-if="testRun.milestone" class="text-sm text-muted-foreground">
                            {{ testRun.milestone }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search test cases..."
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
                        <Button
                            v-if="testRun.status === 'active'"
                            @click="openAddCasesDialog"
                            variant="outline"
                            class="gap-2"
                        >
                            <Plus class="h-4 w-4" />
                            Add Cases
                        </Button>
                    </RestrictedAction>
                    <RestrictedAction>
                        <Button
                            v-if="testRun.status === 'active' && !testRun.is_paused"
                            @click="pauseRun"
                            variant="outline"
                            class="gap-2"
                        >
                            <Pause class="h-4 w-4" />
                            Pause
                        </Button>
                    </RestrictedAction>
                    <RestrictedAction>
                        <Button
                            v-if="testRun.status === 'active' && testRun.is_paused"
                            @click="resumeRun"
                            variant="outline"
                            class="gap-2"
                        >
                            <Play class="h-4 w-4" />
                            Resume
                        </Button>
                    </RestrictedAction>
                    <RestrictedAction>
                        <Button
                            v-if="testRun.status === 'active'"
                            @click="completeRun"
                            variant="outline"
                            class="gap-2"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                            Complete Run
                        </Button>
                    </RestrictedAction>
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/test-runs/${testRun.id}/edit`">
                            <Button variant="outline" class="gap-2">
                                <Edit class="h-4 w-4" />
                                Edit
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <!-- Progress Stats -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center gap-8">
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium">Progress</span>
                                <span class="text-2xl font-bold text-primary">{{ testRun.progress }}%</span>
                            </div>
                            <Progress :model-value="testRun.progress" class="h-3" />
                        </div>
                        <div class="flex gap-6">
                            <div v-if="formatElapsed(liveElapsed)" class="text-center">
                                <div class="text-2xl font-bold flex items-center gap-1" :class="testRun.is_paused ? 'text-yellow-500' : 'text-primary'">
                                    <Pause v-if="testRun.is_paused" class="h-5 w-5" />
                                    <Timer v-else class="h-5 w-5" />
                                    {{ formatElapsed(liveElapsed) }}
                                </div>
                                <div class="text-xs text-muted-foreground">{{ testRun.is_paused ? 'Paused' : 'Elapsed' }}</div>
                            </div>
                            <div v-if="testRun.stats?.passed" class="text-center">
                                <div class="text-2xl font-bold text-green-500">{{ testRun.stats.passed }}</div>
                                <div class="text-xs text-muted-foreground">Passed</div>
                            </div>
                            <div v-if="testRun.stats?.failed" class="text-center">
                                <div class="text-2xl font-bold text-red-500">{{ testRun.stats.failed }}</div>
                                <div class="text-xs text-muted-foreground">Failed</div>
                            </div>
                            <div v-if="testRun.stats?.blocked" class="text-center">
                                <div class="text-2xl font-bold text-orange-500">{{ testRun.stats.blocked }}</div>
                                <div class="text-xs text-muted-foreground">Blocked</div>
                            </div>
                            <div v-if="testRun.stats?.skipped" class="text-center">
                                <div class="text-2xl font-bold text-purple-500">{{ testRun.stats.skipped }}</div>
                                <div class="text-xs text-muted-foreground">Skipped</div>
                            </div>
                            <div v-if="testRun.stats?.untested" class="text-center">
                                <div class="text-2xl font-bold text-gray-400">{{ testRun.stats.untested }}</div>
                                <div class="text-xs text-muted-foreground">Untested</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Test Cases by Suite -->
            <div v-if="searchQuery.trim() && Object.keys(filteredGroupedCases).length === 0" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                <Search class="h-12 w-12 mb-3" />
                <p class="font-semibold">No results found</p>
                <p class="text-sm max-w-full truncate px-4">No test cases match "{{ searchQuery }}"</p>
            </div>
            <div v-else class="space-y-4">
                <div v-for="(cases, suiteName) in filteredGroupedCases" :key="suiteName">
                    <h3 class="mb-2 font-semibold">{{ suiteName }}</h3>
                    <div class="space-y-1">
                        <Card
                            v-for="trc in cases"
                            :key="trc.id"
                            class="transition-all hover:border-primary/50"
                        >
                            <CardContent class="px-4 py-2">
                                <div class="flex items-center justify-between">
                                    <div
                                        class="flex items-center gap-3 flex-1 min-w-0"
                                        :class="{ 'cursor-pointer': hasDetails(trc) }"
                                        @click="hasDetails(trc) && toggleExpanded(trc.id)"
                                    >
                                        <component
                                            :is="getStatusIcon(trc.status)"
                                            :class="['h-4 w-4 shrink-0', getStatusColor(trc.status)]"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <p class="text-sm font-medium truncate" v-html="highlight(trc.test_case?.title ?? trc.title ?? '')" />
                                                <Badge :variant="testResultVariant(trc.status)" class="text-[10px] px-1.5 h-4 shrink-0">
                                                    {{ trc.status }}
                                                </Badge>
                                                <span v-if="trc.assigned_user" class="flex items-center gap-1 text-xs text-muted-foreground shrink-0">
                                                    <User class="h-3 w-3" />
                                                    {{ trc.assigned_user.name }}
                                                </span>
                                                <span v-if="trc.time_spent" class="text-xs text-muted-foreground shrink-0">{{ trc.time_spent }}min</span>
                                            </div>
                                        </div>
                                        <component
                                            v-if="hasDetails(trc)"
                                            :is="expandedCases.has(trc.id) ? ChevronUp : ChevronDown"
                                            class="h-4 w-4 shrink-0 text-muted-foreground"
                                        />
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0 ml-2">
                                        <!-- Quick status buttons -->
                                        <RestrictedAction>
                                            <div v-if="testRun.status === 'active'" class="flex gap-1">
                                                <Button
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    class="p-0"
                                                    :class="{ 'bg-green-500/10': trc.status === 'passed' }"
                                                    @click="quickStatus(trc, 'passed')"
                                                    title="Pass"
                                                >
                                                    <CheckCircle2 class="h-4 w-4 text-green-500" />
                                                </Button>
                                                <Button
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    class="p-0"
                                                    :class="{ 'bg-red-500/10': trc.status === 'failed' }"
                                                    @click="quickStatus(trc, 'failed')"
                                                    title="Fail"
                                                >
                                                    <XCircle class="h-4 w-4 text-red-500" />
                                                </Button>
                                                <Button
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    class="p-0"
                                                    @click="openResultDialog(trc)"
                                                    title="Add Details"
                                                >
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </RestrictedAction>
                                        <!-- External Links -->
                                        <a
                                            v-if="trc.clickup_link"
                                            :href="trc.clickup_link"
                                            target="_blank"
                                            class="text-muted-foreground hover:text-primary cursor-pointer"
                                        >
                                            <ExternalLink class="h-4 w-4" />
                                        </a>
                                    </div>
                                </div>
                                <!-- Expanded Details -->
                                <div v-if="expandedCases.has(trc.id) && trc.test_case" class="mt-2 ml-7 space-y-2 border-t pt-2">
                                    <div v-if="trc.test_case.description">
                                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Description</p>
                                        <p class="text-sm whitespace-pre-wrap">{{ trc.test_case.description }}</p>
                                    </div>
                                    <div v-if="trc.test_case.preconditions">
                                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Preconditions</p>
                                        <p class="text-sm whitespace-pre-wrap">{{ trc.test_case.preconditions }}</p>
                                    </div>
                                    <div v-if="trc.test_case.steps?.length" class="space-y-1">
                                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Steps</p>
                                        <div v-for="(step, idx) in trc.test_case.steps" :key="idx" class="flex gap-2 text-sm">
                                            <span class="shrink-0 text-xs font-medium text-muted-foreground w-5 text-right pt-0.5">{{ idx + 1 }}.</span>
                                            <div class="min-w-0">
                                                <p>{{ step.action }}</p>
                                                <p v-if="step.expected" class="text-xs text-muted-foreground">
                                                    <span class="text-green-500 font-medium">Expected:</span> {{ step.expected }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="trc.test_case.expected_result">
                                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Expected Result</p>
                                        <p class="text-sm whitespace-pre-wrap">{{ trc.test_case.expected_result }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>

        <!-- Result Dialog -->
        <Dialog v-model:open="showResultDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Update Test Result</DialogTitle>
                    <DialogDescription>
                        {{ selectedCase?.test_case?.title ?? selectedCase?.title }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label>Status</Label>
                        <Select v-model="resultForm.status">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="passed">Passed</SelectItem>
                                <SelectItem value="failed">Failed</SelectItem>
                                <SelectItem value="blocked">Blocked</SelectItem>
                                <SelectItem value="skipped">Skipped</SelectItem>
                                <SelectItem value="retest">Retest</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label>Actual Result / Comments</Label>
                        <Textarea v-model="resultForm.actual_result" rows="3" placeholder="Describe what happened..." />
                    </div>
                    <div class="space-y-2">
                        <Label>Time Spent (minutes)</Label>
                        <Input v-model.number="resultForm.time_spent" type="number" min="0" />
                    </div>
                    <div class="space-y-2">
                        <Label>ClickUp Link</Label>
                        <Input v-model="resultForm.clickup_link" type="url" placeholder="https://app.clickup.com/..." />
                    </div>
                    <div class="space-y-2">
                        <Label>Qase Link</Label>
                        <Input v-model="resultForm.qase_link" type="url" placeholder="https://app.qase.io/..." />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showResultDialog = false">Cancel</Button>
                    <RestrictedAction>
                        <Button @click="saveResult">Save Result</Button>
                    </RestrictedAction>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Cases Dialog -->
        <Dialog v-model:open="showAddCasesDialog">
            <DialogContent class="sm:max-w-2xl max-h-[80vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Add Cases to Test Run</DialogTitle>
                    <DialogDescription>
                        {{ testRun.source === 'test-cases' ? 'Select additional test cases to add.' : 'Select additional checklist rows to add.' }}
                    </DialogDescription>
                </DialogHeader>

                <!-- Test Cases source -->
                <Deferred v-if="testRun.source === 'test-cases'" data="testSuites">
                    <template #fallback>
                        <div class="space-y-3 py-4">
                            <div v-for="i in 3" :key="i" class="h-8 animate-pulse rounded-md bg-muted" />
                        </div>
                    </template>
                    <div class="flex-1 overflow-y-auto space-y-2 pr-1">
                        <div v-if="!testSuites?.length" class="rounded-lg border border-dashed p-6 text-center">
                            <Layers class="mx-auto h-8 w-8 text-muted-foreground" />
                            <p class="mt-2 text-sm text-muted-foreground">No test suites found.</p>
                        </div>
                        <template v-else v-for="suite in testSuites" :key="suite.id">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 py-1">
                                    <Checkbox
                                        :model-value="isSuitePartiallySelected(suite) ? 'indeterminate' : isSuiteSelected(suite)"
                                        @update:model-value="toggleSuiteSelection(suite)"
                                    />
                                    <Layers class="h-4 w-4 text-primary" />
                                    <span class="font-medium">{{ suite.name }}</span>
                                </div>
                                <div class="ml-6 space-y-0.5">
                                    <div
                                        v-for="tc in suite.test_cases"
                                        :key="tc.id"
                                        class="flex items-center gap-2 py-0.5 text-sm"
                                        :class="{ 'opacity-40': existingTestCaseIds.has(tc.id) }"
                                    >
                                        <Checkbox
                                            :model-value="existingTestCaseIds.has(tc.id) || addCaseIds.includes(tc.id)"
                                            :disabled="existingTestCaseIds.has(tc.id)"
                                            @update:model-value="toggleAddTestCase(tc.id)"
                                        />
                                        <FileText class="h-3 w-3 text-muted-foreground" />
                                        <span>{{ tc.title }}</span>
                                        <Badge v-if="existingTestCaseIds.has(tc.id)" variant="secondary" class="text-[10px] px-1.5 h-4">
                                            already added
                                        </Badge>
                                    </div>
                                    <template v-for="child in suite.children" :key="child.id">
                                        <div class="ml-4 space-y-0.5">
                                            <div class="flex items-center gap-2 py-0.5">
                                                <Checkbox
                                                    :model-value="isSuitePartiallySelected(child) ? 'indeterminate' : isSuiteSelected(child)"
                                                    @update:model-value="toggleSuiteSelection(child)"
                                                />
                                                <Boxes class="h-3 w-3 text-yellow-500" />
                                                <span class="font-medium text-sm">{{ child.name }}</span>
                                            </div>
                                            <div
                                                v-for="tc in child.test_cases"
                                                :key="tc.id"
                                                class="ml-6 flex items-center gap-2 py-0.5 text-sm"
                                                :class="{ 'opacity-40': existingTestCaseIds.has(tc.id) }"
                                            >
                                                <Checkbox
                                                    :model-value="existingTestCaseIds.has(tc.id) || addCaseIds.includes(tc.id)"
                                                    :disabled="existingTestCaseIds.has(tc.id)"
                                                    @update:model-value="toggleAddTestCase(tc.id)"
                                                />
                                                <FileText class="h-3 w-3 text-muted-foreground" />
                                                <span>{{ tc.title }}</span>
                                                <Badge v-if="existingTestCaseIds.has(tc.id)" variant="secondary" class="text-[10px] px-1.5 h-4">
                                                    already added
                                                </Badge>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </Deferred>

                <!-- Checklist source -->
                <Deferred v-else-if="testRun.source === 'checklist'" data="checklists">
                    <template #fallback>
                        <div class="space-y-3 py-4">
                            <div v-for="i in 3" :key="i" class="h-8 animate-pulse rounded-md bg-muted" />
                        </div>
                    </template>
                    <div class="flex-1 overflow-y-auto space-y-3 px-0.5">
                        <div class="space-y-2">
                            <Label>Select Checklist</Label>
                            <Select v-model="selectedAddChecklistId">
                                <SelectTrigger>
                                    <SelectValue placeholder="Choose a checklist..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="cl in checklists"
                                        :key="cl.id"
                                        :value="String(cl.id)"
                                    >
                                        {{ cl.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <template v-if="selectedAddChecklist">
                            <div v-if="checklistRows.length > 0" class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <Label>Select Rows</Label>
                                    <div class="flex gap-2">
                                        <Button type="button" variant="outline" size="sm" @click="selectAllAddRows">
                                            Select All
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" @click="deselectAllAddRows">
                                            Deselect All
                                        </Button>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div
                                        v-for="item in checklistRows"
                                        :key="item.row.id"
                                        class="flex items-center gap-2 py-1 text-sm"
                                        :class="{ 'opacity-40': existingTitles.has(item.title) }"
                                    >
                                        <Checkbox
                                            :model-value="existingTitles.has(item.title) || addRowTitles.has(item.title)"
                                            :disabled="existingTitles.has(item.title)"
                                            @update:model-value="toggleAddRowTitle(item.title)"
                                        />
                                        <ListChecks class="h-3 w-3 text-muted-foreground" />
                                        <span>{{ item.title }}</span>
                                        <Badge v-if="existingTitles.has(item.title)" variant="secondary" class="text-[10px] px-1.5 h-4">
                                            already added
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="rounded-lg border border-dashed p-6 text-center">
                                <ListChecks class="mx-auto h-8 w-8 text-muted-foreground" />
                                <p class="mt-2 text-sm text-muted-foreground">No text rows found in this checklist.</p>
                            </div>
                        </template>
                    </div>
                </Deferred>

                <DialogFooter class="pt-2">
                    <p class="text-sm text-muted-foreground mr-auto">
                        {{ addCasesCount }} new case(s) selected
                    </p>
                    <Button variant="outline" @click="showAddCasesDialog = false">Cancel</Button>
                    <RestrictedAction>
                        <Button @click="submitAddCases" :disabled="addCasesProcessing || addCasesCount === 0">
                            Add Cases
                        </Button>
                    </RestrictedAction>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
:deep(.search-highlight) {
    background-color: rgb(147 197 253 / 0.5);
    border-radius: 0.125rem;
    padding: 0.0625rem 0.125rem;
}
</style>
