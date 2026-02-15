<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { home } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { type HomeSection } from '@/types/checkmate';
import { Input } from '@/components/ui/input';
import {
    ClipboardList,
    Layers,
    Play,
    Bug,
    FileText,
    StickyNote,
    Search,
    RefreshCw,
    CheckCircle2,
    User,
    Calendar,
    Clock,
    Database,
    X,
    ArrowRight,
    ExternalLink,
    FolderTree,
} from 'lucide-vue-next';

const props = defineProps<{
    sections: HomeSection[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: home().url,
    },
];

const searchQuery = ref('');
const isRefreshing = ref(false);

const sectionIcons: Record<string, typeof ClipboardList> = {
    checklists: ClipboardList,
    'test-suites': Layers,
    'test-runs': Play,
    bugreports: Bug,
    documentations: FileText,
    notes: StickyNote,
};

const escapeRegExp = (str: string): string => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const escapeHtml = (str: string): string => str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const highlight = (text: string): string => {
    const safe = escapeHtml(text);
    if (!searchQuery.value.trim()) return safe;
    const query = escapeRegExp(searchQuery.value.trim());
    return safe.replace(new RegExp(`(${query})`, 'gi'), '<mark class="search-highlight">$1</mark>');
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

function refreshData(): void {
    isRefreshing.value = true;
    router.post('/home/sync', {}, {
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
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
    <Head title="Home" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-6">
            <!-- Header: Logo + Description -->
            <div class="mb-6 flex items-center gap-6 rounded-xl border border-border bg-card p-6">
                <div class="shrink-0 text-center">
                    <img src="/images/logo2.png" alt="CheckMate" class="h-32 w-auto rounded-2xl" />
                    <p class="mt-2 text-xs font-light tracking-widest uppercase text-muted-foreground/80">
                        Checkmate your bugs
                    </p>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold">CheckMate</h1>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        An all-in-one QA management platform for teams of any size. Organize checklists, build and execute test suites, track bug reports, maintain living documentation, and capture notes — all in a single workspace. Designed to streamline your quality assurance workflow from planning through release.
                    </p>
                </div>
            </div>

            <!-- Grid layout: Sidebar + Content -->
            <div class="grid gap-6 lg:grid-cols-4">
                <!-- Sidebar with navigation -->
                <div class="lg:col-span-1">
                    <div class="sticky top-0 rounded-xl border bg-card shadow-sm">
                        <div class="p-3 border-b bg-muted/30">
                            <div class="flex items-center gap-2 text-sm font-medium mb-2">
                                <FolderTree class="h-4 w-4 text-primary" />
                                <span>Sections</span>
                            </div>
                            <button
                                @click="refreshData"
                                :disabled="isRefreshing"
                                class="mb-2 flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90 cursor-pointer disabled:opacity-50"
                            >
                                <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRefreshing }" />
                                {{ isRefreshing ? 'Syncing...' : 'Sync Features' }}
                            </button>
                            <div class="relative mt-2">
                                <Search class="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search..."
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
                        <div class="p-2 space-y-0.5">
                            <template v-if="filteredSections.length">
                                <div
                                    v-for="section in filteredSections"
                                    :key="section.key"
                                    class="group flex items-center justify-between rounded-lg px-3 py-2 cursor-pointer transition-all duration-150 hover:bg-muted/70"
                                    @click="scrollToSection(section.key)"
                                >
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <component :is="sectionIcons[section.key]" class="h-4 w-4 shrink-0 text-primary" />
                                        <span class="font-medium text-sm truncate">{{ section.title }}</span>
                                    </div>
                                    <Link
                                        :href="`/home/${section.key}`"
                                        @click.stop
                                        class="p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity shrink-0 ml-2 hover:bg-muted"
                                    >
                                        <ExternalLink class="h-3 w-3" />
                                    </Link>
                                </div>
                            </template>
                            <div v-else class="px-3 py-2 text-sm text-muted-foreground">
                                No matches found
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main content: Section cards -->
                <div class="lg:col-span-3 space-y-6">
                    <p v-if="filteredSections.length === 0" class="py-12 text-center text-muted-foreground">
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
                            :href="`/home/${section.key}`"
                            class="mb-4 flex items-center gap-3 group cursor-pointer"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 transition-colors group-hover:bg-primary/20">
                                <component :is="sectionIcons[section.key]" class="h-5 w-5 text-primary" />
                            </div>
                            <h2 class="text-xl font-semibold group-hover:text-primary transition-colors" v-html="highlight(section.title)" />
                        </Link>

                        <!-- Description -->
                        <p class="mb-4 text-sm leading-relaxed text-muted-foreground" v-html="highlight(section.description)" />

                        <!-- Features list -->
                        <div class="mb-5">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Features</h3>
                            <ul class="grid gap-1.5 sm:grid-cols-2 sm:gap-x-6">
                                <li
                                    v-for="(feature, index) in section.features"
                                    :key="index"
                                    class="flex items-start gap-2 text-sm"
                                >
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                                    <span v-html="highlight(feature)" />
                                </li>
                            </ul>
                        </div>

                        <!-- Footer: metadata -->
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
                            <Link
                                :href="`/home/${section.key}`"
                                class="ml-auto inline-flex items-center gap-1.5 font-medium text-primary hover:text-primary/80 transition-colors cursor-pointer"
                            >
                                View Details
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>
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
