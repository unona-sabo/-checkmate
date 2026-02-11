<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { Play, Layers, FileText } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    project: Project;
    testSuites: TestSuite[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Runs', href: `/projects/${props.project.id}/test-runs` },
    { title: 'Create', href: `/projects/${props.project.id}/test-runs/create` },
];

const form = useForm({
    name: '',
    description: '',
    environment: '',
    milestone: '',
    test_case_ids: [] as number[],
});

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

const submit = () => {
    form.post(`/projects/${props.project.id}/test-runs`);
};
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
                            Select test cases to include in this test run.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="space-y-2">
                                <Label for="name">Run Name</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="e.g., Sprint 1 Regression, Release 2.0 Smoke Test"
                                    :class="{ 'border-destructive': form.errors.name }"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Describe the purpose of this test run..."
                                    rows="2"
                                />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="environment">Environment</Label>
                                    <Input
                                        id="environment"
                                        v-model="form.environment"
                                        type="text"
                                        placeholder="e.g., Staging, Production"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="milestone">Milestone</Label>
                                    <Input
                                        id="milestone"
                                        v-model="form.milestone"
                                        type="text"
                                        placeholder="e.g., v2.0, Sprint 5"
                                    />
                                </div>
                            </div>

                            <!-- Test Case Selection -->
                            <div class="space-y-3">
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
                                                    :checked="isSuiteSelected(suite)"
                                                    :indeterminate="isSuitePartiallySelected(suite)"
                                                    @update:checked="toggleSuiteSelection(suite)"
                                                />
                                                <button
                                                    type="button"
                                                    @click="toggleSuite(suite.id)"
                                                    class="flex items-center gap-2 font-medium hover:text-primary"
                                                >
                                                    <Layers class="h-4 w-4" />
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
                                                        :checked="form.test_case_ids.includes(testCase.id)"
                                                        @update:checked="toggleTestCase(testCase.id)"
                                                    />
                                                    <FileText class="h-3 w-3 text-muted-foreground" />
                                                    <span>{{ testCase.title }}</span>
                                                </div>

                                                <!-- Child Suites -->
                                                <template v-for="child in suite.children" :key="child.id">
                                                    <div class="ml-4 space-y-1">
                                                        <div class="flex items-center gap-2 py-1">
                                                            <Checkbox
                                                                :checked="isSuiteSelected(child)"
                                                                :indeterminate="isSuitePartiallySelected(child)"
                                                                @update:checked="toggleSuiteSelection(child)"
                                                            />
                                                            <Layers class="h-3 w-3" />
                                                            <span class="font-medium text-sm">{{ child.name }}</span>
                                                        </div>
                                                        <div
                                                            v-for="tc in child.test_cases"
                                                            :key="tc.id"
                                                            class="ml-6 flex items-center gap-2 py-1 text-sm"
                                                        >
                                                            <Checkbox
                                                                :checked="form.test_case_ids.includes(tc.id)"
                                                                @update:checked="toggleTestCase(tc.id)"
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

                            <div class="flex gap-2">
                                <Button type="submit" variant="cta" :disabled="form.processing || !form.name || form.test_case_ids.length === 0">
                                    Create Test Run
                                </Button>
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
