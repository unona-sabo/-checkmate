<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, FolderKanban, CheckSquare, Layers, PlayCircle, Calendar, Sparkles, GripVertical } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    projects: Project[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
];

// Colors for project cards
const cardColors = [
    { bg: 'bg-blue-500/10', icon: 'text-blue-500', accent: 'bg-blue-500', glow: 'shadow-blue-500/20' },
    { bg: 'bg-emerald-500/10', icon: 'text-emerald-500', accent: 'bg-emerald-500', glow: 'shadow-emerald-500/20' },
    { bg: 'bg-violet-500/10', icon: 'text-violet-500', accent: 'bg-violet-500', glow: 'shadow-violet-500/20' },
    { bg: 'bg-amber-500/10', icon: 'text-amber-500', accent: 'bg-amber-500', glow: 'shadow-amber-500/20' },
    { bg: 'bg-rose-500/10', icon: 'text-rose-500', accent: 'bg-rose-500', glow: 'shadow-rose-500/20' },
    { bg: 'bg-cyan-500/10', icon: 'text-cyan-500', accent: 'bg-cyan-500', glow: 'shadow-cyan-500/20' },
];

const getCardColor = (index: number) => cardColors[index % cardColors.length];

// Drag and drop
const localProjects = ref<Project[]>([...props.projects]);
const draggedIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);
const isSaving = ref(false);

const onDragStart = (index: number, event: DragEvent) => {
    draggedIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', `project-${index}`);
    }
};

const onDragOver = (index: number, event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    dragOverIndex.value = index;
};

const onDragLeave = () => {
    dragOverIndex.value = null;
};

const onDrop = (index: number, event: DragEvent) => {
    event.preventDefault();
    if (draggedIndex.value !== null && draggedIndex.value !== index) {
        const draggedProject = localProjects.value[draggedIndex.value];
        localProjects.value.splice(draggedIndex.value, 1);
        localProjects.value.splice(index, 0, draggedProject);
        saveOrder();
    }
    draggedIndex.value = null;
    dragOverIndex.value = null;
};

const onDragEnd = () => {
    draggedIndex.value = null;
    dragOverIndex.value = null;
};

const saveOrder = () => {
    isSaving.value = true;
    const projects = localProjects.value.map((project, index) => ({
        id: project.id,
        order: index + 1,
    }));

    router.post('/projects/reorder', { projects }, {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false;
        },
    });
};
</script>

<template>
    <Head title="Projects" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <FolderKanban class="h-6 w-6 text-primary" />
                        Projects
                    </h1>
                    <p class="text-muted-foreground">
                        Manage your QA projects and test suites
                        <span v-if="isSaving" class="ml-2 text-primary">Saving...</span>
                    </p>
                </div>
                <Link href="/projects/create">
                    <Button variant="cta" class="gap-2">
                        <Plus class="h-4 w-4" />
                        New Project
                    </Button>
                </Link>
            </div>

            <div v-if="projects.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <Sparkles class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No projects yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Get started by creating your first project.</p>
                    <Link href="/projects/create" class="mt-4 inline-block">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Create Project
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-else class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(project, index) in localProjects"
                    :key="project.id"
                    class="group relative"
                    :class="{
                        'ring-2 ring-primary ring-offset-2 rounded-xl': dragOverIndex === index,
                        'opacity-50': draggedIndex === index
                    }"
                    @dragover="onDragOver(index, $event)"
                    @dragleave="onDragLeave"
                    @drop="onDrop(index, $event)"
                >
                    <Link :href="`/projects/${project.id}`">
                        <Card
                            class="transition-all duration-300 cursor-pointer h-full overflow-hidden group-hover:scale-[1.02] shadow-sm hover:shadow-lg !border-l-0 relative"
                        >
                            <!-- Colored left bar -->
                            <div
                                class="absolute left-0 top-0 bottom-0 w-1 rounded-l-xl"
                                :class="getCardColor(index).accent"
                            ></div>
                            <CardHeader class="p-4 pb-2">
                                <div class="flex items-center justify-between gap-3 relative">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-11 w-11 rounded-xl flex items-center justify-center shrink-0 shadow-lg"
                                            :class="[getCardColor(index).bg, getCardColor(index).glow]"
                                        >
                                            <FolderKanban class="h-6 w-6" :class="getCardColor(index).icon" />
                                        </div>
                                        <div>
                                            <CardTitle class="text-xl font-semibold line-clamp-1">
                                                {{ project.name }}
                                            </CardTitle>
                                            <CardDescription class="text-sm flex items-center gap-1.5 mt-0.5">
                                                <Calendar class="h-3.5 w-3.5" />
                                                {{ new Date(project.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }}
                                            </CardDescription>
                                        </div>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="p-4 pt-0 relative">
                                <div class="flex gap-3 text-sm">
                                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-muted/50">
                                        <CheckSquare class="h-4 w-4 text-emerald-500" />
                                        <span class="font-medium">{{ project.checklists_count || 0 }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-muted/50">
                                        <Layers class="h-4 w-4 text-blue-500" />
                                        <span class="font-medium">{{ project.test_suites_count || 0 }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-muted/50">
                                        <PlayCircle class="h-4 w-4 text-violet-500" />
                                        <span class="font-medium">{{ project.test_runs_count || 0 }}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                    <!-- Drag handle -->
                    <div
                        draggable="true"
                        @dragstart="onDragStart(index, $event)"
                        @dragend="onDragEnd"
                        class="absolute top-2 right-2 p-1.5 rounded-lg bg-muted/80 hover:bg-muted cursor-grab active:cursor-grabbing opacity-0 group-hover:opacity-100 transition-opacity z-10"
                        @click.prevent.stop
                    >
                        <GripVertical class="h-4 w-4 text-muted-foreground" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
