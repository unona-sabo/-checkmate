<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite, type TestCase } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Plus, Edit, Layers, FileText,
    Zap, RotateCcw, GripVertical, Boxes, FolderPlus, Search, X, Link2, Check,
    MoreHorizontal, Trash2, Play, Copy, Minus, Filter, FileSpreadsheet,
    Upload, Download, Loader2
} from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
} from '@/components/ui/dialog';
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
import { ref, computed, watch } from 'vue';
import { useSearch } from '@/composables/useSearch';

const props = defineProps<{
    project: Project;
    testSuite: TestSuite;
    users: { id: number; name: string }[];
    availableFeatures: { id: number; name: string; module: string[] | null }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
    { title: props.testSuite.name, href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}` },
];

const copied = ref(false);

const titleStart = computed(() => {
    const words = props.testSuite.name.split(' ');
    return words.length > 1 ? words.slice(0, -1).join(' ') + ' ' : '';
});
const titleEnd = computed(() => {
    const words = props.testSuite.name.split(' ');
    return words[words.length - 1];
});

const copyLink = () => {
    const route = `/projects/${props.project.id}/test-suites/${props.testSuite.id}`;
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

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'smoke': return Zap;
        case 'regression': return RotateCcw;
        default: return FileText;
    }
};

// Build hierarchical view - current suite + children
interface SuiteSection {
    id: number;
    name: string;
    type: string;
    isChild: boolean;
    testCases: TestCase[];
}

const suiteSections = computed<SuiteSection[]>(() => {
    const sections: SuiteSection[] = [];

    if (props.testSuite.test_cases?.length) {
        sections.push({
            id: props.testSuite.id,
            name: props.testSuite.name,
            type: props.testSuite.type,
            isChild: false,
            testCases: props.testSuite.test_cases,
        });
    }

    props.testSuite.children?.forEach(child => {
        sections.push({
            id: child.id,
            name: child.name,
            type: child.type,
            isChild: true,
            testCases: child.test_cases || [],
        });
    });

    return sections;
});

const totalTestCases = computed(() => {
    return suiteSections.value.reduce((acc, s) => acc + s.testCases.length, 0);
});

const isSaving = ref(false);

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

const activeFilterCount = computed(() => {
    return [filterType, filterPriority, filterSeverity, filterAutomation, filterAuthor, filterFeature]
        .filter(f => f.value !== '').length;
});

const clearFilters = () => {
    filterType.value = '';
    filterPriority.value = '';
    filterSeverity.value = '';
    filterAutomation.value = '';
    filterAuthor.value = '';
    filterFeature.value = '';
};

const filteredSections = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const hasSearch = query.length > 0;
    const hasFilters = activeFilterCount.value > 0;

    if (!hasSearch && !hasFilters) return suiteSections.value;

    return suiteSections.value
        .map(section => ({
            ...section,
            testCases: section.testCases.filter(tc => {
                if (hasSearch && !tc.title.toLowerCase().includes(query)) return false;
                if (filterType.value && tc.type !== filterType.value) return false;
                if (filterPriority.value && tc.priority !== filterPriority.value) return false;
                if (filterSeverity.value && tc.severity !== filterSeverity.value) return false;
                if (filterAutomation.value && tc.automation_status !== filterAutomation.value) return false;
                if (filterAuthor.value && String(tc.created_by) !== filterAuthor.value) return false;
                if (filterFeature.value === '__none__') {
                    if (tc.project_features && tc.project_features.length > 0) return false;
                } else if (filterFeature.value) {
                    if (!tc.project_features?.some(f => String(f.id) === filterFeature.value)) return false;
                }
                return true;
            }),
        }))
        .filter(section => section.testCases.length > 0 || (hasSearch && section.name.toLowerCase().includes(query)));
});

const filteredTestCaseCount = computed(() => {
    return filteredSections.value.reduce((acc, s) => acc + s.testCases.length, 0);
});

// Selection
const selectedTestCaseIds = ref<number[]>([]);

const isTestCaseSelected = (id: number): boolean => {
    return selectedTestCaseIds.value.includes(id);
};

const allTestCaseIds = computed<number[]>(() => {
    const ids: number[] = [];
    suiteSections.value.forEach(s => s.testCases.forEach(tc => ids.push(tc.id)));
    return ids;
});

const filteredTestCaseIds = computed<number[]>(() => {
    const ids: number[] = [];
    filteredSections.value.forEach(s => s.testCases.forEach(tc => ids.push(tc.id)));
    return ids;
});

const isAllSelected = computed(() => {
    if (filteredTestCaseIds.value.length === 0) return false;
    return filteredTestCaseIds.value.every(id => selectedTestCaseIds.value.includes(id));
});

const isSomeSelected = computed(() => {
    return selectedTestCaseIds.value.length > 0 && !isAllSelected.value;
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        const filteredIds = new Set(filteredTestCaseIds.value);
        selectedTestCaseIds.value = selectedTestCaseIds.value.filter(id => !filteredIds.has(id));
    } else {
        const newIds = filteredTestCaseIds.value.filter(id => !selectedTestCaseIds.value.includes(id));
        selectedTestCaseIds.value = [...selectedTestCaseIds.value, ...newIds];
    }
};

const toggleTestCaseSelection = (testCaseId: number) => {
    const index = selectedTestCaseIds.value.indexOf(testCaseId);
    if (index > -1) {
        selectedTestCaseIds.value = selectedTestCaseIds.value.filter(id => id !== testCaseId);
    } else {
        selectedTestCaseIds.value = [...selectedTestCaseIds.value, testCaseId];
    }
};

// Drag and drop state per section
const dragState = ref<{
    sectionId: number | null;
    draggedIndex: number | null;
    dragOverIndex: number | null;
}>({
    sectionId: null,
    draggedIndex: null,
    dragOverIndex: null,
});

const onDragStart = (sectionId: number, index: number, event: DragEvent) => {
    dragState.value = { sectionId, draggedIndex: index, dragOverIndex: null };
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', `${sectionId}-${index}`);
    }
};

const onDragOver = (sectionId: number, index: number, event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    if (dragState.value.sectionId === sectionId) {
        dragState.value.dragOverIndex = index;
    }
};

const onDragLeave = () => {
    dragState.value.dragOverIndex = null;
};

const onDrop = (sectionId: number, index: number, event: DragEvent) => {
    event.preventDefault();
    if (dragState.value.sectionId === sectionId &&
        dragState.value.draggedIndex !== null &&
        dragState.value.draggedIndex !== index) {

        const section = suiteSections.value.find(s => s.id === sectionId);
        if (section) {
            const draggedItem = section.testCases[dragState.value.draggedIndex];
            section.testCases.splice(dragState.value.draggedIndex, 1);
            section.testCases.splice(index, 0, draggedItem);
            saveOrder(sectionId, section.testCases);
        }
    }
    dragState.value = { sectionId: null, draggedIndex: null, dragOverIndex: null };
};

const onDragEnd = () => {
    dragState.value = { sectionId: null, draggedIndex: null, dragOverIndex: null };
};

const saveOrder = (suiteId: number, testCases: TestCase[]) => {
    isSaving.value = true;
    const cases = testCases.map((tc, index) => ({
        id: tc.id,
        order: index + 1,
    }));

    router.post(`/projects/${props.project.id}/test-suites/${suiteId}/test-cases/reorder`, { cases }, {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false;
        },
    });
};

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
    testRunName.value = `${props.testSuite.name} Test Run`;
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
    const suites = isCrossProject.value ? availableSuites.value : (props.testSuite.parent_id ? [] : [props.testSuite]);
    // For current project, build from the testSuite data we have
    if (!isCrossProject.value) {
        // Use parent suite options if available
        const parentSuites = props.testSuite.parent ? [props.testSuite.parent] : [props.testSuite];
        parentSuites.forEach(suite => {
            options.push({ id: suite.id, name: suite.name, label: suite.name });
            if (suite.id === props.testSuite.id) {
                props.testSuite.children?.forEach(child => {
                    options.push({ id: child.id, name: child.name, label: `${suite.name} / ${child.name}` });
                });
            }
        });
        return options;
    }
    suites.forEach(suite => {
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
    showSubcategoryDialog.value = true;
};

const createSubcategory = () => {
    if (!subcategoryName.value.trim()) return;
    isCreatingSubcategory.value = true;

    router.post(`/projects/${props.project.id}/test-suites`, {
        name: subcategoryName.value.trim(),
        description: subcategoryDescription.value || null,
        type: subcategoryType.value,
        parent_id: props.testSuite.id,
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
const importTargetSuiteId = ref<string>('');
const isImportingCases = ref(false);

const importSuiteOptions = computed(() => {
    const options: { id: number; name: string }[] = [{ id: props.testSuite.id, name: props.testSuite.name }];
    props.testSuite.children?.forEach(c => options.push({ id: c.id, name: c.name }));
    return options;
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
    importTargetSuiteId.value = String(props.testSuite.id);
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
    // Export only test cases from this suite and its children
    const ids: number[] = [];
    suiteSections.value.forEach(s => s.testCases.forEach(tc => ids.push(tc.id)));
    if (ids.length === 0) return;
    window.location.href = `/projects/${props.project.id}/test-suites/export-cases?ids=${ids.join(',')}`;
};

const exportSelectedCsv = () => {
    if (selectedTestCaseIds.value.length === 0) return;
    window.location.href = `/projects/${props.project.id}/test-suites/export-cases?ids=${selectedTestCaseIds.value.join(',')}`;
};
</script>

<template>
    <Head :title="testSuite.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-bold tracking-tight">
                            <Boxes v-if="testSuite.parent_id" class="inline-block h-6 w-6 align-text-top text-yellow-500 mr-2" />
                            <Layers v-else class="inline-block h-6 w-6 align-text-top text-primary mr-2" />{{ titleStart }}<span class="whitespace-nowrap">{{ titleEnd }}<button
                                @click="copyLink"
                                class="inline-flex align-middle ml-1.5 p-1 rounded-md text-muted-foreground hover:text-primary hover:bg-muted transition-colors cursor-pointer"
                                :title="copied ? 'Copied!' : 'Copy link'"
                            ><Check v-if="copied" class="h-4 w-4 text-green-500" /><Link2 v-else class="h-4 w-4" /></button></span>
                        </h1>
                        <FeatureBadges v-if="testSuite.project_features?.length" :features="testSuite.project_features" :max-visible="3" />
                    </div>
                    <div v-if="testSuite.parent" class="flex items-center gap-2 text-sm text-muted-foreground mt-1">
                        <span>in</span>
                        <Link
                            :href="`/projects/${project.id}/test-suites/${testSuite.parent.id}`"
                            class="inline-flex items-center gap-1.5 text-primary hover:underline cursor-pointer"
                        >
                            <Layers class="h-3.5 w-3.5" />
                            {{ testSuite.parent.name }}
                        </Link>
                    </div>
                    <p v-if="testSuite.description" class="text-muted-foreground mt-1">
                        {{ testSuite.description }}
                    </p>
                    <p class="text-muted-foreground text-sm mt-1">
                        {{ suiteSections.length }} {{ suiteSections.length === 1 ? 'section' : 'sections' }} · {{ totalTestCases }} test cases
                        <span v-if="isSaving" class="ml-2 text-primary">Saving...</span>
                    </p>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="flex items-center justify-between gap-3">
                <div v-if="totalTestCases > 0 && filteredSections.length > 0" class="flex items-center gap-3">
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
                                <DropdownMenuItem v-if="!testSuite.parent_id" class="cursor-pointer" @click="openSubcategoryDialog">
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
                <div v-else />
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
                        size="sm"
                        class="gap-1.5 text-xs relative"
                        @click="showFilters = !showFilters"
                    >
                        <Filter class="h-3.5 w-3.5" />
                        Filter
                        <Badge
                            v-if="activeFilterCount > 0"
                            class="absolute -top-2 -right-2 h-5 w-5 p-0 flex items-center justify-center text-[10px] rounded-full"
                        >
                            {{ activeFilterCount }}
                        </Badge>
                    </Button>
                    <RestrictedAction v-if="testSuite.parent_id">
                        <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/create`">
                            <Button variant="cta" class="gap-2">
                                <Plus class="h-4 w-4" />
                                Add Test Case
                            </Button>
                        </Link>
                    </RestrictedAction>
                    <RestrictedAction v-if="!testSuite.parent_id">
                        <Link :href="`/projects/${project.id}/test-suites/create?parent_id=${testSuite.id}`">
                            <Button variant="outline" class="gap-2">
                                <FolderPlus class="h-4 w-4" />
                                Add Subcategory
                            </Button>
                        </Link>
                    </RestrictedAction>
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/edit`">
                            <Button variant="outline" class="gap-2">
                                <Edit class="h-4 w-4" />
                                Edit Suite
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <!-- Filter panel -->
            <div v-if="showFilters" class="rounded-lg border p-4 bg-muted/30 space-y-3">
                <div class="flex items-center justify-between">
                    <Label class="text-sm font-medium">Filters</Label>
                    <Button v-if="activeFilterCount > 0" variant="ghost" size="sm" class="text-xs h-7" @click="clearFilters">
                        Clear all
                    </Button>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    <Select v-model="filterType">
                        <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Type" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Types</SelectItem>
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
                    <Select v-model="filterPriority">
                        <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Priority" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Priorities</SelectItem>
                            <SelectItem value="low">Low</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="high">High</SelectItem>
                            <SelectItem value="critical">Critical</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filterSeverity">
                        <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Severity" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Severities</SelectItem>
                            <SelectItem value="trivial">Trivial</SelectItem>
                            <SelectItem value="minor">Minor</SelectItem>
                            <SelectItem value="major">Major</SelectItem>
                            <SelectItem value="critical">Critical</SelectItem>
                            <SelectItem value="blocker">Blocker</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filterAutomation">
                        <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Automation" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All</SelectItem>
                            <SelectItem value="not_automated">Not Automated</SelectItem>
                            <SelectItem value="to_be_automated">To Be Automated</SelectItem>
                            <SelectItem value="automated">Automated</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filterAuthor">
                        <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Author" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Authors</SelectItem>
                            <SelectItem v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filterFeature">
                        <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Feature" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All Features</SelectItem>
                            <SelectItem value="__none__">No Feature</SelectItem>
                            <SelectItem v-for="f in availableFeatures" :key="f.id" :value="String(f.id)">{{ f.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="suiteSections.length === 0 && !testSuite.children?.length" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <FileText class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="text-lg font-semibold">No test cases yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground max-w-sm">
                        Add test cases to this suite to get started.
                    </p>
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/create`" class="mt-4 inline-block">
                            <Button variant="cta" class="gap-2">
                                <Plus class="h-4 w-4" />
                                Add Test Case
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <!-- Content -->
            <div v-else class="space-y-2">
                <!-- No search results -->
                <div v-if="filteredSections.length === 0 && (searchQuery.trim() || activeFilterCount > 0)" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                    <Search class="h-12 w-12 mb-3" />
                    <p class="font-semibold">No results found</p>
                    <p v-if="searchQuery.trim()" class="text-sm max-w-full truncate px-4">No test cases match "{{ searchQuery }}"</p>
                    <p v-else class="text-sm">No test cases match the current filters</p>
                </div>

                <!-- Current Suite Test Cases (if any) -->
                <div
                    v-for="section in filteredSections"
                    :key="section.id"
                    class="mt-2.5 first:mt-0"
                >
                    <!-- Section Header (hide when on a subcategory page for the current suite) -->
                    <div
                        v-if="!(section.id === testSuite.id && testSuite.parent_id)"
                        class="group/header flex items-center justify-between mb-2 sticky top-0 bg-card/95 backdrop-blur-sm py-2.5 px-4 z-10 rounded-xl border shadow-sm cursor-pointer transition-all duration-150 hover:border-primary/50"
                        @click="router.visit(`/projects/${project.id}/test-suites/${section.id}`)"
                    >
                        <div class="flex items-center gap-3 min-w-0 flex-1 mr-3">
                            <div
                                class="h-8 w-8 shrink-0 rounded-lg flex items-center justify-center transition-colors"
                                :class="section.isChild ? 'bg-yellow-500/10 group-hover/header:bg-primary/10' : 'bg-primary/10'"
                            >
                                <Boxes v-if="section.isChild" class="h-4 w-4 text-yellow-500 group-hover/header:text-primary transition-colors" />
                                <Layers v-else class="h-4 w-4 text-primary" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-base group-hover/header:text-primary transition-colors truncate" v-html="highlight(section.name)" />
                                <p v-if="section.isChild" class="text-xs text-muted-foreground">
                                    Subcategory
                                </p>
                            </div>
                            <Badge variant="secondary" class="shrink-0 text-xs font-normal bg-gray-500/10 text-gray-600 border-gray-200 dark:text-gray-400 dark:border-gray-800">
                                {{ section.testCases.length }} {{ section.testCases.length === 1 ? 'case' : 'cases' }}
                            </Badge>
                            <Badge :variant="testTypeVariant(section.type)" class="shrink-0 text-xs font-normal">
                                {{ section.type }}
                            </Badge>
                        </div>
                        <RestrictedAction>
                            <Link :href="`/projects/${project.id}/test-suites/${section.id}/test-cases/create`" @click.stop class="shrink-0">
                                <Button variant="outline" size="sm" class="text-xs">
                                    <Plus class="h-3.5 w-3.5" />
                                    Add
                                </Button>
                            </Link>
                        </RestrictedAction>
                    </div>

                    <!-- Test Cases -->
                    <div v-if="section.testCases.length" class="space-y-1.5">
                        <div
                            v-for="(testCase, tcIndex) in section.testCases"
                            :key="testCase.id"
                            class="group flex items-center justify-between px-4 py-2.5 rounded-xl border bg-card hover:border-primary/50 hover:shadow-sm transition-all duration-150"
                            :class="{
                                'border-t-2 border-t-primary': dragState.sectionId === section.id && dragState.dragOverIndex === tcIndex,
                                'opacity-50': dragState.sectionId === section.id && dragState.draggedIndex === tcIndex
                            }"
                            @dragover="onDragOver(section.id, tcIndex, $event)"
                            @dragleave="onDragLeave"
                            @drop="onDrop(section.id, tcIndex, $event)"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <div
                                    class="flex items-center justify-center shrink-0 cursor-pointer"
                                    @click.stop.prevent="toggleTestCaseSelection(testCase.id)"
                                >
                                    <Checkbox
                                        :model-value="isTestCaseSelected(testCase.id)"
                                        @update:model-value="toggleTestCaseSelection(testCase.id)"
                                        class="cursor-pointer"
                                    />
                                </div>
                                <div
                                    draggable="true"
                                    @dragstart="onDragStart(section.id, tcIndex, $event)"
                                    @dragend="onDragEnd"
                                    @click.stop.prevent
                                    class="cursor-grab active:cursor-grabbing"
                                >
                                    <GripVertical class="h-4 w-4 text-muted-foreground/50" />
                                </div>
                                <Link
                                    :href="`/projects/${project.id}/test-suites/${section.id}/test-cases/${testCase.id}`"
                                    class="flex items-center gap-3 min-w-0 flex-1"
                                >
                                    <div class="h-7 w-7 rounded-lg bg-muted/50 flex items-center justify-center shrink-0 group-hover:bg-primary/10 transition-colors">
                                        <component :is="getTypeIcon(testCase.type)" class="h-3.5 w-3.5 text-muted-foreground group-hover:text-primary transition-colors" />
                                    </div>
                                    <p class="text-sm font-normal truncate group-hover:text-primary transition-colors" v-html="highlight(testCase.title)" />
                                </Link>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 ml-4">
                                <Badge :variant="priorityVariant(testCase.priority)" class="text-[10px] px-1.5 h-4 font-medium">
                                    {{ testCase.priority }}
                                </Badge>
                                <Badge variant="secondary" class="text-[10px] px-1.5 h-4 font-normal">
                                    {{ testCase.type }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state for section -->
                    <div v-else class="rounded-lg border border-dashed p-6 text-center">
                        <FileText class="mx-auto h-8 w-8 text-muted-foreground" />
                        <p class="mt-2 text-sm text-muted-foreground">No test cases in this {{ section.isChild ? 'subcategory' : 'suite' }}</p>
                        <RestrictedAction>
                            <Link :href="`/projects/${project.id}/test-suites/${section.id}/test-cases/create`" class="mt-3 inline-block">
                                <Button size="sm" variant="outline" class="gap-2">
                                    <Plus class="h-4 w-4" />
                                    Add Test Case
                                </Button>
                            </Link>
                        </RestrictedAction>
                    </div>
                </div>

                <!-- Children without test cases (show as cards to add) -->
                <template v-if="testSuite.children?.length">
                    <div
                        v-for="child in testSuite.children.filter(c => !suiteSections.find(s => s.id === c.id))"
                        :key="child.id"
                        class="mt-2.5"
                    >
                        <div
                            class="group/header flex items-center justify-between mb-2 sticky top-0 bg-card/95 backdrop-blur-sm py-2.5 px-4 z-10 rounded-xl border shadow-sm cursor-pointer transition-all duration-150 hover:border-primary/50"
                            @click="router.visit(`/projects/${project.id}/test-suites/${child.id}`)"
                        >
                            <div class="flex items-center gap-3 min-w-0 flex-1 mr-3">
                                <div class="h-8 w-8 shrink-0 rounded-lg bg-yellow-500/10 group-hover/header:bg-primary/10 flex items-center justify-center transition-colors">
                                    <Boxes class="h-4 w-4 text-yellow-500 group-hover/header:text-primary transition-colors" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-base group-hover/header:text-primary transition-colors truncate">{{ child.name }}</h3>
                                    <p class="text-xs text-muted-foreground">Subcategory</p>
                                </div>
                                <Badge variant="secondary" class="shrink-0 text-xs font-normal bg-gray-500/10 text-gray-600 border-gray-200 dark:text-gray-400 dark:border-gray-800">0 cases</Badge>
                                <Badge :variant="testTypeVariant(child.type)" class="shrink-0 text-xs font-normal">{{ child.type }}</Badge>
                            </div>
                            <RestrictedAction>
                                <Link :href="`/projects/${project.id}/test-suites/${child.id}/test-cases/create`" @click.stop class="shrink-0">
                                    <Button variant="outline" size="sm" class="text-xs">
                                        <Plus class="h-3.5 w-3.5" />
                                        Add
                                    </Button>
                                </Link>
                            </RestrictedAction>
                        </div>
                        <div class="rounded-lg border border-dashed p-6 text-center">
                            <FileText class="mx-auto h-8 w-8 text-muted-foreground" />
                            <p class="mt-2 text-sm text-muted-foreground">No test cases in this subcategory</p>
                            <RestrictedAction>
                                <Link :href="`/projects/${project.id}/test-suites/${child.id}/test-cases/create`" class="mt-3 inline-block">
                                    <Button size="sm" variant="outline" class="gap-2">
                                        <Plus class="h-4 w-4" />
                                        Add Test Case
                                    </Button>
                                </Link>
                            </RestrictedAction>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Create Test Run Dialog -->
        <Dialog v-model:open="showTestRunDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Play class="h-5 w-5 text-primary" />
                        Create Test Run
                    </DialogTitle>
                    <DialogDescription>
                        Create a test run from {{ selectedTestCaseIds.length }} selected test case(s).
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Name</Label>
                        <Input v-model="testRunName" placeholder="Test run name..." />
                    </div>
                    <div class="space-y-2">
                        <Label>Description <span class="text-muted-foreground font-normal">(optional)</span></Label>
                        <Textarea v-model="testRunDescription" placeholder="Description..." rows="2" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <Label>Priority</Label>
                            <Select v-model="testRunPriority">
                                <SelectTrigger><SelectValue placeholder="Select..." /></SelectTrigger>
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
                            <Select v-model="testRunEnvPreset">
                                <SelectTrigger><SelectValue placeholder="Select..." /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Development">Development</SelectItem>
                                    <SelectItem value="Staging">Staging</SelectItem>
                                    <SelectItem value="Production">Production</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showTestRunDialog = false">Cancel</Button>
                    <Button @click="createTestRun" :disabled="!testRunName.trim() || isCreatingTestRun" class="gap-2">
                        <Play class="h-4 w-4" />
                        {{ isCreatingTestRun ? 'Creating...' : 'Create Test Run' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Copy to Test Suite Dialog -->
        <Dialog v-model:open="showCopyDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Copy class="h-5 w-5 text-primary" />
                        Copy to Test Suite
                    </DialogTitle>
                    <DialogDescription>
                        Copy {{ selectedTestCaseIds.length }} test case(s) to another suite.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Project</Label>
                        <Select v-model="copyTargetProjectId" :disabled="loadingProjects">
                            <SelectTrigger><SelectValue placeholder="Select project..." /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in availableProjects" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label>Target Suite</Label>
                        <Select v-model="copyTargetSuiteId" :disabled="loadingSuites || allSuiteOptions.length === 0">
                            <SelectTrigger><SelectValue :placeholder="loadingSuites ? 'Loading...' : 'Select suite...'" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in allSuiteOptions" :key="s.id" :value="String(s.id)">{{ s.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label class="text-sm">Copy options</Label>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <Checkbox v-model:checked="copyAttachments" /> Attachments
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <Checkbox v-model:checked="copyFeatures" /> Features
                            </label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <Checkbox v-model:checked="copyNotes" /> Notes
                            </label>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showCopyDialog = false">Cancel</Button>
                    <Button @click="copyToSuite" :disabled="!copyTargetSuiteId || isCopying" class="gap-2">
                        <Copy class="h-4 w-4" />
                        {{ isCopying ? 'Copying...' : 'Copy' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Test Cases Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <Trash2 class="h-5 w-5" />
                        Delete Test Cases
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete {{ selectedTestCaseIds.length }} test case(s)? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                    <Button variant="destructive" @click="deleteTestCases" :disabled="isDeleting" class="gap-2">
                        <Trash2 class="h-4 w-4" />
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
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
                        Create Subcategory
                    </DialogTitle>
                    <DialogDescription>
                        Create a new subcategory and move {{ selectedTestCaseIds.length }} selected test case(s) into it.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Name</Label>
                        <Input v-model="subcategoryName" placeholder="Subcategory name..." />
                    </div>
                    <div class="space-y-2">
                        <Label>Description</Label>
                        <Textarea v-model="subcategoryDescription" placeholder="Optional description..." rows="2" />
                    </div>
                    <div class="space-y-2">
                        <Label>Type</Label>
                        <Select v-model="subcategoryType">
                            <SelectTrigger><SelectValue /></SelectTrigger>
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
                <DialogFooter>
                    <Button variant="outline" @click="showSubcategoryDialog = false">Cancel</Button>
                    <Button @click="createSubcategory" :disabled="!subcategoryName.trim() || isCreatingSubcategory" class="gap-2">
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
                        <Upload class="h-5 w-5 text-primary" />
                        Import Test Cases
                    </DialogTitle>
                    <DialogDescription>
                        Upload a CSV or Excel file to import test cases. Columns will be automatically mapped to CheckMate fields.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4 overflow-y-auto min-h-0 flex-1">
                    <div class="space-y-2">
                        <Label>File</Label>
                        <input
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            class="block w-full text-sm text-muted-foreground file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary file:text-primary-foreground hover:file:bg-primary/90 file:cursor-pointer cursor-pointer"
                            @change="onImportFileChange"
                        />
                    </div>

                    <div v-if="importHeaders.length > 0" class="space-y-4">
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

                        <div class="rounded-lg border p-4 bg-muted/30 space-y-3">
                            <div class="space-y-2">
                                <Label>Target Suite</Label>
                                <Select v-model="importTargetSuiteId">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select suite..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="suite in importSuiteOptions"
                                            :key="suite.id"
                                            :value="String(suite.id)"
                                        >
                                            {{ suite.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter class="flex gap-2 sm:justify-end">
                    <Button variant="outline" @click="showImportDialog = false">Cancel</Button>
                    <Button
                        @click="submitImport"
                        :disabled="!importTargetSuiteId || importRows.length === 0 || isImportingCases || matchedFieldCount === 0"
                        class="gap-2"
                    >
                        <Upload class="h-4 w-4" />
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
