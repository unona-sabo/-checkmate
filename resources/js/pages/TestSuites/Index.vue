<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite, type TestCase } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Plus, FileText, ExternalLink, FolderTree, GripVertical, Boxes, Layers, Check, Minus, MoreHorizontal, Trash2, Play, Copy, FolderPlus, Search, X, StickyNote, Pencil, Filter, Loader2, Upload, Download, FileSpreadsheet, Zap, RotateCcw } from 'lucide-vue-next';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import RestrictedAction from '@/components/RestrictedAction.vue';
import FeatureBadges from '@/components/FeatureBadges.vue';
import FeatureSelector from '@/components/FeatureSelector.vue';
import { priorityVariant, testTypeVariant } from '@/lib/badge-variants';
import { ref, computed, onMounted, watch } from 'vue';
import { useSearch } from '@/composables/useSearch';

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'smoke': return Zap;
        case 'regression': return RotateCcw;
        default: return FileText;
    }
};

const props = defineProps<{
    project: Project;
    testSuites: TestSuite[];
    users: { id: number; name: string }[];
    availableFeatures: { id: number; name: string; module: string[] | null }[];
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
    type: string;
    parentName?: string;
    testCases: TestCase[];
    projectFeatures?: { id: number; name?: string; module?: string[] | null }[];
}

const flatSuites = computed<FlatSuite[]>(() => {
    const result: FlatSuite[] = [];

    props.testSuites.forEach(suite => {
        // Add parent suite
        if (suite.test_cases?.length) {
            result.push({
                id: suite.id,
                name: suite.name,
                type: suite.type,
                testCases: suite.test_cases,
                projectFeatures: suite.project_features,
            });
        }

        // Add child suites
        suite.children?.forEach(child => {
            if (child.test_cases?.length) {
                result.push({
                    id: child.id,
                    name: child.name,
                    type: child.type,
                    parentName: suite.name,
                    testCases: child.test_cases,
                    projectFeatures: child.project_features,
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

// Get filtered (visible) test case IDs — respects search and filters
const filteredTestCaseIds = computed<number[]>(() => {
    const ids: number[] = [];
    filteredFlatSuites.value.forEach(suite => {
        suite.testCases.forEach(tc => ids.push(tc.id));
    });
    return ids;
});

// Check if all visible test cases are selected
const isAllSelected = computed(() => {
    if (filteredTestCaseIds.value.length === 0) return false;
    return filteredTestCaseIds.value.every(id => selectedTestCaseIds.value.includes(id));
});

// Check if some test cases are selected
const isSomeSelected = computed(() => {
    return selectedTestCaseIds.value.length > 0 && !isAllSelected.value;
});

// Toggle select all — only affects visible/filtered test cases
const toggleSelectAll = () => {
    if (isAllSelected.value) {
        const filteredIds = new Set(filteredTestCaseIds.value);
        selectedTestCaseIds.value = selectedTestCaseIds.value.filter(id => !filteredIds.has(id));
    } else {
        const newIds = filteredTestCaseIds.value.filter(id => !selectedTestCaseIds.value.includes(id));
        selectedTestCaseIds.value = [...selectedTestCaseIds.value, ...newIds];
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
        // Always add parent suite block
        result.push({
            id: suite.id,
            name: suite.name,
            type: suite.type,
            testCases: suite.test_cases || [],
            projectFeatures: suite.project_features,
        });

        // Add all child suites (even without test cases)
        suite.children?.forEach(child => {
            result.push({
                id: child.id,
                name: child.name,
                type: child.type,
                parentName: suite.name,
                testCases: child.test_cases || [],
                projectFeatures: child.project_features,
            });
        });
    });

    return result;
});

const localTotalTestCases = computed(() => {
    return localFlatSuites.value.reduce((acc, s) => acc + s.testCases.length, 0);
});

// Search
const { searchQuery, highlight } = useSearch();

// Filters
const showFilters = ref(false);
const filterType = ref('');
const filterPriority = ref('');
const filterSeverity = ref('');
const filterAutomation = ref('');
const filterAuthor = ref('');
const filterFeature = ref('');
const filterModule = ref('');
const filterCreatedFrom = ref('');
const filterCreatedTo = ref('');
const filterUpdatedFrom = ref('');
const filterUpdatedTo = ref('');

const activeFilterCount = computed(() => {
    return [filterType, filterPriority, filterSeverity, filterAutomation, filterAuthor, filterFeature, filterModule, filterCreatedFrom, filterCreatedTo, filterUpdatedFrom, filterUpdatedTo]
        .filter(f => f.value !== '').length;
});

const clearFilters = () => {
    filterType.value = '';
    filterPriority.value = '';
    filterSeverity.value = '';
    filterAutomation.value = '';
    filterAuthor.value = '';
    filterFeature.value = '';
    filterModule.value = '';
    filterCreatedFrom.value = '';
    filterCreatedTo.value = '';
    filterUpdatedFrom.value = '';
    filterUpdatedTo.value = '';
};

const filteredFlatSuites = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const hasSearch = query.length > 0;
    const hasFilters = activeFilterCount.value > 0;

    if (!hasSearch && !hasFilters) return localFlatSuites.value;

    return localFlatSuites.value
        .map(suite => ({
            ...suite,
            testCases: suite.testCases.filter(tc => {
                // Search filter
                if (hasSearch && !tc.title.toLowerCase().includes(query)) return false;
                // Type filter
                if (filterType.value && tc.type !== filterType.value) return false;
                // Priority filter
                if (filterPriority.value && tc.priority !== filterPriority.value) return false;
                // Severity filter
                if (filterSeverity.value && tc.severity !== filterSeverity.value) return false;
                // Automation filter
                if (filterAutomation.value && tc.automation_status !== filterAutomation.value) return false;
                // Author filter
                if (filterAuthor.value && String(tc.created_by) !== filterAuthor.value) return false;
                // Feature filter
                if (filterFeature.value === '__none__') {
                    if (tc.project_features && tc.project_features.length > 0) return false;
                } else if (filterFeature.value) {
                    if (!tc.project_features?.some(f => String(f.id) === filterFeature.value)) return false;
                }
                // Module filter
                if (filterModule.value === '__none__') {
                    if (tc.module && tc.module.length > 0) return false;
                } else if (filterModule.value) {
                    if (!tc.module?.includes(filterModule.value)) return false;
                }
                // Date filters
                if (filterCreatedFrom.value && tc.created_at < filterCreatedFrom.value) return false;
                if (filterCreatedTo.value && tc.created_at.slice(0, 10) > filterCreatedTo.value) return false;
                if (filterUpdatedFrom.value && tc.updated_at < filterUpdatedFrom.value) return false;
                if (filterUpdatedTo.value && tc.updated_at.slice(0, 10) > filterUpdatedTo.value) return false;
                return true;
            }),
        }))
        .filter(suite => suite.testCases.length > 0 || (hasSearch && suite.name.toLowerCase().includes(query)));
});

const filteredTestCaseCount = computed(() => {
    return filteredFlatSuites.value.reduce((acc, s) => acc + s.testCases.length, 0);
});

// === Action Dialogs ===

// Create Test Run dialog
const showTestRunDialog = ref(false);
const testRunName = ref('');
const testRunDescription = ref('');
const testRunPriority = ref('');
const testRunEnvPreset = ref('');
const testRunEnvNotes = ref('');
const testRunMilestone = ref('');
const isCreatingTestRun = ref(false);

const openTestRunDialog = () => {
    // Pre-fill name based on first selected suite
    const firstSelectedId = selectedTestCaseIds.value[0];
    let suiteName = '';
    for (const suite of localFlatSuites.value) {
        if (suite.testCases.some(tc => tc.id === firstSelectedId)) {
            suiteName = suite.name;
            break;
        }
    }
    testRunName.value = suiteName ? `${suiteName} Test Run` : 'Test Run';
    testRunDescription.value = '';
    testRunPriority.value = '';
    testRunEnvPreset.value = '';
    testRunEnvNotes.value = '';
    testRunMilestone.value = '';
    showTestRunDialog.value = true;
};

const createTestRun = () => {
    if (!testRunName.value.trim() || selectedTestCaseIds.value.length === 0) return;
    isCreatingTestRun.value = true;

    const environment = [testRunEnvPreset.value, testRunEnvNotes.value].filter(Boolean).join(' - ') || null;

    router.post(`/projects/${props.project.id}/test-runs`, {
        name: testRunName.value.trim(),
        description: testRunDescription.value || null,
        priority: testRunPriority.value || null,
        environment,
        milestone: testRunMilestone.value || null,
        test_case_ids: selectedTestCaseIds.value,
    }, {
        onSuccess: () => {
            showTestRunDialog.value = false;
            isCreatingTestRun.value = false;
        },
        onError: () => {
            isCreatingTestRun.value = false;
        },
    });
};

// Copy to Test Suite dialog
const showCopyDialog = ref(false);
const copyTargetProjectId = ref('');
const copyTargetSuiteId = ref('');
const isCopying = ref(false);
const copyAttachments = ref(true);
const copyFeatures = ref(true);
const copyNotes = ref(true);
const availableProjects = ref<{ id: number; name: string }[]>([]);
const availableSuites = ref<{ id: number; name: string; children?: { id: number; name: string }[] }[]>([]);
const loadingProjects = ref(false);
const loadingSuites = ref(false);

const isCrossProject = computed(() => {
    return copyTargetProjectId.value && Number(copyTargetProjectId.value) !== props.project.id;
});

const allSuiteOptions = computed(() => {
    const options: { id: number; name: string; label: string }[] = [];
    const source = isCrossProject.value ? availableSuites.value : props.testSuites;
    source.forEach(suite => {
        options.push({ id: suite.id, name: suite.name, label: suite.name });
        suite.children?.forEach(child => {
            options.push({ id: child.id, name: child.name, label: `${suite.name} / ${child.name}` });
        });
    });
    return options;
});

const openCopyDialog = () => {
    copyTargetProjectId.value = String(props.project.id);
    copyTargetSuiteId.value = '';
    copyAttachments.value = true;
    copyFeatures.value = true;
    copyNotes.value = true;
    availableSuites.value = [];
    showCopyDialog.value = true;

    loadingProjects.value = true;
    fetch(`/projects/${props.project.id}/test-suites/copy-projects`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(res => res.json())
        .then(data => { availableProjects.value = data; })
        .finally(() => { loadingProjects.value = false; });
};

watch(copyTargetProjectId, (newVal) => {
    copyTargetSuiteId.value = '';
    if (!newVal) return;

    if (Number(newVal) === props.project.id) {
        availableSuites.value = [];
        return;
    }

    loadingSuites.value = true;
    fetch(`/projects/${props.project.id}/test-suites/copy-suites?project_id=${newVal}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(res => res.json())
        .then(data => { availableSuites.value = data; })
        .finally(() => { loadingSuites.value = false; });
});

const copyToSuite = () => {
    if (!copyTargetSuiteId.value || selectedTestCaseIds.value.length === 0) return;
    isCopying.value = true;

    router.post(`/projects/${props.project.id}/test-suites/bulk-copy-cases`, {
        test_case_ids: selectedTestCaseIds.value,
        target_suite_id: Number(copyTargetSuiteId.value),
        target_project_id: isCrossProject.value ? Number(copyTargetProjectId.value) : null,
        copy_attachments: copyAttachments.value,
        copy_features: copyFeatures.value,
        copy_notes: copyNotes.value,
    }, {
        preserveState: false,
        onSuccess: () => {
            showCopyDialog.value = false;
            selectedTestCaseIds.value = [];
            isCopying.value = false;
        },
        onError: () => {
            isCopying.value = false;
        },
    });
};

// Delete Test Cases dialog
const showDeleteDialog = ref(false);
const isDeleting = ref(false);

const openDeleteDialog = () => {
    showDeleteDialog.value = true;
};

const deleteTestCases = () => {
    if (selectedTestCaseIds.value.length === 0) return;
    isDeleting.value = true;

    router.post(`/projects/${props.project.id}/test-suites/bulk-delete-cases`, {
        test_case_ids: selectedTestCaseIds.value,
    }, {
        preserveState: false,
        onSuccess: () => {
            showDeleteDialog.value = false;
            selectedTestCaseIds.value = [];
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        },
    });
};

// Create Subcategory dialog
const showSubcategoryDialog = ref(false);
const subcategoryParentId = ref('');
const subcategoryName = ref('');
const subcategoryDescription = ref('');
const subcategoryType = ref('functional');
const subcategoryFeatureIds = ref<number[]>([]);
const isCreatingSubcategory = ref(false);

const openSubcategoryDialog = () => {
    subcategoryName.value = '';
    subcategoryDescription.value = '';
    subcategoryType.value = 'functional';
    subcategoryFeatureIds.value = [];

    // Auto-detect parent suite from first selected test case
    const firstId = selectedTestCaseIds.value[0];
    let detectedParentId = '';
    if (firstId) {
        for (const suite of props.testSuites) {
            if (suite.test_cases?.some(tc => tc.id === firstId)) {
                detectedParentId = String(suite.id);
                break;
            }
            if (suite.children?.some(child => child.test_cases?.some(tc => tc.id === firstId))) {
                detectedParentId = String(suite.id);
                break;
            }
        }
    }
    subcategoryParentId.value = detectedParentId;
    showSubcategoryDialog.value = true;
};

const createSubcategory = () => {
    if (!subcategoryParentId.value || !subcategoryName.value.trim()) return;
    isCreatingSubcategory.value = true;

    router.post(`/projects/${props.project.id}/test-suites`, {
        name: subcategoryName.value.trim(),
        description: subcategoryDescription.value || null,
        type: subcategoryType.value,
        parent_id: Number(subcategoryParentId.value),
        feature_ids: subcategoryFeatureIds.value,
        test_case_ids: selectedTestCaseIds.value,
    }, {
        preserveState: false,
        onSuccess: () => {
            showSubcategoryDialog.value = false;
            isCreatingSubcategory.value = false;
            selectedTestCaseIds.value = [];
        },
        onError: () => {
            isCreatingSubcategory.value = false;
        },
    });
};

// Import/Export
const showImportDialog = ref(false);
const importFile = ref<File | null>(null);
const importHeaders = ref<string[]>([]);
const importRows = ref<any[][]>([]);
const importParentSuiteId = ref<string>('');
const importSubcategoryId = ref<string>('');
const isImportingCases = ref(false);

const importSubcategoryOptions = computed(() => {
    if (!importParentSuiteId.value) return [];
    const parent = props.testSuites.find(s => s.id === Number(importParentSuiteId.value));
    return parent?.children?.map(c => ({ id: c.id, name: c.name })) || [];
});

const importTargetSuiteId = computed(() => importSubcategoryId.value || importParentSuiteId.value);

watch(() => importParentSuiteId.value, () => {
    importSubcategoryId.value = '';
});

const fieldAliases: Record<string, string[]> = {
    'Title': ['title', 'name', 'test case name', 'test name', 'case name'],
    'Description': ['description', 'summary', 'details'],
    'Preconditions': ['preconditions', 'pre-conditions', 'prerequisites', 'pre conditions'],
    'Steps': ['steps', 'test steps', 'steps to reproduce', 'step'],
    'Expected Result': ['expected result', 'expected', 'expected results', 'expected outcome'],
    'Priority': ['priority'],
    'Severity': ['severity'],
    'Type': ['type', 'test type', 'case type'],
    'Automation Status': ['automation status', 'automation', 'automated'],
    'Tags': ['tags', 'labels', 'keywords'],
};

const getMatchedField = (header: string): string | null => {
    const normalized = header.toLowerCase().trim();
    for (const [field, aliases] of Object.entries(fieldAliases)) {
        if (aliases.includes(normalized)) return field;
    }
    return null;
};

const importFieldMapping = computed(() => {
    return importHeaders.value.map(h => ({
        header: h,
        matchedField: getMatchedField(h),
    }));
});

const matchedFieldCount = computed(() => importFieldMapping.value.filter(m => m.matchedField).length);

const onImportFileChange = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    importFile.value = file;
    importHeaders.value = [];
    importRows.value = [];

    try {
        const XLSX = await import('xlsx');
        const data = await file.arrayBuffer();
        const workbook = XLSX.read(data, { type: 'array' });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const json: any[][] = XLSX.utils.sheet_to_json(sheet, { header: 1 });

        if (json.length < 2) {
            importHeaders.value = [];
            importRows.value = [];
            return;
        }

        importHeaders.value = (json[0] || []).map(String);
        importRows.value = json.slice(1).filter(row => row.some(cell => cell !== null && cell !== undefined && String(cell).trim() !== ''));
    } catch {
        importHeaders.value = [];
        importRows.value = [];
    }
};

const openImportDialog = () => {
    importFile.value = null;
    importHeaders.value = [];
    importRows.value = [];
    importParentSuiteId.value = '';
    importSubcategoryId.value = '';
    showImportDialog.value = true;
};

const submitImport = () => {
    if (!importTargetSuiteId.value || importRows.value.length === 0) return;
    isImportingCases.value = true;

    router.post(`/projects/${props.project.id}/test-suites/import-cases`, {
        test_suite_id: Number(importTargetSuiteId.value),
        headers: importHeaders.value,
        rows: importRows.value,
    }, {
        preserveState: false,
        onSuccess: () => {
            showImportDialog.value = false;
            isImportingCases.value = false;
        },
        onError: () => {
            isImportingCases.value = false;
        },
    });
};

const exportAllCsv = () => {
    window.location.href = `/projects/${props.project.id}/test-suites/export-cases`;
};

const exportSelectedCsv = () => {
    if (selectedTestCaseIds.value.length === 0) return;
    window.location.href = `/projects/${props.project.id}/test-suites/export-cases?ids=${selectedTestCaseIds.value.join(',')}`;
};

// Note dialog state
const showNoteDialog = ref(false);
const noteContent = ref('');
const noteTitle = ref('');
const selectedParentSuiteId = ref<string>('');
const selectedSubcategoryId = ref<string>('');
const isImportingNote = ref(false);
const hasDraft = ref(false);
const DRAFT_STORAGE_KEY = `test-suite-note-draft-${props.project.id}`;

// Target suite ID: subcategory takes priority, then parent suite
const targetSuiteId = computed(() => selectedSubcategoryId.value || selectedParentSuiteId.value);

// Parent suites (top-level only)
const parentSuiteOptions = computed(() => props.testSuites.map(s => ({ id: s.id, name: s.name })));

// Subcategories filtered by selected parent suite
const subcategoryOptions = computed(() => {
    if (!selectedParentSuiteId.value) return [];
    const parent = props.testSuites.find(s => s.id === Number(selectedParentSuiteId.value));
    return parent?.children?.map(c => ({ id: c.id, name: c.name })) || [];
});

interface NoteDraft {
    content: string;
    title: string;
    parentSuiteId: string;
    subcategoryId: string;
}

const loadDraft = () => {
    try {
        const saved = localStorage.getItem(DRAFT_STORAGE_KEY);
        if (saved) {
            const draft: NoteDraft = JSON.parse(saved);
            if (draft.content && draft.content.trim()) {
                hasDraft.value = true;
            }
        }
    } catch (e) {
        console.error('Failed to load draft:', e);
    }
};

const saveDraft = () => {
    if (!noteContent.value.trim()) {
        deleteDraft();
        return;
    }
    const draft: NoteDraft = {
        content: noteContent.value,
        title: noteTitle.value,
        parentSuiteId: selectedParentSuiteId.value,
        subcategoryId: selectedSubcategoryId.value,
    };
    try {
        localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draft));
        hasDraft.value = true;
    } catch (e) {
        console.error('Failed to save draft:', e);
    }
};

const deleteDraft = () => {
    try {
        localStorage.removeItem(DRAFT_STORAGE_KEY);
        hasDraft.value = false;
    } catch (e) {
        console.error('Failed to delete draft:', e);
    }
};

const clearNotes = () => {
    noteContent.value = '';
    noteTitle.value = '';
    selectedParentSuiteId.value = '';
    selectedSubcategoryId.value = '';
    deleteDraft();
};

const openDraft = () => {
    try {
        const saved = localStorage.getItem(DRAFT_STORAGE_KEY);
        if (saved) {
            const draft: NoteDraft = JSON.parse(saved);
            noteContent.value = draft.content;
            noteTitle.value = draft.title;
            selectedParentSuiteId.value = draft.parentSuiteId || '';
            selectedSubcategoryId.value = draft.subcategoryId || '';
        }
    } catch (e) {
        console.error('Failed to open draft:', e);
    }
};

const onNoteDialogChange = (open: boolean) => {
    if (open && hasDraft.value) {
        openDraft();
    }
    if (!open && noteContent.value.trim()) {
        saveDraft();
    }
    if (!open) {
        noteContent.value = '';
        noteTitle.value = '';
    }
};

// Reset subcategory when parent suite changes
watch(selectedParentSuiteId, () => {
    selectedSubcategoryId.value = '';
});

// Parse note lines into test steps
const parsedSteps = computed(() => {
    if (!noteContent.value.trim()) return [];
    return noteContent.value
        .split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0)
        .map(line => ({
            action: line.replace(/^[\d]+[.\)\-:\s]+/, '').trim(),
            expected: null,
        }))
        .filter(step => step.action.length > 0);
});

const importNoteAsTestCase = () => {
    if (!parsedSteps.value.length || !noteTitle.value.trim() || !targetSuiteId.value) return;
    isImportingNote.value = true;
    router.post(
        `/projects/${props.project.id}/test-suites/${targetSuiteId.value}/test-cases`,
        {
            title: noteTitle.value.trim(),
            steps: parsedSteps.value,
            priority: 'medium',
            severity: 'major',
            type: 'functional',
            automation_status: 'not_automated',
            tags: [],
        },
        {
            preserveState: false,
            onSuccess: () => {
                showNoteDialog.value = false;
                noteContent.value = '';
                noteTitle.value = '';
                isImportingNote.value = false;
                deleteDraft();
            },
            onError: () => {
                isImportingNote.value = false;
            },
        },
    );
};

onMounted(() => {
    loadDraft();
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
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/test-suites/create`" class="mt-4 inline-block">
                            <Button variant="cta" class="gap-2">
                                <Plus class="h-4 w-4" />
                                Create Test Suite
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <!-- Main Content -->
            <div v-else class="flex flex-col flex-1 min-h-0">
                <!-- Action Header -->
                <div class="flex items-center mb-3 gap-6">
                    <!-- Spacer for left column -->
                    <div class="w-[430px] shrink-0"></div>
                    <!-- Right side - Selection controls and New Test Suite button -->
                    <div class="flex items-center justify-between flex-1 max-w-4xl pr-2">
                        <div v-if="localTotalTestCases > 0 && filteredFlatSuites.length > 0" class="flex items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-xs font-medium border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground h-7 px-2.5 cursor-pointer"
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
                                {{ selectedTestCaseIds.length }} of {{ filteredTestCaseCount }} selected
                            </span>
                            <!-- Actions dropdown when test cases are selected -->
                            <RestrictedAction v-if="selectedTestCaseIds.length > 0">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button class="gap-2">
                                            <MoreHorizontal class="h-4 w-4" />
                                            Actions ({{ selectedTestCaseIds.length }})
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        <DropdownMenuLabel>Selected Test Cases</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem class="cursor-pointer" @click="openTestRunDialog">
                                            <Play class="h-4 w-4 mr-2" />
                                            Create Test Run
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="cursor-pointer" @click="openCopyDialog">
                                            <Copy class="h-4 w-4 mr-2" />
                                            Copy to Test Suite
                                        </DropdownMenuItem>
                                        <DropdownMenuItem class="cursor-pointer" @click="openSubcategoryDialog">
                                            <FolderPlus class="h-4 w-4 mr-2" />
                                            Create Subcategory
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem class="text-destructive focus:text-destructive cursor-pointer" @click="openDeleteDialog">
                                            <Trash2 class="h-4 w-4 mr-2" />
                                            Delete Test Cases
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </RestrictedAction>
                        </div>
                        <div v-else></div>
                        <div class="flex items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline" size="sm" class="gap-1.5 text-xs">
                                        <FileSpreadsheet class="h-3.5 w-3.5" />
                                        File
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem class="cursor-pointer" @click="openImportDialog">
                                        <Download class="h-4 w-4 mr-2" />
                                        Import
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="cursor-pointer" @click="exportAllCsv">
                                        <Upload class="h-4 w-4 mr-2" />
                                        Export All
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="selectedTestCaseIds.length > 0"
                                        class="cursor-pointer"
                                        @click="exportSelectedCsv"
                                    >
                                        <Upload class="h-4 w-4 mr-2" />
                                        Export Selected ({{ selectedTestCaseIds.length }})
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Button
                                variant="outline"
                                class="gap-2 relative"
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
                            <RestrictedAction>
                                <Dialog v-model:open="showNoteDialog" @update:open="onNoteDialogChange">
                                    <DialogTrigger as-child>
                                        <Button
                                            :variant="hasDraft ? 'cta' : 'outline'"
                                            class="gap-2"
                                        >
                                            <Pencil v-if="hasDraft" class="h-4 w-4" />
                                            <StickyNote v-else class="h-4 w-4" />
                                            {{ hasDraft ? 'Draft' : 'Create a Note' }}
                                        </Button>
                                    </DialogTrigger>
                                <DialogContent class="max-w-2xl max-h-[75vh] flex flex-col" style="overflow: hidden !important; max-width: min(42rem, calc(100vw - 2rem)) !important;">
                                    <DialogHeader>
                                        <DialogTitle class="flex items-center gap-2">
                                            <StickyNote class="h-5 w-5 text-primary" />
                                            {{ hasDraft ? 'Edit Draft' : 'Create a Note' }}
                                        </DialogTitle>
                                        <DialogDescription>
                                            Write your notes below. Each line will become a test step in the new test case.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div class="space-y-4 py-4 px-0.5 overflow-y-auto min-h-0 flex-1">
                                        <div class="space-y-2">
                                            <Label>Test Case Title</Label>
                                            <Input
                                                v-model="noteTitle"
                                                type="text"
                                                placeholder="e.g. Verify user login flow"
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <Label>Steps (one per line)</Label>
                                            <Textarea
                                                v-model="noteContent"
                                                placeholder="1. Navigate to the login page&#10;2. Enter valid credentials&#10;3. Click the login button&#10;4. Verify dashboard is displayed"
                                                rows="10"
                                                class="font-mono text-sm resize-y"
                                                style="word-wrap: break-word; overflow-wrap: break-word; white-space: pre-wrap; overflow-y: auto; max-height: 400px;"
                                            />
                                            <p v-if="parsedSteps.length > 0" class="text-sm text-muted-foreground">
                                                {{ parsedSteps.length }} step(s) will be created
                                            </p>
                                        </div>

                                        <div v-if="parsedSteps.length > 0" class="space-y-4 rounded-lg border p-4 bg-muted/30">
                                            <div class="grid gap-3">
                                                <div class="space-y-2 min-w-0">
                                                    <Label>Test Suite</Label>
                                                    <Select v-model="selectedParentSuiteId">
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select suite..." />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="suite in parentSuiteOptions"
                                                                :key="suite.id"
                                                                :value="String(suite.id)"
                                                            >
                                                                {{ suite.name }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="space-y-2 min-w-0">
                                                    <Label>Subcategory <span class="text-muted-foreground font-normal">(optional)</span></Label>
                                                    <Select v-model="selectedSubcategoryId" :disabled="!subcategoryOptions.length">
                                                        <SelectTrigger>
                                                            <SelectValue :placeholder="subcategoryOptions.length ? 'Select subcategory...' : 'No subcategories'" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                v-for="sub in subcategoryOptions"
                                                                :key="sub.id"
                                                                :value="String(sub.id)"
                                                            >
                                                                {{ sub.name }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            <div class="space-y-2 overflow-hidden">
                                                <Label>Preview</Label>
                                                <div class="max-h-40 overflow-auto rounded border bg-background p-2 text-sm" style="word-wrap: break-word; overflow-wrap: break-word;">
                                                    <ol class="list-decimal list-inside space-y-1">
                                                        <li v-for="(step, index) in parsedSteps.slice(0, 10)" :key="index" class="break-words whitespace-pre-wrap" style="overflow-wrap: break-word; word-break: break-all;">
                                                            {{ step.action }}
                                                        </li>
                                                        <li v-if="parsedSteps.length > 10" class="text-muted-foreground">
                                                            ... and {{ parsedSteps.length - 10 }} more
                                                        </li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <DialogFooter class="flex justify-between sm:justify-between">
                                        <Button
                                            v-if="noteContent.trim() || noteTitle.trim() || selectedParentSuiteId"
                                            variant="ghost"
                                            @click="clearNotes"
                                            class="gap-2 text-muted-foreground hover:text-destructive"
                                        >
                                            <X class="h-4 w-4" />
                                            Clear
                                        </Button>
                                        <div v-else></div>
                                        <div class="flex gap-2">
                                            <Button variant="outline" @click="showNoteDialog = false">
                                                Cancel
                                            </Button>
                                            <Button
                                                @click="importNoteAsTestCase"
                                                :disabled="!targetSuiteId || parsedSteps.length === 0 || !noteTitle.trim() || isImportingNote"
                                                class="gap-2"
                                            >
                                                <Plus class="h-4 w-4" />
                                                Create Test Case
                                            </Button>
                                        </div>
                                    </DialogFooter>
                                </DialogContent>
                                </Dialog>
                            </RestrictedAction>
                            <RestrictedAction>
                                <Link :href="`/projects/${project.id}/test-suites/create`">
                                    <Button variant="cta" class="gap-2">
                                        <Plus class="h-4 w-4" />
                                        Test Suite
                                    </Button>
                                </Link>
                            </RestrictedAction>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="flex gap-6 flex-1 min-h-0">
                <!-- Left: Test Suites Navigation -->
                <div class="w-[430px] shrink-0 self-start sticky top-6">
                    <div class="rounded-xl border bg-card shadow-sm">
                        <div class="p-3 border-b bg-muted/30">
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <FolderTree class="h-4 w-4 text-primary" />
                                <span>Navigation</span>
                            </div>
                            <div class="relative mt-2">
                                <Search class="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search test cases..."
                                    class="pl-7 pr-7 h-8 text-xs bg-background/60"
                                />
                                <button
                                    v-if="searchQuery"
                                    @click="searchQuery = ''"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
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
                <div class="flex-1 overflow-y-auto min-h-0 pr-2 max-w-4xl relative">
                    <!-- Filter Dropdown -->
                    <div v-if="showFilters" class="absolute top-0 left-0 right-2 z-20 rounded-xl border bg-card shadow-lg p-4 animate-in fade-in slide-in-from-top-2 duration-200">
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
                                    class="h-6 gap-1 text-xs text-muted-foreground hover:text-destructive"
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
                        <div class="grid grid-cols-3 gap-x-3 gap-y-2.5">
                            <!-- Type -->
                            <div class="relative">
                                <Label class="text-[11px] text-muted-foreground mb-1 block">Type</Label>
                                <Select v-model="filterType">
                                    <SelectTrigger class="h-8 text-xs" :class="filterType ? 'pr-7' : ''">
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="functional">Functional</SelectItem>
                                        <SelectItem value="smoke">Smoke</SelectItem>
                                        <SelectItem value="regression">Regression</SelectItem>
                                        <SelectItem value="integration">Integration</SelectItem>
                                        <SelectItem value="acceptance">Acceptance</SelectItem>
                                        <SelectItem value="performance">Performance</SelectItem>
                                        <SelectItem value="security">Security</SelectItem>
                                        <SelectItem value="usability">Usability</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                                <button v-if="filterType" @click="filterType = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <!-- Priority -->
                            <div class="relative">
                                <Label class="text-[11px] text-muted-foreground mb-1 block">Priority</Label>
                                <Select v-model="filterPriority">
                                    <SelectTrigger class="h-8 text-xs" :class="filterPriority ? 'pr-7' : ''">
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
                            <!-- Automation -->
                            <div class="relative">
                                <Label class="text-[11px] text-muted-foreground mb-1 block">Automation</Label>
                                <Select v-model="filterAutomation">
                                    <SelectTrigger class="h-8 text-xs" :class="filterAutomation ? 'pr-7' : ''">
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="not_automated">Not Automated</SelectItem>
                                        <SelectItem value="to_be_automated">To Be Automated</SelectItem>
                                        <SelectItem value="automated">Automated</SelectItem>
                                    </SelectContent>
                                </Select>
                                <button v-if="filterAutomation" @click="filterAutomation = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <!-- Severity -->
                            <div class="relative">
                                <Label class="text-[11px] text-muted-foreground mb-1 block">Severity</Label>
                                <Select v-model="filterSeverity">
                                    <SelectTrigger class="h-8 text-xs" :class="filterSeverity ? 'pr-7' : ''">
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="trivial">Trivial</SelectItem>
                                        <SelectItem value="minor">Minor</SelectItem>
                                        <SelectItem value="major">Major</SelectItem>
                                        <SelectItem value="critical">Critical</SelectItem>
                                        <SelectItem value="blocker">Blocker</SelectItem>
                                    </SelectContent>
                                </Select>
                                <button v-if="filterSeverity" @click="filterSeverity = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <!-- Author -->
                            <div class="relative">
                                <Label class="text-[11px] text-muted-foreground mb-1 block">Author</Label>
                                <Select v-model="filterAuthor">
                                    <SelectTrigger class="h-8 text-xs" :class="filterAuthor ? 'pr-7' : ''">
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
                            <!-- Feature -->
                            <div class="relative">
                                <Label class="text-[11px] text-muted-foreground mb-1 block">Feature</Label>
                                <Select v-model="filterFeature">
                                    <SelectTrigger class="h-8 text-xs" :class="filterFeature ? 'pr-7' : ''">
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__">No feature</SelectItem>
                                        <SelectItem
                                            v-for="feature in availableFeatures"
                                            :key="feature.id"
                                            :value="String(feature.id)"
                                        >
                                            {{ feature.module?.length ? `${feature.module.join(', ')} / ` : '' }}{{ feature.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <button v-if="filterFeature" @click="filterFeature = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <!-- Module -->
                            <div>
                                <Label class="text-[11px] text-muted-foreground mb-1 block">Module</Label>
                                <div class="relative">
                                    <Select v-model="filterModule">
                                        <SelectTrigger class="h-8 text-xs" :class="filterModule ? 'pr-7' : ''">
                                            <SelectValue placeholder="All" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none__">No module</SelectItem>
                                            <SelectItem value="UI">UI</SelectItem>
                                            <SelectItem value="API">API</SelectItem>
                                            <SelectItem value="Backend">Backend</SelectItem>
                                            <SelectItem value="Database">Database</SelectItem>
                                            <SelectItem value="Integration">Integration</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <button v-if="filterModule" @click="filterModule = ''" class="absolute right-1.5 top-1/2 -translate-y-1/2 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>
                            <!-- Dates: 2x2 grid spanning 2 columns -->
                            <div class="col-span-2 grid grid-cols-2 gap-x-3 gap-y-2.5">
                                <!-- Created From -->
                                <div class="relative">
                                    <Label class="text-[11px] text-muted-foreground mb-1 block">Created From</Label>
                                    <Input v-model="filterCreatedFrom" type="date" class="h-8 text-xs" :class="filterCreatedFrom ? 'pr-7' : ''" />
                                    <button v-if="filterCreatedFrom" @click="filterCreatedFrom = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                                <!-- Created To -->
                                <div class="relative">
                                    <Label class="text-[11px] text-muted-foreground mb-1 block">Created To</Label>
                                    <Input v-model="filterCreatedTo" type="date" class="h-8 text-xs" :class="filterCreatedTo ? 'pr-7' : ''" />
                                    <button v-if="filterCreatedTo" @click="filterCreatedTo = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                                <!-- Updated From -->
                                <div class="relative">
                                    <Label class="text-[11px] text-muted-foreground mb-1 block">Updated From</Label>
                                    <Input v-model="filterUpdatedFrom" type="date" class="h-8 text-xs" :class="filterUpdatedFrom ? 'pr-7' : ''" />
                                    <button v-if="filterUpdatedFrom" @click="filterUpdatedFrom = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                                <!-- Updated To -->
                                <div class="relative">
                                    <Label class="text-[11px] text-muted-foreground mb-1 block">Updated To</Label>
                                    <Input v-model="filterUpdatedTo" type="date" class="h-8 text-xs" :class="filterUpdatedTo ? 'pr-7' : ''" />
                                    <button v-if="filterUpdatedTo" @click="filterUpdatedTo = ''" class="absolute right-1.5 bottom-1.5 p-0.5 rounded-full hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer z-10">
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>
                            <!-- Results count -->
                            <div class="flex items-end justify-center pb-1">
                                <span class="text-sm text-muted-foreground">
                                    Found <span class="font-semibold text-foreground">{{ filteredTestCaseCount }}</span> {{ filteredTestCaseCount === 1 ? 'case' : 'cases' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <!-- Backdrop to close filter -->
                    <div v-if="showFilters" class="fixed inset-0 z-10" @click="showFilters = false" />
                    <div v-if="filteredFlatSuites.length === 0 && (searchQuery.trim() || activeFilterCount > 0)" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                        <Search class="h-12 w-12 mb-3" />
                        <p class="font-semibold">No results found</p>
                        <p v-if="searchQuery.trim()" class="text-sm max-w-full truncate px-4">No test cases match "{{ searchQuery }}"</p>
                        <p v-else class="text-sm">No test cases match the selected filters</p>
                        <Button v-if="activeFilterCount > 0" variant="outline" size="sm" class="mt-3 gap-2" @click="clearFilters">
                            <X class="h-3.5 w-3.5" />
                            Clear Filters
                        </Button>
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
                                :class="suite.parentName ? 'py-2 px-4' : 'py-3.5 px-4'"
                                @click="router.visit(`/projects/${project.id}/test-suites/${suite.id}`)"
                            >
                                <div class="flex items-center gap-3 min-w-0 flex-1 mr-3">
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
                                        class="shrink-0 rounded-lg flex items-center justify-center transition-colors"
                                        :class="[
                                            suite.parentName ? 'h-8 w-8 bg-yellow-500/10 group-hover/header:bg-primary/10' : 'h-8 w-8 bg-primary/10'
                                        ]"
                                    >
                                        <Boxes v-if="suite.parentName" class="h-3.5 w-3.5 text-yellow-500 group-hover/header:text-primary transition-colors" />
                                        <Layers v-else class="h-4 w-4 text-primary" />
                                    </div>
                                    <div class="min-w-0">
                                        <h3 :class="suite.parentName ? 'font-semibold text-sm' : 'font-semibold text-base'" class="group-hover/header:text-primary transition-colors truncate" v-html="highlight(suite.name)" />
                                        <p v-if="suite.parentName" class="text-[11px] text-muted-foreground truncate">
                                            in <span v-html="highlight(suite.parentName ?? '')" />
                                        </p>
                                    </div>
                                    <Badge variant="secondary" :class="suite.parentName ? 'text-[11px] ml-1' : 'text-xs ml-2'" class="shrink-0 font-normal bg-gray-500/10 text-gray-600 border-gray-200 dark:text-gray-400 dark:border-gray-800">
                                        {{ getFlatSuiteTotalTestCases(suite) }} {{ getFlatSuiteTotalTestCases(suite) === 1 ? 'case' : 'cases' }}
                                    </Badge>
                                    <Badge :variant="testTypeVariant(suite.type)" :class="[suite.parentName ? 'text-[11px]' : 'text-xs']" class="shrink-0 font-normal">
                                        {{ suite.type }}
                                    </Badge>
                                    <FeatureBadges v-if="suite.projectFeatures?.length" :features="suite.projectFeatures" :max-visible="2" />
                                </div>
                                <RestrictedAction>
                                    <Link :href="`/projects/${project.id}/test-suites/${suite.id}/test-cases/create`" @click.stop class="shrink-0">
                                        <Button variant="outline" size="sm" :class="suite.parentName ? 'h-6 text-[11px] gap-1 px-2' : 'text-xs'">
                                            <Plus class="h-3.5 w-3.5" />
                                            Add
                                        </Button>
                                    </Link>
                                </RestrictedAction>
                            </div>

                            <!-- Test Cases -->
                            <div v-if="suite.testCases.length" class="space-y-[3px]">
                                <Link
                                    v-for="(testCase, tcIndex) in suite.testCases"
                                    :key="testCase.id"
                                    :href="`/projects/${project.id}/test-suites/${suite.id}/test-cases/${testCase.id}`"
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
                                            @click.stop.prevent="toggleTestCaseSelection(testCase.id)"
                                        >
                                            <Check v-if="isTestCaseSelected(testCase.id)" class="h-3 w-3" />
                                        </div>
                                        <div
                                            draggable="true"
                                            @dragstart="onTestCaseDragStart(suite.id, tcIndex, testCase, $event)"
                                            @dragend="onTestCaseDragEnd"
                                            @click.stop.prevent
                                            class="cursor-grab active:cursor-grabbing"
                                        >
                                            <GripVertical class="h-4 w-4 text-muted-foreground/50" />
                                        </div>
                                        <div class="h-7 w-7 rounded-lg bg-muted/50 flex items-center justify-center shrink-0 group-hover:bg-primary/10 transition-colors">
                                            <component :is="getTypeIcon(testCase.type)" class="h-3.5 w-3.5 text-muted-foreground group-hover:text-primary transition-colors" />
                                        </div>
                                        <p class="text-sm font-normal truncate group-hover:text-primary transition-colors" v-html="highlight(testCase.title)" />
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0 ml-4">
                                        <FeatureBadges v-if="testCase.project_features?.length" :features="testCase.project_features" :max-visible="2" />
                                        <Badge :variant="priorityVariant(testCase.priority)" class="text-[10px] px-1.5 h-4 font-medium">
                                            {{ testCase.priority }}
                                        </Badge>
                                        <Badge variant="secondary" class="text-[10px] px-1.5 h-4 font-normal">
                                            {{ testCase.type }}
                                        </Badge>
                                    </div>
                                </Link>
                            </div>

                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
        <!-- Create Test Run Dialog -->
        <Dialog v-model:open="showTestRunDialog">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Play class="h-5 w-5 text-primary" />
                        Create Test Run
                    </DialogTitle>
                    <DialogDescription>
                        Create a test run from {{ selectedTestCaseIds.length }} selected test case{{ selectedTestCaseIds.length !== 1 ? 's' : '' }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4 space-y-4">
                    <div class="space-y-2">
                        <Label for="tr-name">Name</Label>
                        <Input id="tr-name" v-model="testRunName" placeholder="Test run name..." />
                    </div>
                    <div class="space-y-2">
                        <Label for="tr-description">Description</Label>
                        <Input id="tr-description" v-model="testRunDescription" placeholder="Optional description..." />
                    </div>
                    <div class="space-y-2">
                        <Label>Priority</Label>
                        <Select v-model="testRunPriority">
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
                    </div>
                    <div class="space-y-2">
                        <Label>Environment</Label>
                        <div class="grid grid-cols-3 gap-2">
                            <Select v-model="testRunEnvPreset">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Develop">Develop</SelectItem>
                                    <SelectItem value="Staging">Staging</SelectItem>
                                    <SelectItem value="Production">Production</SelectItem>
                                </SelectContent>
                            </Select>
                            <Input v-model="testRunEnvNotes" placeholder="Devices, browser..." class="col-span-2" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="tr-milestone">Milestone</Label>
                        <Input id="tr-milestone" v-model="testRunMilestone" placeholder="e.g. v1.0, Sprint 5..." />
                    </div>
                </div>
                <DialogFooter class="flex gap-2 sm:justify-end">
                    <Button variant="outline" @click="showTestRunDialog = false">
                        Cancel
                    </Button>
                    <Button @click="createTestRun" :disabled="!testRunName.trim() || isCreatingTestRun" class="gap-2">
                        <Play class="h-4 w-4" />
                        {{ isCreatingTestRun ? 'Creating...' : 'Create Test Run' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Copy to Test Suite Dialog -->
        <Dialog v-model:open="showCopyDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Copy class="h-5 w-5 text-primary" />
                        Copy to Test Suite
                    </DialogTitle>
                    <DialogDescription>
                        Copy {{ selectedTestCaseIds.length }} test case{{ selectedTestCaseIds.length !== 1 ? 's' : '' }} to another suite.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4 space-y-4">
                    <div class="space-y-2">
                        <Label>Destination Project</Label>
                        <Select v-model="copyTargetProjectId" :disabled="loadingProjects">
                            <SelectTrigger>
                                <SelectValue placeholder="Select project..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="p in availableProjects"
                                    :key="p.id"
                                    :value="String(p.id)"
                                >
                                    {{ p.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label>Target Suite</Label>
                        <div v-if="loadingSuites" class="flex items-center gap-2 text-sm text-muted-foreground py-2">
                            <Loader2 class="h-4 w-4 animate-spin" />
                            Loading suites...
                        </div>
                        <Select v-else v-model="copyTargetSuiteId" :disabled="!copyTargetProjectId">
                            <SelectTrigger>
                                <SelectValue placeholder="Select suite..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="suite in allSuiteOptions"
                                    :key="suite.id"
                                    :value="String(suite.id)"
                                >
                                    {{ suite.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-3 pt-2 border-t">
                        <Label class="text-sm font-medium">Copy Options</Label>
                        <div class="flex items-center gap-2">
                            <Checkbox id="copy-attachments" :checked="copyAttachments" @update:checked="copyAttachments = $event" />
                            <label for="copy-attachments" class="text-sm cursor-pointer">Copy attachments</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox id="copy-features" :checked="copyFeatures" @update:checked="copyFeatures = $event" />
                            <label for="copy-features" class="text-sm cursor-pointer">
                                Copy feature links
                                <span v-if="isCrossProject" class="text-muted-foreground">(matched by name)</span>
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox id="copy-notes" :checked="copyNotes" @update:checked="copyNotes = $event" />
                            <label for="copy-notes" class="text-sm cursor-pointer">Copy notes</label>
                        </div>
                    </div>
                </div>
                <DialogFooter class="flex gap-2 sm:justify-end">
                    <Button variant="outline" @click="showCopyDialog = false">
                        Cancel
                    </Button>
                    <Button @click="copyToSuite" :disabled="!copyTargetSuiteId || isCopying" class="gap-2">
                        <Copy class="h-4 w-4" />
                        {{ isCopying ? 'Copying...' : 'Copy' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Test Cases Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Delete Test Cases?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete {{ selectedTestCaseIds.length }} test case{{ selectedTestCaseIds.length !== 1 ? 's' : '' }}? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-4 sm:justify-end">
                    <Button variant="secondary" @click="showDeleteDialog = false" class="flex-1 sm:flex-none">
                        No
                    </Button>
                    <Button variant="destructive" @click="deleteTestCases" :disabled="isDeleting" class="flex-1 sm:flex-none">
                        {{ isDeleting ? 'Deleting...' : 'Yes' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Create Subcategory Dialog -->
        <Dialog v-model:open="showSubcategoryDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <FolderPlus class="h-5 w-5 text-primary" />
                        Group into Subcategory
                    </DialogTitle>
                    <DialogDescription>
                        Create a new subcategory and move {{ selectedTestCaseIds.length }} selected test case{{ selectedTestCaseIds.length === 1 ? '' : 's' }} into it.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div class="space-y-2">
                        <Label>Parent Suite</Label>
                        <Select v-model="subcategoryParentId">
                            <SelectTrigger>
                                <SelectValue placeholder="Select parent suite..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="suite in parentSuiteOptions"
                                    :key="suite.id"
                                    :value="String(suite.id)"
                                >
                                    {{ suite.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label for="sub-name">Name</Label>
                        <Input id="sub-name" v-model="subcategoryName" placeholder="Subcategory name..." />
                    </div>
                    <div class="space-y-2">
                        <Label for="sub-description">Description</Label>
                        <Textarea id="sub-description" v-model="subcategoryDescription" placeholder="Optional description..." rows="2" />
                    </div>
                    <div class="space-y-2">
                        <Label>Type</Label>
                        <Select v-model="subcategoryType">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="functional">Functional</SelectItem>
                                <SelectItem value="smoke">Smoke</SelectItem>
                                <SelectItem value="regression">Regression</SelectItem>
                                <SelectItem value="integration">Integration</SelectItem>
                                <SelectItem value="acceptance">Acceptance</SelectItem>
                                <SelectItem value="performance">Performance</SelectItem>
                                <SelectItem value="security">Security</SelectItem>
                                <SelectItem value="usability">Usability</SelectItem>
                                <SelectItem value="other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <FeatureSelector
                        v-if="availableFeatures.length"
                        :features="availableFeatures"
                        v-model="subcategoryFeatureIds"
                        :project-id="project.id"
                    />
                </div>
                <DialogFooter class="flex gap-2 sm:justify-end">
                    <Button variant="outline" @click="showSubcategoryDialog = false">
                        Cancel
                    </Button>
                    <Button @click="createSubcategory" :disabled="!subcategoryParentId || !subcategoryName.trim() || isCreatingSubcategory" class="gap-2">
                        <FolderPlus class="h-4 w-4" />
                        {{ isCreatingSubcategory ? 'Creating...' : 'Create & Move' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Import Dialog -->
        <Dialog v-model:open="showImportDialog">
            <DialogContent class="max-w-2xl max-h-[80vh] flex flex-col" style="overflow: hidden !important; max-width: min(42rem, calc(100vw - 2rem)) !important;">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Download class="h-5 w-5 text-primary" />
                        Import Test Cases
                    </DialogTitle>
                    <DialogDescription>
                        Upload a CSV or Excel file to import test cases. Columns will be automatically mapped to CheckMate fields.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4 overflow-y-auto min-h-0 flex-1">
                    <div class="space-y-2">
                        <Label>File</Label>
                        <div class="flex items-center gap-3">
                            <input
                                ref="importFileInput"
                                type="file"
                                accept=".csv,.xlsx,.xls"
                                class="hidden"
                                @change="onImportFileChange"
                            />
                            <Button variant="outline" size="sm" class="gap-2 cursor-pointer" @click="($refs.importFileInput as HTMLInputElement).click()">
                                <Upload class="h-4 w-4" />
                                Choose File
                            </Button>
                            <span class="text-sm text-muted-foreground truncate">{{ importFile?.name || 'No file selected' }}</span>
                        </div>
                    </div>

                    <div v-if="importHeaders.length > 0" class="space-y-4">
                        <!-- Field mapping preview -->
                        <div class="rounded-lg border p-4 bg-muted/30 space-y-3">
                            <div class="flex items-center justify-between">
                                <Label>Column Mapping</Label>
                                <span class="text-xs text-muted-foreground">
                                    {{ matchedFieldCount }} of {{ importHeaders.length }} columns matched
                                </span>
                            </div>
                            <div class="grid gap-1.5">
                                <div
                                    v-for="mapping in importFieldMapping"
                                    :key="mapping.header"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                        :class="mapping.matchedField
                                            ? 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20'
                                            : 'bg-muted text-muted-foreground ring-border'"
                                    >
                                        {{ mapping.header }}
                                    </span>
                                    <span v-if="mapping.matchedField" class="text-muted-foreground">&rarr;</span>
                                    <span v-if="mapping.matchedField" class="text-sm font-medium">{{ mapping.matchedField }}</span>
                                    <span v-else class="text-xs text-muted-foreground italic">ignored</span>
                                </div>
                            </div>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            Found <strong>{{ importRows.length }}</strong> test case(s) to import
                        </p>

                        <!-- Suite selector -->
                        <div class="rounded-lg border p-4 bg-muted/30 space-y-3">
                            <div class="space-y-2">
                                <Label>Test Suite</Label>
                                <Select v-model="importParentSuiteId">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select suite..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="suite in parentSuiteOptions"
                                            :key="suite.id"
                                            :value="String(suite.id)"
                                        >
                                            {{ suite.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label>Subcategory <span class="text-muted-foreground font-normal">(optional)</span></Label>
                                <Select v-model="importSubcategoryId" :disabled="!importSubcategoryOptions.length">
                                    <SelectTrigger>
                                        <SelectValue :placeholder="importSubcategoryOptions.length ? 'Select subcategory...' : 'No subcategories'" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="sub in importSubcategoryOptions"
                                            :key="sub.id"
                                            :value="String(sub.id)"
                                        >
                                            {{ sub.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter class="flex gap-2 sm:justify-end">
                    <Button variant="outline" @click="showImportDialog = false">
                        Cancel
                    </Button>
                    <Button
                        @click="submitImport"
                        :disabled="!importTargetSuiteId || importRows.length === 0 || isImportingCases || matchedFieldCount === 0"
                        class="gap-2"
                    >
                        <Download class="h-4 w-4" />
                        {{ isImportingCases ? 'Importing...' : `Import ${importRows.length} test case(s)` }}
                    </Button>
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
