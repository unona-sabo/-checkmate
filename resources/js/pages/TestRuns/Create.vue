<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite, type Checklist, type ChecklistRow } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Play, Layers, FileText, Boxes, ListChecks } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import RestrictedAction from '@/components/RestrictedAction.vue';

const props = defineProps<{
    project: Project;
    testSuites: TestSuite[];
    source?: string;
    checklists?: Checklist[];
}>();

const isChecklistMode = computed(() => props.source === 'checklist');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Runs', href: `/projects/${props.project.id}/test-runs` },
    { title: 'Create', href: `/projects/${props.project.id}/test-runs/create` },
];

// Test cases form
const form = useForm({
    name: '',
    description: '',
    priority: '' as string,
    environment: '',
    milestone: '',
    test_case_ids: [] as number[],
});

// Checklist form
const checklistForm = useForm({
    name: '',
    description: '',
    priority: '' as string,
    environment: '',
    milestone: '',
    checklist_id: null as number | null,
    titles: [] as string[],
});

const envPreset = ref('');
const envNotes = ref('');

watch([envPreset, envNotes], () => {
    const parts = [envPreset.value, envNotes.value.trim()].filter(Boolean);
    const env = parts.join(' — ');
    form.environment = env;
    checklistForm.environment = env;
});

// --- Test Cases mode helpers ---
const expandedSuites = ref<Record<number, boolean>>({});

const toggleSuite = (suiteId: number) => {
    expandedSuites.value[suiteId] = !expandedSuites.value[suiteId];
};

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
    const allIds = getAllTestCases(suite);
    return allIds.length > 0 && allIds.every(id => form.test_case_ids.includes(id));
};

const isSuitePartiallySelected = (suite: TestSuite) => {
    const allIds = getAllTestCases(suite);
    const selectedCount = allIds.filter(id => form.test_case_ids.includes(id)).length;
    return selectedCount > 0 && selectedCount < allIds.length;
};

const toggleSuiteSelection = (suite: TestSuite) => {
    const allIds = getAllTestCases(suite);
    if (isSuiteSelected(suite)) {
        form.test_case_ids = form.test_case_ids.filter(id => !allIds.includes(id));
    } else {
        const newIds = allIds.filter(id => !form.test_case_ids.includes(id));
        form.test_case_ids = [...form.test_case_ids, ...newIds];
    }
};

const toggleTestCase = (testCaseId: number) => {
    const index = form.test_case_ids.indexOf(testCaseId);
    if (index > -1) {
        form.test_case_ids = form.test_case_ids.filter(id => id !== testCaseId);
    } else {
        form.test_case_ids = [...form.test_case_ids, testCaseId];
    }
};

const selectAll = () => {
    const allIds: number[] = [];
    props.testSuites.forEach(suite => {
        allIds.push(...getAllTestCases(suite));
    });
    form.test_case_ids = allIds;
};

const deselectAll = () => {
    form.test_case_ids = [];
};

// --- Checklist mode helpers ---
const selectedChecklistId = ref('');

const selectedChecklist = computed(() => {
    if (!selectedChecklistId.value) return null;
    return props.checklists?.find(c => c.id === Number(selectedChecklistId.value)) ?? null;
});

const textColumnKey = computed((): string | null => {
    if (!selectedChecklist.value?.columns_config) return null;
    const col = selectedChecklist.value.columns_config.find(col => col.type === 'text');
    return col?.key ?? null;
});

const checklistRows = computed((): { title: string; row: ChecklistRow }[] => {
    if (!selectedChecklist.value?.rows || !textColumnKey.value) return [];
    return selectedChecklist.value.rows
        .filter(r => r.row_type !== 'section_header')
        .map(r => ({
            title: String((r.data as Record<string, unknown>)?.[textColumnKey.value!] ?? ''),
            row: r,
        }))
        .filter(r => r.title.trim() !== '');
});

const selectedRowTitles = ref<Set<string>>(new Set());

watch(selectedChecklistId, () => {
    selectedRowTitles.value = new Set();
    checklistForm.checklist_id = selectedChecklistId.value ? Number(selectedChecklistId.value) : null;
});

const toggleRowTitle = (title: string) => {
    const set = new Set(selectedRowTitles.value);
    if (set.has(title)) {
        set.delete(title);
    } else {
        set.add(title);
    }
    selectedRowTitles.value = set;
};

const selectAllRows = () => {
    selectedRowTitles.value = new Set(checklistRows.value.map(r => r.title));
};

const deselectAllRows = () => {
    selectedRowTitles.value = new Set();
};

// --- Submit ---
const submit = () => {
    form.post(`/projects/${props.project.id}/test-runs`);
};

const submitChecklist = () => {
    checklistForm.titles = Array.from(selectedRowTitles.value);
    checklistForm.post(`/projects/${props.project.id}/test-runs/from-checklist`);
};

const activeForm = computed(() => isChecklistMode.value ? checklistForm : form);
const isSubmitDisabled = computed(() => {
    if (isChecklistMode.value) {
        return checklistForm.processing || !checklistForm.name || !checklistForm.checklist_id || selectedRowTitles.value.size === 0;
    }
    return form.processing || !form.name || form.test_case_ids.length === 0;
});
</script>

<template>
    <Head title="Create Test Run" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-3xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Play class="h-5 w-5 text-primary" />
                            Create Test Run
                        </CardTitle>
                        <CardDescription>
                            {{ isChecklistMode ? 'Select checklist rows to include in this test run.' : 'Select test cases to include in this test run.' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="isChecklistMode ? submitChecklist() : submit()" class="space-y-6">
                            <div class="space-y-2">
                                <Label for="name">Run Name</Label>
                                <Input
                                    id="name"
                                    v-model="activeForm.name"
                                    type="text"
                                    placeholder="e.g., Sprint 1 Regression, Release 2.0 Smoke Test"
                                    :class="{ 'border-destructive': activeForm.errors.name }"
                                />
                                <InputError :message="activeForm.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="activeForm.description"
                                    placeholder="Describe the purpose of this test run..."
                                    rows="2"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label>Priority</Label>
                                <Select v-model="activeForm.priority">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select priority..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Low</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                        <SelectItem value="critical">Critical</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="activeForm.errors.priority" />
                            </div>

                            <div class="space-y-2">
                                <Label>Environment</Label>
                                <div class="grid gap-3 md:grid-cols-3">
                                    <Select v-model="envPreset">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Develop">Develop</SelectItem>
                                            <SelectItem value="Staging">Staging</SelectItem>
                                            <SelectItem value="Production">Production</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        v-model="envNotes"
                                        type="text"
                                        placeholder="Devices, browser..."
                                        class="md:col-span-2"
                                    />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="milestone">Milestone</Label>
                                <Input
                                    id="milestone"
                                    v-model="activeForm.milestone"
                                    type="text"
                                    placeholder="e.g., v2.0, Sprint 5"
                                />
                            </div>

                            <!-- Test Case Selection (default mode) -->
                            <div v-if="!isChecklistMode" class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>Select Test Cases</Label>
                                    <div class="flex gap-2">
                                        <Button type="button" variant="outline" size="sm" @click="selectAll">
                                            Select All
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" @click="deselectAll">
                                            Deselect All
                                        </Button>
                                    </div>
                                </div>

                                <div v-if="!testSuites.length" class="rounded-lg border border-dashed p-6 text-center">
                                    <Layers class="mx-auto h-8 w-8 text-muted-foreground" />
                                    <p class="mt-2 text-sm text-muted-foreground">No test suites found. Create test cases first.</p>
                                </div>

                                <div v-else class="space-y-2 rounded-lg border p-4 max-h-96 overflow-y-auto">
                                    <template v-for="suite in testSuites" :key="suite.id">
                                        <!-- Parent Suite -->
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2 py-1">
                                                <Checkbox
                                                    :model-value="isSuitePartiallySelected(suite) ? 'indeterminate' : isSuiteSelected(suite)"
                                                    @update:model-value="toggleSuiteSelection(suite)"
                                                />
                                                <button
                                                    type="button"
                                                    @click="toggleSuite(suite.id)"
                                                    class="flex items-center gap-2 font-medium hover:text-primary cursor-pointer"
                                                >
                                                    <Layers class="h-4 w-4 text-primary" />
                                                    {{ suite.name }}
                                                    <span class="text-xs text-muted-foreground">({{ getAllTestCases(suite).length }} cases)</span>
                                                </button>
                                            </div>

                                            <!-- Test Cases -->
                                            <div v-if="expandedSuites[suite.id] || true" class="ml-6 space-y-1">
                                                <div
                                                    v-for="testCase in suite.test_cases"
                                                    :key="testCase.id"
                                                    class="flex items-center gap-2 py-1 text-sm"
                                                >
                                                    <Checkbox
                                                        :model-value="form.test_case_ids.includes(testCase.id)"
                                                        @update:model-value="toggleTestCase(testCase.id)"
                                                    />
                                                    <FileText class="h-3 w-3 text-muted-foreground" />
                                                    <span>{{ testCase.title }}</span>
                                                </div>

                                                <!-- Child Suites -->
                                                <template v-for="child in suite.children" :key="child.id">
                                                    <div class="ml-4 space-y-1">
                                                        <div class="flex items-center gap-2 py-1">
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
                                                            class="ml-6 flex items-center gap-2 py-1 text-sm"
                                                        >
                                                            <Checkbox
                                                                :model-value="form.test_case_ids.includes(tc.id)"
                                                                @update:model-value="toggleTestCase(tc.id)"
                                                            />
                                                            <FileText class="h-3 w-3 text-muted-foreground" />
                                                            <span>{{ tc.title }}</span>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <p class="text-sm text-muted-foreground">
                                    {{ form.test_case_ids.length }} test cases selected
                                </p>
                                <InputError :message="form.errors.test_case_ids" />
                            </div>

                            <!-- Checklist Selection (checklist mode) -->
                            <div v-if="isChecklistMode" class="space-y-3">
                                <div class="space-y-2">
                                    <Label>Select Checklist</Label>
                                    <Select v-model="selectedChecklistId">
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
                                    <InputError :message="checklistForm.errors.checklist_id" />
                                </div>

                                <div v-if="selectedChecklist && checklistRows.length > 0" class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <Label>Select Rows</Label>
                                        <div class="flex gap-2">
                                            <Button type="button" variant="outline" size="sm" @click="selectAllRows">
                                                Select All
                                            </Button>
                                            <Button type="button" variant="outline" size="sm" @click="deselectAllRows">
                                                Deselect All
                                            </Button>
                                        </div>
                                    </div>

                                    <div class="space-y-1 rounded-lg border p-4 max-h-96 overflow-y-auto">
                                        <div
                                            v-for="item in checklistRows"
                                            :key="item.row.id"
                                            class="flex items-center gap-2 py-1 text-sm"
                                        >
                                            <Checkbox
                                                :model-value="selectedRowTitles.has(item.title)"
                                                @update:model-value="toggleRowTitle(item.title)"
                                            />
                                            <ListChecks class="h-3 w-3 text-muted-foreground" />
                                            <span>{{ item.title }}</span>
                                        </div>
                                    </div>

                                    <p class="text-sm text-muted-foreground">
                                        {{ selectedRowTitles.size }} rows selected
                                    </p>
                                    <InputError :message="checklistForm.errors.titles" />
                                </div>

                                <div v-else-if="selectedChecklist && checklistRows.length === 0" class="rounded-lg border border-dashed p-6 text-center">
                                    <ListChecks class="mx-auto h-8 w-8 text-muted-foreground" />
                                    <p class="mt-2 text-sm text-muted-foreground">No rows with text found in this checklist.</p>
                                </div>

                                <div v-else-if="!checklists?.length" class="rounded-lg border border-dashed p-6 text-center">
                                    <ListChecks class="mx-auto h-8 w-8 text-muted-foreground" />
                                    <p class="mt-2 text-sm text-muted-foreground">No checklists found. Create a checklist first.</p>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <RestrictedAction>
                                    <Button type="submit" variant="cta" :disabled="isSubmitDisabled">
                                        Create Test Run
                                    </Button>
                                </RestrictedAction>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/test-runs`)">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
