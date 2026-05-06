<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import type { Release } from '@/types/checkmate';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Rocket, Plus, Search, X, Calendar, Filter } from 'lucide-vue-next';
import RestrictedAction from '@/components/RestrictedAction.vue';
import {
    releaseStatusVariant,
    releaseDecisionVariant,
} from '@/lib/badge-variants';

const props = defineProps<{
    project: Project;
    releases: Release[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Releases', href: `/projects/${props.project.id}/releases` },
];

// Filters
const searchQuery = ref('');
const statusFilter = ref('all');
const healthFilter = ref('all');
const showFilters = ref(false);
const filterPlannedFrom = ref('');
const filterPlannedTo = ref('');
const filterActualFrom = ref('');
const filterActualTo = ref('');

const activeFilterCount = computed(() => {
    return [
        filterPlannedFrom,
        filterPlannedTo,
        filterActualFrom,
        filterActualTo,
    ].filter((f) => f.value !== '').length;
});

const clearFilters = () => {
    filterPlannedFrom.value = '';
    filterPlannedTo.value = '';
    filterActualFrom.value = '';
    filterActualTo.value = '';
};

const filteredReleases = computed(() => {
    let result = [...props.releases];

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(
            (r) =>
                r.version.toLowerCase().includes(q) ||
                r.name.toLowerCase().includes(q) ||
                r.description?.toLowerCase().includes(q),
        );
    }

    if (statusFilter.value !== 'all') {
        result = result.filter((r) => r.status === statusFilter.value);
    }

    if (healthFilter.value !== 'all') {
        result = result.filter((r) => r.health === healthFilter.value);
    }

    if (filterPlannedFrom.value) {
        result = result.filter(
            (r) => r.planned_date && r.planned_date >= filterPlannedFrom.value,
        );
    }
    if (filterPlannedTo.value) {
        result = result.filter(
            (r) => r.planned_date && r.planned_date <= filterPlannedTo.value,
        );
    }
    if (filterActualFrom.value) {
        result = result.filter(
            (r) => r.actual_date && r.actual_date >= filterActualFrom.value,
        );
    }
    if (filterActualTo.value) {
        result = result.filter(
            (r) => r.actual_date && r.actual_date <= filterActualTo.value,
        );
    }

    return result;
});

// Create dialog
const showCreateDialog = ref(false);
const createForm = ref({
    version: '',
    name: '',
    description: '',
    planned_date: '',
});
const creating = ref(false);

const createRelease = () => {
    creating.value = true;
    router.post(
        `/projects/${props.project.id}/releases`,
        {
            ...createForm.value,
            description: createForm.value.description || null,
            planned_date: createForm.value.planned_date || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showCreateDialog.value = false;
                createForm.value = {
                    version: '',
                    name: '',
                    description: '',
                    planned_date: '',
                };
            },
            onFinish: () => {
                creating.value = false;
            },
        },
    );
};

// Helpers
const getStatusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        planning: 'Planning',
        development: 'Development',
        testing: 'Testing',
        staging: 'Staging',
        ready: 'Ready',
        released: 'Released',
        cancelled: 'Cancelled',
    };
    return labels[status] || status;
};

const getHealthColor = (health: string): string => {
    if (health === 'green') return 'bg-emerald-500';
    if (health === 'red') return 'bg-red-500';
    return 'bg-amber-500';
};

const getDecisionLabel = (d: string): string => {
    const labels: Record<string, string> = {
        pending: 'Pending',
        go: 'Go',
        no_go: 'No-Go',
        conditional: 'Conditional',
    };
    return labels[d] || d;
};

const formatDate = (date: string | null): string => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="`Releases - ${project.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div>
                <div class="flex items-center justify-between">
                    <div>
                        <h1
                            class="flex items-center gap-2 text-2xl font-bold tracking-tight"
                        >
                            <Rocket class="h-6 w-6 shrink-0 text-primary" />
                            Release Management
                        </h1>
                        <p class="text-muted-foreground">
                            Plan, track, and manage product releases
                        </p>
                    </div>
                </div>
                <div
                    v-if="releases.length"
                    class="mt-4 flex items-center justify-end gap-2"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search releases..."
                            class="w-56 bg-background/60 pr-8 pl-9"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <Select v-model="statusFilter">
                        <SelectTrigger class="w-40 bg-background/60">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem value="planning">Planning</SelectItem>
                            <SelectItem value="development"
                                >Development</SelectItem
                            >
                            <SelectItem value="testing">Testing</SelectItem>
                            <SelectItem value="staging">Staging</SelectItem>
                            <SelectItem value="ready">Ready</SelectItem>
                            <SelectItem value="released">Released</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="healthFilter">
                        <SelectTrigger class="w-36 bg-background/60">
                            <SelectValue placeholder="Health" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Health</SelectItem>
                            <SelectItem value="green">Green</SelectItem>
                            <SelectItem value="yellow">Yellow</SelectItem>
                            <SelectItem value="red">Red</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        variant="outline"
                        class="relative cursor-pointer gap-2"
                        @click="showFilters = !showFilters"
                    >
                        <Filter class="h-4 w-4" />
                        Filter
                        <Badge
                            v-if="activeFilterCount > 0"
                            class="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full p-0 text-[10px]"
                        >
                            {{ activeFilterCount }}
                        </Badge>
                    </Button>
                    <RestrictedAction>
                        <Button
                            variant="cta"
                            @click="showCreateDialog = true"
                            class="cursor-pointer gap-2"
                        >
                            <Plus class="h-4 w-4" />
                            Create Release
                        </Button>
                    </RestrictedAction>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="relative -mt-3">
                <div
                    v-if="showFilters"
                    class="fixed inset-0 z-10"
                    @click="showFilters = false"
                />
                <div
                    v-if="showFilters"
                    class="absolute top-0 right-0 z-20 w-full animate-in rounded-xl border bg-card p-4 shadow-lg duration-200 fade-in slide-in-from-top-2 md:w-[calc(50%-0.3125rem)]"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <span
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <Filter class="h-4 w-4 text-primary" />
                            Date Filters
                        </span>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="activeFilterCount > 0"
                                variant="ghost"
                                size="sm"
                                class="h-6 cursor-pointer gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                                @click="clearFilters"
                            >
                                <X class="h-3 w-3" />
                                Clear All
                            </Button>
                            <button
                                @click="showFilters = false"
                                class="cursor-pointer rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <!-- Row 1: Planned From, Planned To -->
                    <div class="grid grid-cols-2 gap-x-3 gap-y-2.5">
                        <div class="relative">
                            <Label
                                class="mb-1 block text-[11px] text-muted-foreground"
                                >Planned From</Label
                            >
                            <Input
                                v-model="filterPlannedFrom"
                                type="date"
                                class="h-8 text-xs"
                                :class="filterPlannedFrom ? 'pr-7' : ''"
                            />
                            <button
                                v-if="filterPlannedFrom"
                                @click="filterPlannedFrom = ''"
                                class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                        <div class="relative">
                            <Label
                                class="mb-1 block text-[11px] text-muted-foreground"
                                >Planned To</Label
                            >
                            <Input
                                v-model="filterPlannedTo"
                                type="date"
                                class="h-8 text-xs"
                                :class="filterPlannedTo ? 'pr-7' : ''"
                            />
                            <button
                                v-if="filterPlannedTo"
                                @click="filterPlannedTo = ''"
                                class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                    <!-- Row 2: Actual From, Actual To -->
                    <div class="mt-2.5 grid grid-cols-2 gap-x-3 gap-y-2.5">
                        <div class="relative">
                            <Label
                                class="mb-1 block text-[11px] text-muted-foreground"
                                >Actual From</Label
                            >
                            <Input
                                v-model="filterActualFrom"
                                type="date"
                                class="h-8 text-xs"
                                :class="filterActualFrom ? 'pr-7' : ''"
                            />
                            <button
                                v-if="filterActualFrom"
                                @click="filterActualFrom = ''"
                                class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                        <div class="relative">
                            <Label
                                class="mb-1 block text-[11px] text-muted-foreground"
                                >Actual To</Label
                            >
                            <Input
                                v-model="filterActualTo"
                                type="date"
                                class="h-8 text-xs"
                                :class="filterActualTo ? 'pr-7' : ''"
                            />
                            <button
                                v-if="filterActualTo"
                                @click="filterActualTo = ''"
                                class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                    <!-- Results count -->
                    <div class="mt-3 flex items-center justify-end">
                        <span class="text-sm text-muted-foreground">
                            Found
                            <span class="font-semibold text-foreground">{{
                                filteredReleases.length
                            }}</span>
                            {{
                                filteredReleases.length === 1
                                    ? 'release'
                                    : 'releases'
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Release Cards -->
            <div v-if="filteredReleases.length" class="space-y-3">
                <Card
                    v-for="release in filteredReleases"
                    :key="release.id"
                    class="cursor-pointer transition-all hover:border-primary"
                    @click="
                        router.visit(
                            `/projects/${project.id}/releases/${release.id}`,
                        )
                    "
                >
                    <CardContent class="px-4 py-3">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3">
                                    <Badge
                                        variant="outline"
                                        class="font-mono text-xs"
                                    >
                                        v{{ release.version }}
                                    </Badge>
                                    <h3
                                        class="truncate text-sm font-semibold text-foreground"
                                    >
                                        {{ release.name }}
                                    </h3>
                                    <Badge
                                        :variant="
                                            releaseStatusVariant(release.status)
                                        "
                                        class="text-xs"
                                    >
                                        {{ getStatusLabel(release.status) }}
                                    </Badge>
                                    <div
                                        class="flex items-center gap-1.5"
                                        :title="`Health: ${release.health}`"
                                    >
                                        <div
                                            class="h-2.5 w-2.5 rounded-full"
                                            :class="
                                                getHealthColor(release.health)
                                            "
                                        />
                                    </div>
                                    <Badge
                                        :variant="
                                            releaseDecisionVariant(
                                                release.decision,
                                            )
                                        "
                                        class="text-xs"
                                    >
                                        {{ getDecisionLabel(release.decision) }}
                                    </Badge>
                                </div>
                                <div
                                    class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <Calendar class="h-3 w-3" />
                                        Planned:
                                        {{ formatDate(release.planned_date) }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <Calendar class="h-3 w-3" />
                                        Actual:
                                        {{ formatDate(release.actual_date) }}
                                    </span>
                                    <span class="text-muted-foreground/50"
                                        >|</span
                                    >
                                    <span
                                        >{{
                                            release.features_count ?? 0
                                        }}
                                        features</span
                                    >
                                </div>
                            </div>
                            <div
                                v-if="release.checklist_items_count"
                                class="shrink-0 text-right"
                            >
                                <div class="text-lg font-bold text-primary">
                                    {{ release.checklist_progress ?? 0 }}%
                                </div>
                                <Progress
                                    :model-value="
                                        release.checklist_progress ?? 0
                                    "
                                    class="h-2 w-24"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty state -->
            <div v-else class="flex flex-1 items-center justify-center py-12">
                <div class="text-center">
                    <Rocket class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No releases yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Create your first release to start tracking.
                    </p>
                    <RestrictedAction>
                        <Button
                            variant="cta"
                            @click="showCreateDialog = true"
                            class="mt-4 cursor-pointer gap-2"
                        >
                            <Plus class="h-4 w-4" />
                            Create Release
                        </Button>
                    </RestrictedAction>
                </div>
            </div>
        </div>

        <!-- Create Release Dialog -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create Release</DialogTitle>
                    <DialogDescription
                        >Set up a new release for this
                        project.</DialogDescription
                    >
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Version *</Label>
                            <Input
                                v-model="createForm.version"
                                placeholder="e.g. 1.0.0"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label>Planned Date</Label>
                            <Input
                                v-model="createForm.planned_date"
                                type="date"
                                class="mt-1"
                            />
                        </div>
                    </div>
                    <div>
                        <Label>Name *</Label>
                        <Input
                            v-model="createForm.name"
                            placeholder="Release name"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea
                            v-model="createForm.description"
                            placeholder="Release description..."
                            class="mt-1"
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showCreateDialog = false"
                        class="cursor-pointer"
                        >Cancel</Button
                    >
                    <Button
                        @click="createRelease"
                        :disabled="
                            creating ||
                            !createForm.version.trim() ||
                            !createForm.name.trim()
                        "
                        class="cursor-pointer"
                    >
                        Create
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
