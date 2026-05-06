<script setup lang="ts">
import { Head, Link, Deferred } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Bug, Plus, Search, X, Filter, Tag } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ref, computed } from 'vue';
import RestrictedAction from '@/components/RestrictedAction.vue';
import FeatureBadges from '@/components/FeatureBadges.vue';
import { severityVariant, bugStatusVariant } from '@/lib/badge-variants';
import { useSearch } from '@/composables/useSearch';

interface Bugreport {
    id: number;
    title: string;
    description: string | null;
    severity: 'critical' | 'major' | 'minor' | 'trivial';
    priority: 'high' | 'medium' | 'low';
    status:
        | 'to_do'
        | 'in_progress'
        | 'in_review'
        | 'needs_changes'
        | 'cancelled'
        | 'done';
    fixed_on: string[] | null;
    reporter: { id: number; name: string } | null;
    assignee: { id: number; name: string } | null;
    project_features?: {
        id: number;
        name?: string;
        module?: string[] | null;
    }[];
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    project: Project;
    bugreports: Bugreport[];
    availableFeatures: { id: number; name: string; module: string[] | null }[];
    users?: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Bugreports', href: `/projects/${props.project.id}/bugreports` },
];

const { searchQuery, highlight } = useSearch();

// Filters
const showFilters = ref(false);
const filterStatus = ref('');
const filterPriority = ref('');
const filterSeverity = ref('');
const filterAuthor = ref('');
const filterFeature = ref('');
const filterFixedOn = ref('');
const filterCreatedFrom = ref('');
const filterCreatedTo = ref('');
const filterUpdatedFrom = ref('');
const filterUpdatedTo = ref('');

const activeFilterCount = computed(() => {
    return [
        filterStatus,
        filterPriority,
        filterSeverity,
        filterAuthor,
        filterFeature,
        filterFixedOn,
        filterCreatedFrom,
        filterCreatedTo,
        filterUpdatedFrom,
        filterUpdatedTo,
    ].filter((f) => f.value !== '').length;
});

const clearFilters = () => {
    filterStatus.value = '';
    filterPriority.value = '';
    filterSeverity.value = '';
    filterAuthor.value = '';
    filterFeature.value = '';
    filterFixedOn.value = '';
    filterCreatedFrom.value = '';
    filterCreatedTo.value = '';
    filterUpdatedFrom.value = '';
    filterUpdatedTo.value = '';
};

const filteredBugreports = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const hasSearch = query.length > 0;
    const hasFilters = activeFilterCount.value > 0;

    if (!hasSearch && !hasFilters) return props.bugreports;

    return props.bugreports.filter((bug) => {
        // Search filter
        if (
            hasSearch &&
            !bug.title.toLowerCase().includes(query) &&
            !bug.description?.toLowerCase().includes(query)
        )
            return false;
        // Status filter
        if (filterStatus.value && bug.status !== filterStatus.value)
            return false;
        // Priority filter
        if (filterPriority.value && bug.priority !== filterPriority.value)
            return false;
        // Severity filter
        if (filterSeverity.value && bug.severity !== filterSeverity.value)
            return false;
        // Author filter
        if (
            filterAuthor.value &&
            String(bug.reporter?.id) !== filterAuthor.value
        )
            return false;
        // Feature filter
        if (filterFeature.value === '__none__') {
            if (bug.project_features && bug.project_features.length > 0)
                return false;
        } else if (filterFeature.value) {
            if (
                !bug.project_features?.some(
                    (f) => String(f.id) === filterFeature.value,
                )
            )
                return false;
        }
        // Fixed On filter
        if (filterFixedOn.value === '__none__') {
            if (bug.fixed_on && bug.fixed_on.length > 0) return false;
        } else if (filterFixedOn.value) {
            if (!bug.fixed_on?.includes(filterFixedOn.value)) return false;
        }
        // Date filters
        if (filterCreatedFrom.value && bug.created_at < filterCreatedFrom.value)
            return false;
        if (
            filterCreatedTo.value &&
            bug.created_at.slice(0, 10) > filterCreatedTo.value
        )
            return false;
        if (filterUpdatedFrom.value && bug.updated_at < filterUpdatedFrom.value)
            return false;
        if (
            filterUpdatedTo.value &&
            bug.updated_at.slice(0, 10) > filterUpdatedTo.value
        )
            return false;
        return true;
    });
});
</script>

<template>
    <Head title="Bugreports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1
                    class="flex items-start gap-2 text-2xl font-bold tracking-tight"
                >
                    <Bug class="mt-1 h-6 w-6 shrink-0 text-primary" />
                    Bugreports
                </h1>
                <div class="flex items-center gap-2">
                    <template v-if="bugreports.length > 0">
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search bugreports..."
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
                    </template>
                    <RestrictedAction>
                        <Link
                            :href="`/projects/${project.id}/bugreports/create`"
                        >
                            <Button variant="cta" class="gap-2">
                                <Plus class="h-4 w-4" />
                                Report Bug
                            </Button>
                        </Link>
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
                            Filters
                            <Badge
                                v-if="activeFilterCount > 0"
                                class="h-5 rounded-full px-1.5 text-[10px]"
                                >{{ activeFilterCount }}</Badge
                            >
                        </span>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="activeFilterCount > 0"
                                variant="ghost"
                                size="sm"
                                class="h-6 cursor-pointer gap-1 text-xs text-muted-foreground hover:text-destructive"
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
                    <div class="space-y-2.5">
                        <!-- Row 1: Status, Priority, Severity -->
                        <div class="grid grid-cols-3 gap-x-3">
                            <div class="relative">
                                <Label
                                    class="mb-1 block text-[11px] text-muted-foreground"
                                    >Status</Label
                                >
                                <Select v-model="filterStatus">
                                    <SelectTrigger
                                        class="h-8 cursor-pointer text-xs"
                                        :class="filterStatus ? 'pr-7' : ''"
                                    >
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="to_do"
                                            >To Do</SelectItem
                                        >
                                        <SelectItem value="in_progress"
                                            >In Progress</SelectItem
                                        >
                                        <SelectItem value="in_review"
                                            >In Review</SelectItem
                                        >
                                        <SelectItem value="needs_changes"
                                            >Needs Changes</SelectItem
                                        >
                                        <SelectItem value="cancelled"
                                            >Cancelled</SelectItem
                                        >
                                        <SelectItem value="done"
                                            >Done</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <button
                                    v-if="filterStatus"
                                    @click="filterStatus = ''"
                                    class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <div class="relative">
                                <Label
                                    class="mb-1 block text-[11px] text-muted-foreground"
                                    >Priority</Label
                                >
                                <Select v-model="filterPriority">
                                    <SelectTrigger
                                        class="h-8 cursor-pointer text-xs"
                                        :class="filterPriority ? 'pr-7' : ''"
                                    >
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="high"
                                            >High</SelectItem
                                        >
                                        <SelectItem value="medium"
                                            >Medium</SelectItem
                                        >
                                        <SelectItem value="low">Low</SelectItem>
                                    </SelectContent>
                                </Select>
                                <button
                                    v-if="filterPriority"
                                    @click="filterPriority = ''"
                                    class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <div class="relative">
                                <Label
                                    class="mb-1 block text-[11px] text-muted-foreground"
                                    >Severity</Label
                                >
                                <Select v-model="filterSeverity">
                                    <SelectTrigger
                                        class="h-8 cursor-pointer text-xs"
                                        :class="filterSeverity ? 'pr-7' : ''"
                                    >
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="critical"
                                            >Critical</SelectItem
                                        >
                                        <SelectItem value="major"
                                            >Major</SelectItem
                                        >
                                        <SelectItem value="minor"
                                            >Minor</SelectItem
                                        >
                                        <SelectItem value="trivial"
                                            >Trivial</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <button
                                    v-if="filterSeverity"
                                    @click="filterSeverity = ''"
                                    class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                        <!-- Row 2: Feature, Fixed On, Author -->
                        <div class="grid grid-cols-3 gap-x-3">
                            <div class="relative">
                                <Label
                                    class="mb-1 block text-[11px] text-muted-foreground"
                                    >Feature</Label
                                >
                                <Select v-model="filterFeature">
                                    <SelectTrigger
                                        class="h-8 cursor-pointer text-xs"
                                        :class="filterFeature ? 'pr-7' : ''"
                                    >
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__"
                                            >No feature</SelectItem
                                        >
                                        <SelectItem
                                            v-for="feature in availableFeatures"
                                            :key="feature.id"
                                            :value="String(feature.id)"
                                        >
                                            {{
                                                feature.module?.length
                                                    ? `${feature.module.join(', ')} / `
                                                    : ''
                                            }}{{ feature.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <button
                                    v-if="filterFeature"
                                    @click="filterFeature = ''"
                                    class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <div class="relative">
                                <Label
                                    class="mb-1 block text-[11px] text-muted-foreground"
                                    >Fixed On</Label
                                >
                                <Select v-model="filterFixedOn">
                                    <SelectTrigger
                                        class="h-8 cursor-pointer text-xs"
                                        :class="filterFixedOn ? 'pr-7' : ''"
                                    >
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__"
                                            >Not fixed</SelectItem
                                        >
                                        <SelectItem value="develop"
                                            >Develop</SelectItem
                                        >
                                        <SelectItem value="staging"
                                            >Staging</SelectItem
                                        >
                                        <SelectItem value="production"
                                            >Production</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <button
                                    v-if="filterFixedOn"
                                    @click="filterFixedOn = ''"
                                    class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <Deferred data="users">
                                <template #fallback>
                                    <div>
                                        <Label
                                            class="mb-1 block text-[11px] text-muted-foreground"
                                            >Author</Label
                                        >
                                        <div
                                            class="h-8 w-full animate-pulse rounded-md bg-muted"
                                        />
                                    </div>
                                </template>
                                <div class="relative">
                                    <Label
                                        class="mb-1 block text-[11px] text-muted-foreground"
                                        >Author</Label
                                    >
                                    <Select v-model="filterAuthor">
                                        <SelectTrigger
                                            class="h-8 cursor-pointer text-xs"
                                            :class="filterAuthor ? 'pr-7' : ''"
                                        >
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
                                    <button
                                        v-if="filterAuthor"
                                        @click="filterAuthor = ''"
                                        class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                            </Deferred>
                        </div>
                        <!-- Row 3: Created From, Created To -->
                        <div class="grid grid-cols-3 gap-x-3">
                            <div class="relative">
                                <Label
                                    class="mb-1 block text-[11px] text-muted-foreground"
                                    >Created From</Label
                                >
                                <Input
                                    v-model="filterCreatedFrom"
                                    type="date"
                                    class="h-8 text-xs"
                                    :class="filterCreatedFrom ? 'pr-7' : ''"
                                />
                                <button
                                    v-if="filterCreatedFrom"
                                    @click="filterCreatedFrom = ''"
                                    class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <div class="relative">
                                <Label
                                    class="mb-1 block text-[11px] text-muted-foreground"
                                    >Created To</Label
                                >
                                <Input
                                    v-model="filterCreatedTo"
                                    type="date"
                                    class="h-8 text-xs"
                                    :class="filterCreatedTo ? 'pr-7' : ''"
                                />
                                <button
                                    v-if="filterCreatedTo"
                                    @click="filterCreatedTo = ''"
                                    class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                        <!-- Row 4: Updated From, Updated To, Results count -->
                        <div class="grid grid-cols-3 gap-x-3">
                            <div class="relative">
                                <Label
                                    class="mb-1 block text-[11px] text-muted-foreground"
                                    >Updated From</Label
                                >
                                <Input
                                    v-model="filterUpdatedFrom"
                                    type="date"
                                    class="h-8 text-xs"
                                    :class="filterUpdatedFrom ? 'pr-7' : ''"
                                />
                                <button
                                    v-if="filterUpdatedFrom"
                                    @click="filterUpdatedFrom = ''"
                                    class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <div class="relative">
                                <Label
                                    class="mb-1 block text-[11px] text-muted-foreground"
                                    >Updated To</Label
                                >
                                <Input
                                    v-model="filterUpdatedTo"
                                    type="date"
                                    class="h-8 text-xs"
                                    :class="filterUpdatedTo ? 'pr-7' : ''"
                                />
                                <button
                                    v-if="filterUpdatedTo"
                                    @click="filterUpdatedTo = ''"
                                    class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                            <div class="flex h-full items-end justify-center">
                                <span
                                    class="pb-1.5 text-sm text-muted-foreground"
                                >
                                    Found
                                    <span
                                        class="font-semibold text-foreground"
                                        >{{ filteredBugreports.length }}</span
                                    >
                                    {{
                                        filteredBugreports.length === 1
                                            ? 'bug'
                                            : 'bugs'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="bugreports.length === 0"
                class="flex flex-1 items-center justify-center"
            >
                <div class="text-center">
                    <Bug class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">
                        No bugreports yet
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Report your first bug to start tracking issues.
                    </p>
                    <RestrictedAction>
                        <Link
                            :href="`/projects/${project.id}/bugreports/create`"
                            class="mt-4 inline-block"
                        >
                            <Button variant="cta" class="gap-2">
                                <Plus class="h-4 w-4" />
                                Report Bug
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <div
                v-else-if="filteredBugreports.length === 0"
                class="flex flex-col items-center justify-center py-12"
            >
                <Bug class="mb-4 h-12 w-12 text-muted-foreground/50" />
                <p class="text-muted-foreground">
                    No bugreports match your search.
                </p>
            </div>

            <div v-else class="grid gap-2.5 md:grid-cols-2">
                <Link
                    v-for="bug in filteredBugreports"
                    :key="bug.id"
                    :href="`/projects/${project.id}/bugreports/${bug.id}`"
                    class="block"
                >
                    <Card
                        class="cursor-pointer transition-colors hover:border-primary"
                    >
                        <CardContent class="px-3 py-2">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <Bug
                                            class="h-4 w-4 shrink-0 text-primary"
                                        />
                                        <h3
                                            class="truncate text-base font-semibold"
                                            v-html="highlight(bug.title)"
                                        />
                                        <Badge
                                            :variant="
                                                severityVariant(bug.severity)
                                            "
                                            class="h-4 shrink-0 px-1.5 text-[10px]"
                                        >
                                            {{ bug.severity }}
                                        </Badge>
                                        <Badge
                                            :variant="
                                                bugStatusVariant(bug.status)
                                            "
                                            class="h-4 shrink-0 px-1.5 text-[10px]"
                                        >
                                            {{
                                                bug.status
                                                    .split('_')
                                                    .map(
                                                        (w) =>
                                                            w
                                                                .charAt(0)
                                                                .toUpperCase() +
                                                            w.slice(1),
                                                    )
                                                    .join(' ')
                                            }}
                                        </Badge>
                                        <FeatureBadges
                                            v-if="bug.project_features?.length"
                                            :features="bug.project_features"
                                            :max-visible="2"
                                        />
                                    </div>
                                    <div
                                        class="mt-2 flex items-center gap-3 text-xs text-muted-foreground"
                                    >
                                        <span v-if="bug.reporter">{{
                                            bug.reporter.name
                                        }}</span>
                                        <span
                                            v-if="bug.assignee"
                                            class="flex items-center gap-1"
                                        >
                                            <span
                                                class="text-muted-foreground/50"
                                                >→</span
                                            >
                                            {{ bug.assignee.name }}
                                        </span>
                                        <span
                                            v-if="bug.description"
                                            class="max-w-xs truncate text-muted-foreground/70"
                                            v-html="highlight(bug.description)"
                                        />
                                        <span
                                            v-if="bug.fixed_on?.length"
                                            class="ml-auto flex shrink-0 items-center gap-1"
                                        >
                                            <Badge
                                                v-for="env in bug.fixed_on"
                                                :key="env"
                                                variant="success"
                                                class="h-4 px-1.5 text-[10px]"
                                            >
                                                {{
                                                    env
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                    env.slice(1)
                                                }}
                                            </Badge>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
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
