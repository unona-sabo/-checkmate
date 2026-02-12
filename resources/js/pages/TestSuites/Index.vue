<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite, type TestCase } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Plus, ChevronRight, FileText, ExternalLink, FolderTree, GripVertical, Boxes, Layers, Check, Minus, MoreHorizontal, Trash2, Play, Copy, FolderPlus, Search, X } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ref, computed } from 'vue';

const props = defineProps<{
    project: Project;
    testSuites: TestSuite[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
];

const activeSuiteId = ref<number | null>(null);

// Collect all suites (parent + children) with their test cases
interface FlatSuite {
    id: number;
    name: string;
    parentName?: string;
    testCases: TestCase[];
}

const flatSuites = computed<FlatSuite[]>(() => {
    const result: FlatSuite[] = [];

    props.testSuites.forEach(suite => {
        // Add parent suite
        if (suite.test_cases?.length) {
            result.push({
                id: suite.id,
                name: suite.name,
                testCases: suite.test_cases,
            });
        }

        // Add child suites
        suite.children?.forEach(child => {
            if (child.test_cases?.length) {
                result.push({
                    id: child.id,
                    name: child.name,
                    parentName: suite.name,
                    testCases: child.test_cases,
                });
            }
        });
    });

    return result;
});

const totalTestCases = computed(() => {
    return flatSuites.value.reduce((acc, s) => acc + s.testCases.length, 0);
});

const scrollToSuite = (suiteId: number) => {
    activeSuiteId.value = suiteId;
    const element = document.getElementById(`suite-${suiteId}`);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

// Calculate total test cases count including children
const getSuiteTotalTestCases = (suite: TestSuite): number => {
    const ownCount = suite.test_cases?.length || 0;
    const childrenCount = suite.children?.reduce((acc, child) => acc + (child.test_cases?.length || 0), 0) || 0;
    return ownCount + childrenCount;
};

// Get total test cases for a flat suite (checks if it's a parent with children)
const getFlatSuiteTotalTestCases = (flatSuite: FlatSuite): number => {
    // If it's a child suite (has parentName), just return its own count
    if (flatSuite.parentName) {
        return flatSuite.testCases.length;
    }
    // If it's a parent suite, find it in localTestSuites and get total
    const originalSuite = localTestSuites.value.find(s => s.id === flatSuite.id);
    if (originalSuite) {
        return getSuiteTotalTestCases(originalSuite);
    }
    return flatSuite.testCases.length;
};

// Mutable copies for drag-and-drop (moved here for proper order)
const localTestSuites = ref<TestSuite[]>([...props.testSuites]);

// Selection state - use array for better Vue reactivity
const selectedTestCaseIds = ref<number[]>([]);

// Check if a test case is selected
const isTestCaseSelected = (id: number): boolean => {
    return selectedTestCaseIds.value.includes(id);
};

// Get all test case IDs
const allTestCaseIds = computed<number[]>(() => {
    const ids: number[] = [];
    localTestSuites.value.forEach(suite => {
        suite.test_cases?.forEach(tc => ids.push(tc.id));
        suite.children?.forEach(child => {
            child.test_cases?.forEach(tc => ids.push(tc.id));
        });
    });
    return ids;
});

// Check if all test cases are selected
const isAllSelected = computed(() => {
    if (allTestCaseIds.value.length === 0) return false;
    return allTestCaseIds.value.every(id => selectedTestCaseIds.value.includes(id));
});

// Check if some test cases are selected
const isSomeSelected = computed(() => {
    return selectedTestCaseIds.value.length > 0 && !isAllSelected.value;
});

// Toggle select all
const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedTestCaseIds.value = [];
    } else {
        selectedTestCaseIds.value = [...allTestCaseIds.value];
    }
};

// Computed: suite selection states (reactive)
const suiteSelectionStates = computed(() => {
    const states: Record<number, { isFullySelected: boolean; isPartiallySelected: boolean; totalCount: number; testCaseIds: number[] }> = {};

    localFlatSuites.value.forEach(suite => {
        const ids: number[] = [];

        // Add own test cases
        suite.testCases.forEach(tc => ids.push(tc.id));

        // If it's a parent suite (no parentName), also add children's test cases
        if (!suite.parentName) {
            const originalSuite = localTestSuites.value.find(s => s.id === suite.id);
            if (originalSuite?.children) {
                originalSuite.children.forEach(child => {
                    child.test_cases?.forEach(tc => ids.push(tc.id));
                });
            }
        }

        const selectedCount = ids.filter(id => selectedTestCaseIds.value.includes(id)).length;
        const totalCount = ids.length;

        states[suite.id] = {
            isFullySelected: totalCount > 0 && selectedCount === totalCount,
            isPartiallySelected: selectedCount > 0 && selectedCount < totalCount,
            totalCount,
            testCaseIds: ids,
        };
    });

    return states;
});

// Get suite selection state
const getSuiteState = (suiteId: number) => {
    return suiteSelectionStates.value[suiteId] || { isFullySelected: false, isPartiallySelected: false, totalCount: 0, testCaseIds: [] };
};

// Toggle selection for a flat suite (including children for parent suites)
const toggleFlatSuiteSelection = (suite: FlatSuite) => {
    const state = getSuiteState(suite.id);
    const ids = state.testCaseIds;

    if (state.isFullySelected) {
        // Deselect all - remove these ids from selection
        selectedTestCaseIds.value = selectedTestCaseIds.value.filter(id => !ids.includes(id));
    } else {
        // Select all - add missing ids
        const newIds = ids.filter(id => !selectedTestCaseIds.value.includes(id));
        selectedTestCaseIds.value = [...selectedTestCaseIds.value, ...newIds];
    }
};

// Toggle selection for a single test case
const toggleTestCaseSelection = (testCaseId: number) => {
    const index = selectedTestCaseIds.value.indexOf(testCaseId);
    if (index > -1) {
        selectedTestCaseIds.value = selectedTestCaseIds.value.filter(id => id !== testCaseId);
    } else {
        selectedTestCaseIds.value = [...selectedTestCaseIds.value, testCaseId];
    }
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'critical': return 'bg-red-500/10 text-red-600 border-red-200 dark:text-red-400 dark:border-red-800';
        case 'high': return 'bg-orange-500/10 text-orange-600 border-orange-200 dark:text-orange-400 dark:border-orange-800';
        case 'medium': return 'bg-yellow-500/10 text-yellow-600 border-yellow-200 dark:text-yellow-400 dark:border-yellow-800';
        case 'low': return 'bg-blue-500/10 text-blue-600 border-blue-200 dark:text-blue-400 dark:border-blue-800';
        default: return '';
    }
};

// Drag and drop for test suites in navigation
const draggedSuiteIndex = ref<number | null>(null);
const dragOverSuiteIndex = ref<number | null>(null);
const isSaving = ref(false);

const onSuiteDragStart = (index: number, event: DragEvent) => {
    draggedSuiteIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', `suite-${index}`);
    }
};

const onSuiteDragOver = (index: number, event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    dragOverSuiteIndex.value = index;
};

const onSuiteDragLeave = () => {
    dragOverSuiteIndex.value = null;
};

const onSuiteDrop = (index: number, event: DragEvent) => {
    event.preventDefault();
    if (draggedSuiteIndex.value !== null && draggedSuiteIndex.value !== index) {
        const draggedSuite = localTestSuites.value[draggedSuiteIndex.value];
        localTestSuites.value.splice(draggedSuiteIndex.value, 1);
        localTestSuites.value.splice(index, 0, draggedSuite);
        saveSuiteOrder();
    }
    draggedSuiteIndex.value = null;
    dragOverSuiteIndex.value = null;
};

const onSuiteDragEnd = () => {
    draggedSuiteIndex.value = null;
    dragOverSuiteIndex.value = null;
};

const saveSuiteOrder = () => {
    isSaving.value = true;
    const suites = localTestSuites.value.map((suite, index) => ({
        id: suite.id,
        order: index + 1,
        parent_id: suite.parent_id || null,
    }));

    router.post(`/projects/${props.project.id}/test-suites/reorder`, { suites }, {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false;
        },
    });
};

// Drag and drop for test cases
const draggedTestCase = ref<{ suiteId: number; index: number; testCase: TestCase } | null>(null);
const dragOverTestCase = ref<{ suiteId: number; index: number } | null>(null);

const onTestCaseDragStart = (suiteId: number, index: number, testCase: TestCase, event: DragEvent) => {
    draggedTestCase.value = { suiteId, index, testCase };
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', `testcase-${suiteId}-${index}`);
    }
};

const onTestCaseDragOver = (suiteId: number, index: number, event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    dragOverTestCase.value = { suiteId, index };
};

const onTestCaseDragLeave = () => {
    dragOverTestCase.value = null;
};

const onTestCaseDrop = (targetSuiteId: number, targetIndex: number, event: DragEvent) => {
    event.preventDefault();
    if (!draggedTestCase.value) return;

    const { suiteId: sourceSuiteId, index: sourceIndex, testCase } = draggedTestCase.value;

    // Find source and target suites in localTestSuites
    const sourceSuite = findSuiteById(sourceSuiteId);
    const targetSuite = findSuiteById(targetSuiteId);

    if (!sourceSuite || !targetSuite) return;

    // Remove from source
    const sourceTestCases = sourceSuite.test_cases || [];
    sourceTestCases.splice(sourceIndex, 1);

    // Add to target
    const targetTestCases = targetSuite.test_cases || [];
    targetTestCases.splice(targetIndex, 0, testCase);

    // Update suite references
    sourceSuite.test_cases = sourceTestCases;
    targetSuite.test_cases = targetTestCases;

    saveTestCaseOrder(targetSuiteId, targetTestCases, sourceSuiteId !== targetSuiteId ? sourceSuiteId : undefined, sourceSuiteId !== targetSuiteId ? sourceTestCases : undefined);

    draggedTestCase.value = null;
    dragOverTestCase.value = null;
};

const onTestCaseDragEnd = () => {
    draggedTestCase.value = null;
    dragOverTestCase.value = null;
};

const findSuiteById = (suiteId: number): TestSuite | undefined => {
    for (const suite of localTestSuites.value) {
        if (suite.id === suiteId) return suite;
        if (suite.children) {
            const child = suite.children.find(c => c.id === suiteId);
            if (child) return child;
        }
    }
    return undefined;
};

const saveTestCaseOrder = (suiteId: number, testCases: TestCase[], sourceSuiteId?: number, sourceTestCases?: TestCase[]) => {
    isSaving.value = true;

    const cases: { id: number; order: number; test_suite_id: number }[] = [];

    // Add target suite cases
    testCases.forEach((tc, index) => {
        cases.push({
            id: tc.id,
            order: index + 1,
            test_suite_id: suiteId,
        });
    });

    // Add source suite cases if moved between suites
    if (sourceSuiteId && sourceTestCases) {
        sourceTestCases.forEach((tc, index) => {
            cases.push({
                id: tc.id,
                order: index + 1,
                test_suite_id: sourceSuiteId,
            });
        });
    }

    router.post(`/projects/${props.project.id}/test-suites/reorder-cases`, { cases }, {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false;
        },
    });
};

// Update flatSuites to use localTestSuites
const localFlatSuites = computed<FlatSuite[]>(() => {
    const result: FlatSuite[] = [];

    localTestSuites.value.forEach(suite => {
        // Add parent suite (if has test cases OR has children)
        if (suite.test_cases?.length || suite.children?.length) {
            result.push({
                id: suite.id,
                name: suite.name,
                testCases: suite.test_cases || [],
            });
        }

        // Add all child suites (even without test cases)
        suite.children?.forEach(child => {
            result.push({
                id: child.id,
                name: child.name,
                parentName: suite.name,
                testCases: child.test_cases || [],
            });
        });
    });

    return result;
});

const localTotalTestCases = computed(() => {
    return localFlatSuites.value.reduce((acc, s) => acc + s.testCases.length, 0);
});

// Search
const searchQuery = ref('');

const filteredFlatSuites = computed(() => {
    if (!searchQuery.value.trim()) return localFlatSuites.value;
    const query = searchQuery.value.toLowerCase();
    return localFlatSuites.value
        .map(suite => ({
            ...suite,
            testCases: suite.testCases.filter(tc => tc.title.toLowerCase().includes(query)),
        }))
        .filter(suite => suite.testCases.length > 0 || suite.name.toLowerCase().includes(query));
});
</script>

<template>
    <Head title="Test Suites" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                        <Layers class="h-6 w-6 text-primary" />
                        Test Suites
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        {{ localTestSuites.length }} suites · {{ localTotalTestCases }} test cases
                        <span v-if="isSaving" class="ml-2 text-primary">Saving...</span>
                    </p>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="testSuites.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <Layers class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="text-lg font-semibold">No test suites yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground max-w-sm">
                        Create your first test suite to organize your test cases into logical groups.
                    </p>
                    <Link :href="`/projects/${project.id}/test-suites/create`" class="mt-4 inline-block">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Create Test Suite
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Main Content -->
            <div v-else class="flex flex-col flex-1 min-h-0">
                <!-- Action Header -->
                <div class="flex items-center mb-3 gap-6">
                    <!-- Spacer for left column -->
                    <div class="w-[480px] shrink-0"></div>
                    <!-- Right side - Selection controls and New Test Suite button -->
                    <div class="flex items-center justify-between flex-1 max-w-4xl pr-2">
                        <div v-if="localTotalTestCases > 0 && filteredFlatSuites.length > 0" class="flex items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-xs font-medium border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground h-8 px-3 cursor-pointer"
                                @click="toggleSelectAll"
                            >
                                <div
                                    class="h-3.5 w-3.5 shrink-0 rounded-[4px] border shadow-xs flex items-center justify-center"
                                    :class="isAllSelected || isSomeSelected ? 'bg-primary border-primary text-primary-foreground' : 'border-input'"
                                >
                                    <Minus v-if="isSomeSelected" class="h-3 w-3" />
                                    <Check v-else-if="isAllSelected" class="h-3 w-3" />
                                </div>
                                {{ isAllSelected ? 'Deselect All' : 'Select All' }}
                            </button>
                            <span v-if="selectedTestCaseIds.length > 0" class="text-sm text-muted-foreground">
                                {{ selectedTestCaseIds.length }} of {{ localTotalTestCases }} selected
                            </span>
                            <!-- Actions dropdown when test cases are selected -->
                            <DropdownMenu v-if="selectedTestCaseIds.length > 0">
                                <DropdownMenuTrigger as-child>
                                    <Button class="gap-2 h-8">
                                        <MoreHorizontal class="h-4 w-4" />
                                        Actions ({{ selectedTestCaseIds.length }})
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuLabel>Selected Test Cases</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>
                                        <Play class="h-4 w-4 mr-2" />
                                        Create Test Run
                                    </DropdownMenuItem>
                                    <DropdownMenuItem>
                                        <Copy class="h-4 w-4 mr-2" />
                                        Copy to Test Suite
                                    </DropdownMenuItem>
                                    <DropdownMenuItem>
                                        <FolderPlus class="h-4 w-4 mr-2" />
                                        Create Subcategory
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="text-destructive focus:text-destructive">
                                        <Trash2 class="h-4 w-4 mr-2" />
                                        Delete Test Cases
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div v-else></div>
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
                            <Link :href="`/projects/${project.id}/test-suites/create`">
                                <Button variant="cta" class="gap-2">
                                    <Plus class="h-4 w-4" />
                                    New Test Suite
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="flex gap-6 flex-1 min-h-0">
                <!-- Left: Test Suites Navigation -->
                <div class="w-[480px] shrink-0">
                    <div class="sticky top-0 rounded-xl border bg-card shadow-sm">
                        <div class="p-3 border-b bg-muted/30">
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <FolderTree class="h-4 w-4 text-primary" />
                                <span>Navigation</span>
                            </div>
                        </div>

                        <div class="p-2 space-y-0.5 max-h-[calc(100vh-220px)] overflow-y-auto">
                            <template v-for="(suite, suiteIndex) in localTestSuites" :key="suite.id">
                                <!-- Parent Suite -->
                                <div
                                    class="group flex items-center justify-between rounded-lg px-3 py-2 cursor-pointer transition-all duration-150"
                                    :class="[
                                        activeSuiteId === suite.id
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'hover:bg-muted/70',
                                        {
                                            'border-t-2 border-t-primary': dragOverSuiteIndex === suiteIndex,
                                            'opacity-50': draggedSuiteIndex === suiteIndex
                                        }
                                    ]"
                                    @click="scrollToSuite(suite.id)"
                                    @dragover="onSuiteDragOver(suiteIndex, $event)"
                                    @dragleave="onSuiteDragLeave"
                                    @drop="onSuiteDrop(suiteIndex, $event)"
                                >
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <div
                                            draggable="true"
                                            @dragstart="onSuiteDragStart(suiteIndex, $event)"
                                            @dragend="onSuiteDragEnd"
                                            class="cursor-grab active:cursor-grabbing shrink-0"
                                            @click.stop
                                        >
                                            <GripVertical class="h-4 w-4 text-muted-foreground/50" />
                                        </div>
                                        <Layers class="h-4 w-4 shrink-0" :class="activeSuiteId === suite.id ? '' : 'text-primary'" />
                                        <span class="font-medium text-sm truncate max-w-[280px]">{{ suite.name }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0 ml-3">
                                        <span
                                            class="text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                                            :class="activeSuiteId === suite.id ? 'bg-primary-foreground/20' : 'bg-muted text-muted-foreground'"
                                        >
                                            {{ getSuiteTotalTestCases(suite) }}
                                        </span>
                                        <Link
                                            :href="`/projects/${project.id}/test-suites/${suite.id}`"
                                            @click.stop
                                            class="p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity"
                                            :class="activeSuiteId === suite.id ? 'hover:bg-primary-foreground/20' : 'hover:bg-muted'"
                                        >
                                            <ExternalLink class="h-3 w-3" />
                                        </Link>
                                    </div>
                                </div>

                                <!-- Child Suites -->
                                <template v-if="suite.children?.length">
                                    <div
                                        v-for="child in suite.children"
                                        :key="child.id"
                                        class="group flex items-center justify-between rounded-lg px-3 py-1.5 ml-4 cursor-pointer transition-all duration-150"
                                        :class="[
                                            activeSuiteId === child.id
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'hover:bg-muted/70'
                                        ]"
                                        @click="scrollToSuite(child.id)"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <Boxes class="h-3.5 w-3.5 shrink-0" :class="activeSuiteId === child.id ? '' : 'text-yellow-500'" />
                                            <span class="text-sm truncate max-w-[240px]">{{ child.name }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0 ml-3">
                                            <span
                                                class="text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                                                :class="activeSuiteId === child.id ? 'bg-primary-foreground/20' : 'bg-muted text-muted-foreground'"
                                            >
                                                {{ child.test_cases?.length || 0 }}
                                            </span>
                                            <Link
                                                :href="`/projects/${project.id}/test-suites/${child.id}`"
                                                @click.stop
                                                class="p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity"
                                                :class="activeSuiteId === child.id ? 'hover:bg-primary-foreground/20' : 'hover:bg-muted'"
                                            >
                                                <ExternalLink class="h-3 w-3" />
                                            </Link>
                                        </div>
                                    </div>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Right: Test Cases List -->
                <div class="flex-1 overflow-y-auto min-h-0 pr-2 max-w-4xl">
                    <div v-if="filteredFlatSuites.length === 0 && searchQuery.trim()" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                        <Search class="h-12 w-12 mb-3" />
                        <p class="font-semibold">No results found</p>
                        <p class="text-sm">No test cases match "{{ searchQuery }}"</p>
                    </div>
                    <div v-else-if="filteredFlatSuites.length === 0" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                        <FileText class="h-12 w-12 mb-3" />
                        <p class="font-semibold">No test cases yet</p>
                        <p class="text-sm">Add test cases to your suites to see them here.</p>
                    </div>

                    <div v-else class="space-y-1">
                        <div
                            v-for="suite in filteredFlatSuites"
                            :key="suite.id"
                            :id="`suite-${suite.id}`"
                            class="scroll-mt-4 mt-2.5 first:mt-0"
                        >
                            <!-- Suite Header -->
                            <div
                                class="group/header flex items-center justify-between mb-2 sticky top-0 bg-card/95 backdrop-blur-sm z-10 rounded-xl border shadow-sm cursor-pointer transition-all duration-150 hover:border-primary/50"
                                :class="suite.parentName ? 'py-2 px-3' : 'py-3.5 px-4'"
                                @click="router.visit(`/projects/${project.id}/test-suites/${suite.id}`)"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-4 w-4 shrink-0 rounded-[4px] border shadow-xs flex items-center justify-center cursor-pointer transition-colors"
                                        :class="[
                                            getSuiteState(suite.id).isFullySelected || getSuiteState(suite.id).isPartiallySelected
                                                ? 'bg-primary border-primary text-primary-foreground'
                                                : 'border-input',
                                            { 'opacity-50 pointer-events-none': getSuiteState(suite.id).totalCount === 0 }
                                        ]"
                                        @click.stop="toggleFlatSuiteSelection(suite)"
                                    >
                                        <Minus v-if="getSuiteState(suite.id).isPartiallySelected" class="h-3 w-3" />
                                        <Check v-else-if="getSuiteState(suite.id).isFullySelected" class="h-3 w-3" />
                                    </div>
                                    <div
                                        class="rounded-lg flex items-center justify-center transition-colors"
                                        :class="[
                                            suite.parentName ? 'h-7 w-7 bg-yellow-500/10 group-hover/header:bg-primary/10' : 'h-8 w-8 bg-primary/10'
                                        ]"
                                    >
                                        <Boxes v-if="suite.parentName" class="h-3.5 w-3.5 text-yellow-500 group-hover/header:text-primary transition-colors" />
                                        <Layers v-else class="h-4 w-4 text-primary" />
                                    </div>
                                    <div>
                                        <h3 :class="suite.parentName ? 'font-semibold text-[13px]' : 'font-semibold text-base'" class="group-hover/header:text-primary transition-colors">{{ suite.name }}</h3>
                                        <p v-if="suite.parentName" class="text-[11px] text-muted-foreground">
                                            in {{ suite.parentName }}
                                        </p>
                                    </div>
                                    <Badge variant="secondary" :class="suite.parentName ? 'text-[11px] ml-1' : 'text-xs ml-2'" class="font-normal">
                                        {{ getFlatSuiteTotalTestCases(suite) }} {{ getFlatSuiteTotalTestCases(suite) === 1 ? 'case' : 'cases' }}
                                    </Badge>
                                </div>
                                <Link :href="`/projects/${project.id}/test-suites/${suite.id}/test-cases/create`" @click.stop>
                                    <Button variant="outline" size="sm" :class="suite.parentName ? 'h-7 text-[11px] gap-1 px-2.5' : 'h-8 text-xs gap-1.5'">
                                        <Plus :class="suite.parentName ? 'h-3.5 w-3.5' : 'h-3.5 w-3.5'" />
                                        Add
                                    </Button>
                                </Link>
                            </div>

                            <!-- Test Cases -->
                            <div v-if="suite.testCases.length" class="space-y-[3px]">
                                <div
                                    v-for="(testCase, tcIndex) in suite.testCases"
                                    :key="testCase.id"
                                    class="group flex items-center justify-between px-4 py-2.5 rounded-xl border bg-card hover:border-primary/50 hover:shadow-sm transition-all duration-150"
                                    :class="{
                                        'border-t-2 border-t-primary': dragOverTestCase?.suiteId === suite.id && dragOverTestCase?.index === tcIndex,
                                        'opacity-50': draggedTestCase?.suiteId === suite.id && draggedTestCase?.index === tcIndex,
                                        'border-primary/50 bg-primary/5': isTestCaseSelected(testCase.id)
                                    }"
                                    @dragover="onTestCaseDragOver(suite.id, tcIndex, $event)"
                                    @dragleave="onTestCaseDragLeave"
                                    @drop="onTestCaseDrop(suite.id, tcIndex, $event)"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div
                                            class="h-4 w-4 shrink-0 rounded-[4px] border shadow-xs flex items-center justify-center cursor-pointer transition-colors"
                                            :class="isTestCaseSelected(testCase.id)
                                                ? 'bg-primary border-primary text-primary-foreground'
                                                : 'border-input'"
                                            @click.stop="toggleTestCaseSelection(testCase.id)"
                                        >
                                            <Check v-if="isTestCaseSelected(testCase.id)" class="h-3 w-3" />
                                        </div>
                                        <div
                                            draggable="true"
                                            @dragstart="onTestCaseDragStart(suite.id, tcIndex, testCase, $event)"
                                            @dragend="onTestCaseDragEnd"
                                            class="cursor-grab active:cursor-grabbing"
                                        >
                                            <GripVertical class="h-4 w-4 text-muted-foreground/50" />
                                        </div>
                                        <Link
                                            :href="`/projects/${project.id}/test-suites/${suite.id}/test-cases/${testCase.id}`"
                                            class="flex items-center gap-3 min-w-0 flex-1"
                                        >
                                            <div class="h-7 w-7 rounded-lg bg-muted/50 flex items-center justify-center shrink-0 group-hover:bg-primary/10 transition-colors">
                                                <FileText class="h-3.5 w-3.5 text-muted-foreground group-hover:text-primary transition-colors" />
                                            </div>
                                            <p class="text-sm font-medium truncate group-hover:text-primary transition-colors">
                                                {{ testCase.title }}
                                            </p>
                                        </Link>
                                    </div>
                                    <Link
                                        :href="`/projects/${project.id}/test-suites/${suite.id}/test-cases/${testCase.id}`"
                                        class="flex items-center gap-2 shrink-0 ml-4"
                                    >
                                        <Badge :class="getPriorityColor(testCase.priority)" variant="outline" class="text-[10px] px-1.5 h-4 font-medium">
                                            {{ testCase.priority }}
                                        </Badge>
                                        <Badge variant="secondary" class="text-[10px] px-1.5 h-4 font-normal">
                                            {{ testCase.type }}
                                        </Badge>
                                        <ChevronRight class="h-4 w-4 text-muted-foreground group-hover:text-primary transition-colors" />
                                    </Link>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
