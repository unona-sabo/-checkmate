<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestRun, type TestRunCase } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Play, Edit, CheckCircle2, XCircle, AlertTriangle,
    SkipForward, RotateCcw, Circle, User, ExternalLink, Search, X
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps<{
    project: Project;
    testRun: TestRun;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Runs', href: `/projects/${props.project.id}/test-runs` },
    { title: props.testRun.name, href: `/projects/${props.project.id}/test-runs/${props.testRun.id}` },
];

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

const getStatusBadgeColor = (status: string) => {
    switch (status) {
        case 'passed': return 'bg-green-500/10 text-green-500 border-green-500/20';
        case 'failed': return 'bg-red-500/10 text-red-500 border-red-500/20';
        case 'blocked': return 'bg-orange-500/10 text-orange-500 border-orange-500/20';
        case 'skipped': return 'bg-purple-500/10 text-purple-500 border-purple-500/20';
        case 'retest': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        default: return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
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

const groupedCases = computed(() => {
    const groups: Record<string, TestRunCase[]> = {};
    props.testRun.test_run_cases?.forEach(trc => {
        const suiteName = trc.test_case?.test_suite?.name || 'Unknown Suite';
        if (!groups[suiteName]) {
            groups[suiteName] = [];
        }
        groups[suiteName].push(trc);
    });
    return groups;
});

const searchQuery = ref('');

const filteredGroupedCases = computed(() => {
    if (!searchQuery.value.trim()) return groupedCases.value;
    const query = searchQuery.value.toLowerCase();
    const filtered: Record<string, TestRunCase[]> = {};
    for (const [suiteName, cases] of Object.entries(groupedCases.value)) {
        const matched = cases.filter(trc =>
            trc.test_case?.title?.toLowerCase().includes(query) ||
            trc.status.toLowerCase().includes(query) ||
            trc.assigned_user?.name?.toLowerCase().includes(query)
        );
        if (matched.length > 0) {
            filtered[suiteName] = matched;
        }
    }
    return filtered;
});
</script>

<template>
    <Head :title="testRun.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Play class="h-6 w-6 text-primary" />
                        {{ testRun.name }}
                    </h1>
                    <p v-if="testRun.description" class="mt-1 text-muted-foreground">
                        {{ testRun.description }}
                    </p>
                    <div class="mt-2 flex items-center gap-3">
                        <Badge :class="getStatusBadgeColor(testRun.status)" variant="outline">
                            {{ testRun.status }}
                        </Badge>
                        <span v-if="testRun.environment" class="text-sm text-muted-foreground">
                            {{ testRun.environment }}
                        </span>
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
                    <Button
                        v-if="testRun.status === 'active'"
                        @click="completeRun"
                        variant="outline"
                        class="gap-2"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        Complete Run
                    </Button>
                    <Link :href="`/projects/${project.id}/test-runs/${testRun.id}/edit`">
                        <Button variant="outline" class="gap-2">
                            <Edit class="h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
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
                <p class="text-sm">No test cases match "{{ searchQuery }}"</p>
            </div>
            <div v-else class="space-y-4">
                <div v-for="(cases, suiteName) in filteredGroupedCases" :key="suiteName">
                    <h3 class="mb-2 font-semibold">{{ suiteName }}</h3>
                    <div class="space-y-2">
                        <Card
                            v-for="trc in cases"
                            :key="trc.id"
                            class="transition-all hover:border-primary/50"
                        >
                            <CardContent class="flex items-center justify-between p-4">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <component
                                        :is="getStatusIcon(trc.status)"
                                        :class="['h-5 w-5 shrink-0', getStatusColor(trc.status)]"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium truncate">{{ trc.test_case?.title }}</p>
                                        <p v-if="trc.test_case?.expected_result" class="text-sm text-muted-foreground mt-0.5 line-clamp-2">
                                            {{ trc.test_case.expected_result }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
                                            <Badge :class="getStatusBadgeColor(trc.status)" variant="outline" class="text-xs">
                                                {{ trc.status }}
                                            </Badge>
                                            <span v-if="trc.assigned_user" class="flex items-center gap-1">
                                                <User class="h-3 w-3" />
                                                {{ trc.assigned_user.name }}
                                            </span>
                                            <span v-if="trc.time_spent">{{ trc.time_spent }}min</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <!-- Quick status buttons -->
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
                        {{ selectedCase?.test_case?.title }}
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
                    <Button @click="saveResult">Save Result</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
