<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ClipboardList,
    Layers,
    Play,
    Bug,
    FileText,
    StickyNote,
    Search,
    CheckCircle2,
    User,
    Calendar,
    Clock,
    Database,
    X,
    ArrowRight,
    Palette,
    Drama,
    Rocket,
    BarChart3,
    Sparkles,
    Activity,
    Calculator,
    FolderTree,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Input } from '@/components/ui/input';
import { useSearch } from '@/composables/useSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { type HomeSection } from '@/types/checkmate';

const props = defineProps<{
    sections: HomeSection[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Project Updates', href: '/settings/project-updates' },
];

const { searchQuery, highlight } = useSearch();

const sectionIcons: Record<string, typeof ClipboardList> = {
    checklists: ClipboardList,
    'test-suites': Layers,
    'test-runs': Play,
    bugreports: Bug,
    design: Palette,
    automation: Drama,
    releases: Rocket,
    'test-coverage': BarChart3,
    'ai-generator': Sparkles,
    'test-data': Database,
    documentations: FileText,
    notes: StickyNote,
    'payout-monitor': Activity,
    'balance-calculator': Calculator,
};

const filteredSections = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.sections;
    }
    const query = searchQuery.value.toLowerCase();
    return props.sections.filter(
        (section) =>
            section.title.toLowerCase().includes(query) ||
            section.description.toLowerCase().includes(query) ||
            section.features.some((f) => f.toLowerCase().includes(query)),
    );
});

function scrollToSection(key: string): void {
    const el = document.getElementById(`section-${key}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function formatDate(dateString: string | null): string {
    if (!dateString) {
        return 'No data yet';
    }
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatDateTime(dateString: string | null): string {
    if (!dateString) {
        return 'No data yet';
    }
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
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Project Updates" />
        <SettingsLayout wide>
            <div class="flex flex-col gap-6">
                <Heading
                    title="Project Updates"
                    description="What's shipped in each module of CheckMate."
                />

                <div class="relative max-w-sm">
                    <Search
                        class="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search updates..."
                        class="bg-background/60 pr-8 pl-9"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                    <!-- Sidebar with section navigation -->
                    <div
                        v-if="filteredSections.length"
                        class="self-start lg:sticky lg:top-6 lg:col-span-1"
                    >
                        <div class="rounded-xl border bg-card shadow-sm">
                            <div
                                class="mb-2 flex items-center gap-2 border-b bg-muted/30 p-3 text-sm font-medium"
                            >
                                <FolderTree class="h-4 w-4 text-primary" />
                                <span>Sections</span>
                            </div>
                            <div class="space-y-0.5 p-2">
                                <button
                                    v-for="section in filteredSections"
                                    :key="section.key"
                                    type="button"
                                    class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-left transition-colors hover:bg-muted/70"
                                    @click="scrollToSection(section.key)"
                                >
                                    <component
                                        :is="sectionIcons[section.key]"
                                        class="h-4 w-4 shrink-0 text-primary"
                                    />
                                    <span
                                        class="truncate text-sm font-medium"
                                        >{{ section.title }}</span
                                    >
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Section cards -->
                    <div class="space-y-6 lg:col-span-3">
                        <p
                            v-if="filteredSections.length === 0"
                            class="py-12 text-center text-muted-foreground"
                        >
                            No sections match your search.
                        </p>

                        <div
                            v-for="section in filteredSections"
                            :key="section.key"
                            :id="`section-${section.key}`"
                            class="scroll-mt-6 rounded-xl border border-border bg-card p-6"
                        >
                            <!-- Card header -->
                            <Link
                                :href="`/settings/project-updates/${section.key}`"
                                class="group mb-4 flex cursor-pointer items-center gap-3"
                            >
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 transition-colors group-hover:bg-primary/20"
                                >
                                    <component
                                        :is="sectionIcons[section.key]"
                                        class="h-5 w-5 text-primary"
                                    />
                                </div>
                                <h2
                                    class="text-xl font-semibold transition-colors group-hover:text-primary"
                                    v-html="highlight(section.title)"
                                />
                            </Link>

                            <!-- Description -->
                            <p
                                class="mb-4 text-sm leading-relaxed text-muted-foreground"
                                v-html="highlight(section.description)"
                            />

                            <!-- Features list -->
                            <div class="mb-5">
                                <h3
                                    class="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Features
                                </h3>
                                <ul
                                    class="grid gap-1.5 sm:grid-cols-2 sm:gap-x-6"
                                >
                                    <li
                                        v-for="(
                                            feature, index
                                        ) in section.features"
                                        :key="index"
                                        class="flex items-start gap-2 text-sm"
                                    >
                                        <CheckCircle2
                                            class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500"
                                        />
                                        <span v-html="highlight(feature)" />
                                    </li>
                                </ul>
                            </div>

                            <!-- Footer: metadata -->
                            <div
                                class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-border pt-4 text-xs text-muted-foreground"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    <User class="h-3.5 w-3.5" />
                                    {{ section.author }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <Calendar class="h-3.5 w-3.5" />
                                    Created:
                                    {{ formatDate(section.latest_created_at) }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <Clock class="h-3.5 w-3.5" />
                                    Updated:
                                    {{
                                        formatDateTime(
                                            section.latest_updated_at,
                                        )
                                    }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <Database class="h-3.5 w-3.5" />
                                    {{ section.count }} features
                                </span>
                                <Link
                                    :href="`/settings/project-updates/${section.key}`"
                                    class="ml-auto inline-flex cursor-pointer items-center gap-1.5 font-medium text-primary transition-colors hover:text-primary/80"
                                >
                                    View Details
                                    <ArrowRight class="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

<style scoped>
:deep(.search-highlight) {
    background-color: rgb(147 197 253 / 0.5);
    border-radius: 0.125rem;
    padding: 0.0625rem 0.125rem;
}
</style>
