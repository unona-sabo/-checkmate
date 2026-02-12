<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite, type TestCase } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Plus, Edit, Layers, FileText, ArrowRight,
    Zap, Bug, GripVertical, Boxes, ChevronRight, FolderPlus, Search, X
} from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { ref, computed } from 'vue';

const props = defineProps<{
    project: Project;
    testSuite: TestSuite;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
    { title: props.testSuite.name, href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}` },
];

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'critical': return 'bg-red-500/10 text-red-500 border-red-500/20';
        case 'high': return 'bg-orange-500/10 text-orange-500 border-orange-500/20';
        case 'medium': return 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20';
        case 'low': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        default: return '';
    }
};

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'smoke': return Zap;
        case 'regression': return Bug;
        default: return FileText;
    }
};

// Build hierarchical view - current suite + children
interface SuiteSection {
    id: number;
    name: string;
    isChild: boolean;
    testCases: TestCase[];
}

const suiteSections = computed<SuiteSection[]>(() => {
    const sections: SuiteSection[] = [];

    // Add current suite's test cases
    if (props.testSuite.test_cases?.length) {
        sections.push({
            id: props.testSuite.id,
            name: props.testSuite.name,
            isChild: !!props.testSuite.parent_id, // Check if current suite is a subcategory
            testCases: props.testSuite.test_cases,
        });
    }

    // Add children's test cases
    props.testSuite.children?.forEach(child => {
        sections.push({
            id: child.id,
            name: child.name,
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
const searchQuery = ref('');

const filteredSections = computed(() => {
    if (!searchQuery.value.trim()) return suiteSections.value;
    const query = searchQuery.value.toLowerCase();
    return suiteSections.value
        .map(section => ({
            ...section,
            testCases: section.testCases.filter(tc => tc.title.toLowerCase().includes(query)),
        }))
        .filter(section => section.testCases.length > 0 || section.name.toLowerCase().includes(query));
});

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
</script>

<template>
    <Head :title="testSuite.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Boxes v-if="testSuite.parent_id" class="h-6 w-6 text-yellow-500" />
                        <Layers v-else class="h-6 w-6 text-primary" />
                        {{ testSuite.name }}
                    </h1>
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
                    <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/create`">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Add Test Case
                        </Button>
                    </Link>
                    <Link v-if="!testSuite.parent_id" :href="`/projects/${project.id}/test-suites/create?parent_id=${testSuite.id}`">
                        <Button variant="outline" class="gap-2">
                            <FolderPlus class="h-4 w-4" />
                            Add Subcategory
                        </Button>
                    </Link>
                    <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/edit`">
                        <Button variant="outline" class="gap-2">
                            <Edit class="h-4 w-4" />
                            Edit Suite
                        </Button>
                    </Link>
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
                    <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/create`" class="mt-4 inline-block">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Add Test Case
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Content -->
            <div v-else class="space-y-2">
                <!-- No search results -->
                <div v-if="filteredSections.length === 0 && searchQuery.trim()" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                    <Search class="h-12 w-12 mb-3" />
                    <p class="font-semibold">No results found</p>
                    <p class="text-sm">No test cases match "{{ searchQuery }}"</p>
                </div>

                <!-- Current Suite Test Cases (if any) -->
                <div
                    v-for="section in filteredSections"
                    :key="section.id"
                    class="mt-2.5 first:mt-0"
                >
                    <!-- Section Header -->
                    <div
                        class="group/header flex items-center justify-between mb-2 sticky top-0 bg-card/95 backdrop-blur-sm py-2.5 px-4 z-10 rounded-xl border shadow-sm cursor-pointer transition-all duration-150 hover:border-primary/50"
                        @click="router.visit(`/projects/${project.id}/test-suites/${section.id}`)"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="h-8 w-8 rounded-lg flex items-center justify-center transition-colors"
                                :class="section.isChild ? 'bg-yellow-500/10 group-hover/header:bg-primary/10' : 'bg-primary/10'"
                            >
                                <Boxes v-if="section.isChild" class="h-4 w-4 text-yellow-500 group-hover/header:text-primary transition-colors" />
                                <Layers v-else class="h-4 w-4 text-primary" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-base group-hover/header:text-primary transition-colors">{{ section.name }}</h3>
                                <p v-if="section.isChild" class="text-xs text-muted-foreground">
                                    Subcategory
                                </p>
                            </div>
                            <Badge variant="secondary" class="text-xs font-normal">
                                {{ section.testCases.length }} {{ section.testCases.length === 1 ? 'case' : 'cases' }}
                            </Badge>
                        </div>
                        <Link :href="`/projects/${project.id}/test-suites/${section.id}/test-cases/create`" @click.stop>
                            <Button variant="outline" size="sm" class="h-8 text-xs gap-1.5">
                                <Plus class="h-3.5 w-3.5" />
                                Add
                            </Button>
                        </Link>
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
                                    draggable="true"
                                    @dragstart="onDragStart(section.id, tcIndex, $event)"
                                    @dragend="onDragEnd"
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
                                    <p class="text-sm font-medium truncate group-hover:text-primary transition-colors">
                                        {{ testCase.title }}
                                    </p>
                                </Link>
                            </div>
                            <Link
                                :href="`/projects/${project.id}/test-suites/${section.id}/test-cases/${testCase.id}`"
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

                    <!-- Empty state for section -->
                    <div v-else class="rounded-lg border border-dashed p-6 text-center">
                        <FileText class="mx-auto h-8 w-8 text-muted-foreground" />
                        <p class="mt-2 text-sm text-muted-foreground">No test cases in this {{ section.isChild ? 'subcategory' : 'suite' }}</p>
                        <Link :href="`/projects/${project.id}/test-suites/${section.id}/test-cases/create`" class="mt-3 inline-block">
                            <Button size="sm" variant="outline" class="gap-2">
                                <Plus class="h-4 w-4" />
                                Add Test Case
                            </Button>
                        </Link>
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
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-yellow-500/10 group-hover/header:bg-primary/10 flex items-center justify-center transition-colors">
                                    <Boxes class="h-4 w-4 text-yellow-500 group-hover/header:text-primary transition-colors" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-base group-hover/header:text-primary transition-colors">{{ child.name }}</h3>
                                    <p class="text-xs text-muted-foreground">Subcategory</p>
                                </div>
                                <Badge variant="secondary" class="text-xs font-normal">0 cases</Badge>
                            </div>
                            <Link :href="`/projects/${project.id}/test-suites/${child.id}/test-cases/create`" @click.stop>
                                <Button variant="outline" size="sm" class="h-8 text-xs gap-1.5">
                                    <Plus class="h-3.5 w-3.5" />
                                    Add
                                </Button>
                            </Link>
                        </div>
                        <div class="rounded-lg border border-dashed p-6 text-center">
                            <FileText class="mx-auto h-8 w-8 text-muted-foreground" />
                            <p class="mt-2 text-sm text-muted-foreground">No test cases in this subcategory</p>
                            <Link :href="`/projects/${project.id}/test-suites/${child.id}/test-cases/create`" class="mt-3 inline-block">
                                <Button size="sm" variant="outline" class="gap-2">
                                    <Plus class="h-4 w-4" />
                                    Add Test Case
                                </Button>
                            </Link>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
