<script setup lang="ts">
import { ref, computed } from 'vue';
import { useSearch } from '@/composables/useSearch';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { home } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { type HomeSection, type SectionFeature } from '@/types/checkmate';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    ClipboardList,
    Layers,
    Play,
    Bug,
    FileText,
    StickyNote,
    CheckCircle2,
    User,
    Calendar,
    Clock,
    Database,
    Pencil,
    Plus,
    Trash2,
    Search,
    X,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Palette,
    Drama,
    Rocket,
    BarChart3,
} from 'lucide-vue-next';

const props = defineProps<{
    section: HomeSection;
    features: SectionFeature[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: props.section.title, href: `/home/${props.section.key}` },
];

const sectionIcons: Record<string, typeof ClipboardList> = {
    checklists: ClipboardList,
    'test-suites': Layers,
    'test-runs': Play,
    bugreports: Bug,
    design: Palette,
    automation: Drama,
    releases: Rocket,
    'test-coverage': BarChart3,
    'test-data': Database,
    documentations: FileText,
    notes: StickyNote,
};

// Search
const { searchQuery, highlight } = useSearch();

// Sort
const sortDirection = ref<'asc' | 'desc'>('asc');

const toggleSort = () => {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
};

const filteredFeatures = computed(() => {
    let result = [...props.features];
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (f) => f.title.toLowerCase().includes(query) || (f.description && f.description.toLowerCase().includes(query)),
        );
    }
    result.sort((a, b) => {
        const diff = new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
        return sortDirection.value === 'asc' ? diff : -diff;
    });
    return result;
});

// Editing
const editingId = ref<number | null>(null);
const editTitle = ref('');
const editDescription = ref('');
const saving = ref(false);

function startEditing(feature: SectionFeature): void {
    editingId.value = feature.id;
    editTitle.value = feature.title;
    editDescription.value = feature.description ?? '';
}

function cancelEditing(): void {
    editingId.value = null;
    editTitle.value = '';
    editDescription.value = '';
}

function saveFeature(feature: SectionFeature): void {
    saving.value = true;
    router.put(`/home/${props.section.key}/features/${feature.id}`, {
        title: editTitle.value,
        description: editDescription.value || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
            editingId.value = null;
        },
    });
}

// Creating new feature
const showNewForm = ref(false);
const newTitle = ref('');
const newDescription = ref('');
const creating = ref(false);

function createFeature(): void {
    creating.value = true;
    router.post(`/home/${props.section.key}/features`, {
        title: newTitle.value,
        description: newDescription.value || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            creating.value = false;
            showNewForm.value = false;
            newTitle.value = '';
            newDescription.value = '';
        },
    });
}

// Delete
const showDeleteConfirm = ref(false);
const featureToDelete = ref<SectionFeature | null>(null);

function confirmDelete(feature: SectionFeature): void {
    featureToDelete.value = feature;
    showDeleteConfirm.value = true;
}

function deleteFeature(): void {
    if (!featureToDelete.value) return;
    router.delete(`/home/${props.section.key}/features/${featureToDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            showDeleteConfirm.value = false;
            featureToDelete.value = null;
        },
    });
}

function formatDate(dateString: string | null): string {
    if (!dateString) return 'No data yet';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatDateTime(dateString: string | null): string {
    if (!dateString) return 'No data yet';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="section.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Section header -->
            <div class="mb-6 rounded-xl border border-border bg-card p-6">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                        <component :is="sectionIcons[section.key]" class="h-6 w-6 text-primary" />
                    </div>
                    <h1 class="text-2xl font-bold">{{ section.title }}</h1>
                </div>

                <p class="mb-4 text-sm leading-relaxed text-muted-foreground">{{ section.description }}</p>

                <!-- Metadata bar -->
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-border pt-4 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1.5">
                        <User class="h-3.5 w-3.5" />
                        {{ section.author }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <Calendar class="h-3.5 w-3.5" />
                        Created: {{ formatDate(section.latest_created_at) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <Clock class="h-3.5 w-3.5" />
                        Updated: {{ formatDateTime(section.latest_updated_at) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <Database class="h-3.5 w-3.5" />
                        {{ section.count }} features
                    </span>
                </div>
            </div>

            <!-- Toolbar: Search + Add -->
            <div class="mb-4 flex items-center gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search features..."
                        class="h-10 w-full rounded-lg border border-border bg-background pl-10 pr-9 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-primary focus:ring-1 focus:ring-primary"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <button
                    @click="toggleSort"
                    class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-border px-3 text-sm font-medium transition-colors hover:bg-accent cursor-pointer"
                    :title="sortDirection === 'asc' ? 'Oldest first' : 'Newest first'"
                >
                    <ArrowUp v-if="sortDirection === 'asc'" class="h-4 w-4" />
                    <ArrowDown v-else class="h-4 w-4" />
                    Date
                </button>
                <button
                    @click="showNewForm = !showNewForm"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 cursor-pointer"
                >
                    <Plus class="h-4 w-4" />
                    Add Feature
                </button>
            </div>

            <!-- New feature form -->
            <div v-if="showNewForm" class="mb-4 rounded-lg border border-primary/30 bg-card p-4">
                <h3 class="mb-3 text-sm font-semibold">New Feature</h3>
                <input
                    v-model="newTitle"
                    type="text"
                    placeholder="Feature title..."
                    class="mb-2 h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-primary focus:ring-1 focus:ring-primary"
                />
                <textarea
                    v-model="newDescription"
                    rows="2"
                    placeholder="Description (optional)..."
                    class="mb-3 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-primary focus:ring-1 focus:ring-primary"
                />
                <div class="flex items-center gap-2">
                    <button
                        @click="createFeature"
                        :disabled="creating || !newTitle.trim()"
                        class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90 cursor-pointer disabled:opacity-50"
                    >
                        Save
                    </button>
                    <button
                        @click="showNewForm = false; newTitle = ''; newDescription = ''"
                        :disabled="creating"
                        class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium transition-colors hover:bg-accent cursor-pointer disabled:opacity-50"
                    >
                        Cancel
                    </button>
                </div>
            </div>

            <!-- Features list -->
            <div class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                    Features ({{ features.length }})
                    <span v-if="searchQuery && filteredFeatures.length !== features.length" class="font-normal normal-case">
                        — {{ filteredFeatures.length }} found
                    </span>
                </h2>

                <p v-if="filteredFeatures.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    No features match your search.
                </p>

                <div
                    v-for="feature in filteredFeatures"
                    :key="feature.id"
                    class="rounded-lg border border-border bg-card p-4"
                >
                    <!-- View mode -->
                    <template v-if="editingId !== feature.id">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" />
                                <div class="min-w-0">
                                    <span class="text-sm font-medium" v-html="highlight(feature.title)" />
                                    <p
                                        v-if="feature.description"
                                        class="mt-1 text-sm text-muted-foreground whitespace-pre-line"
                                        v-html="highlight(feature.description)"
                                    />
                                </div>
                            </div>
                            <div class="shrink-0 flex items-center gap-1">
                                <button
                                    @click="startEditing(feature)"
                                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground cursor-pointer"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                    Edit
                                </button>
                                <button
                                    @click="confirmDelete(feature)"
                                    class="inline-flex items-center rounded-md px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive cursor-pointer"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Meta: dates + author -->
                        <div class="mt-2 ml-8 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground/70">
                            <span class="inline-flex items-center gap-1">
                                <Calendar class="h-3 w-3" />
                                Created: {{ formatDateTime(feature.created_at) }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <User class="h-3 w-3" />
                                {{ feature.creator?.name ?? 'System' }}
                            </span>
                            <span v-if="feature.updated_by" class="inline-flex items-center gap-1">
                                <Clock class="h-3 w-3" />
                                Updated: {{ formatDateTime(feature.updated_at) }}
                            </span>
                            <span v-if="feature.updater" class="inline-flex items-center gap-1">
                                <Pencil class="h-3 w-3" />
                                {{ feature.updater.name }}
                            </span>
                            <span
                                v-if="feature.is_custom"
                                class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary"
                            >
                                Custom
                            </span>
                        </div>
                    </template>

                    <!-- Edit mode -->
                    <template v-else>
                        <div class="space-y-2">
                            <input
                                v-model="editTitle"
                                type="text"
                                placeholder="Feature title..."
                                class="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-primary focus:ring-1 focus:ring-primary"
                            />
                            <textarea
                                v-model="editDescription"
                                rows="3"
                                placeholder="Description (optional)..."
                                class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-primary focus:ring-1 focus:ring-primary"
                            />
                            <div class="flex items-center gap-2">
                                <button
                                    @click="saveFeature(feature)"
                                    :disabled="saving || !editTitle.trim()"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90 cursor-pointer disabled:opacity-50"
                                >
                                    Save
                                </button>
                                <button
                                    @click="cancelEditing"
                                    :disabled="saving"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium transition-colors hover:bg-accent cursor-pointer disabled:opacity-50"
                                >
                                    <X class="h-3.5 w-3.5" />
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <!-- Delete Confirmation Dialog -->
            <Dialog v-model:open="showDeleteConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Feature?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete "{{ featureToDelete?.title }}"? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button variant="secondary" @click="showDeleteConfirm = false" class="flex-1 sm:flex-none">
                            No
                        </Button>
                        <Button variant="destructive" @click="deleteFeature" class="flex-1 sm:flex-none">
                            Yes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.search-highlight) {
    background-color: rgb(147 197 253 / 0.5);
    border-radius: 0.125rem;
    padding: 0.0625rem 0.125rem;
}
</style>
