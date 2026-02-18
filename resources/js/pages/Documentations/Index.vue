<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { FileText, Plus, Search, X, FolderTree, ExternalLink, ChevronRight } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { computed } from 'vue';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { useSearch } from '@/composables/useSearch';

interface Documentation {
    id: number;
    title: string;
    content: string | null;
    category: string | null;
    order: number;
    children?: Documentation[];
    created_at: string;
}

const props = defineProps<{
    project: Project;
    documentations: Documentation[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Documentations', href: `/projects/${props.project.id}/documentations` },
];

const { searchQuery, highlight } = useSearch();

const filterDocs = (docs: Documentation[]): Documentation[] => {
    if (!searchQuery.value.trim()) return docs;
    const query = searchQuery.value.toLowerCase();
    return docs.filter(doc => {
        const matchesSelf = doc.title.toLowerCase().includes(query) ||
            doc.content?.toLowerCase().includes(query);
        const matchingChildren = doc.children ? filterDocs(doc.children) : [];
        return matchesSelf || matchingChildren.length > 0;
    });
};

const filteredDocs = computed(() => filterDocs(props.documentations));

const filteredChildren = (children: Documentation[]): Documentation[] => filterDocs(children);

const highlightDescription = (content: string): string => {
    const plain = content.replace(/<[^>]*>/g, '').substring(0, 200) + '...';
    return highlight(plain);
};
</script>

<template>
    <Head title="Documentations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="flex items-start gap-2 text-2xl font-bold tracking-tight">
                    <FileText class="h-6 w-6 shrink-0 mt-1 text-primary" />
                    Documentations
                </h1>
                <RestrictedAction>
                    <Link :href="`/projects/${project.id}/documentations/create`">
                        <Button variant="cta" class="gap-2 cursor-pointer">
                            <Plus class="h-4 w-4" />
                            Documentation
                        </Button>
                    </Link>
                </RestrictedAction>
            </div>

            <div v-if="documentations.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <FileText class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No documentations yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Create your first documentation page.</p>
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/documentations/create`" class="mt-4 inline-block">
                            <Button variant="cta" class="gap-2 cursor-pointer">
                                <Plus class="h-4 w-4" />
                                Create Documentation
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <template v-else>
                <div class="grid gap-6 lg:grid-cols-4">
                    <!-- Sidebar with navigation -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-0 rounded-xl border bg-card shadow-sm">
                            <div class="p-3 border-b bg-muted/30">
                                <div class="flex items-center gap-2 text-sm font-medium">
                                    <FolderTree class="h-4 w-4 text-primary" />
                                    <span>Documents</span>
                                </div>
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
                            <div class="p-2 space-y-0.5 max-h-[calc(100vh-270px)] overflow-y-auto">
                                <template v-if="filteredDocs.length">
                                    <template v-for="doc in filteredDocs" :key="doc.id">
                                        <div class="group flex items-center justify-between rounded-lg px-3 py-2 cursor-pointer transition-all duration-150 hover:bg-muted/70">
                                            <Link
                                                :href="`/projects/${project.id}/documentations/${doc.id}`"
                                                class="flex items-center gap-2 min-w-0 flex-1"
                                            >
                                                <FileText class="h-4 w-4 shrink-0 text-primary" />
                                                <span class="font-medium text-sm truncate">{{ doc.title }}</span>
                                            </Link>
                                            <Link
                                                :href="`/projects/${project.id}/documentations/${doc.id}`"
                                                @click.stop
                                                class="p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity shrink-0 ml-2 hover:bg-muted"
                                            >
                                                <ExternalLink class="h-3 w-3" />
                                            </Link>
                                        </div>
                                        <!-- Nested children -->
                                        <template v-if="doc.children?.length">
                                            <template v-for="child in filteredChildren(doc.children)" :key="child.id">
                                                <div class="group flex items-center justify-between rounded-lg px-3 py-1.5 ml-4 cursor-pointer transition-all duration-150 hover:bg-muted/70">
                                                    <Link
                                                        :href="`/projects/${project.id}/documentations/${child.id}`"
                                                        class="flex items-center gap-2 min-w-0 flex-1"
                                                    >
                                                        <ChevronRight class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                        <span class="text-sm truncate">{{ child.title }}</span>
                                                    </Link>
                                                    <Link
                                                        :href="`/projects/${project.id}/documentations/${child.id}`"
                                                        @click.stop
                                                        class="p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity shrink-0 ml-2 hover:bg-muted"
                                                    >
                                                        <ExternalLink class="h-3 w-3" />
                                                    </Link>
                                                </div>
                                            </template>
                                        </template>
                                    </template>
                                </template>
                                <div v-else-if="searchQuery.trim()" class="px-3 py-2 text-sm text-muted-foreground">
                                    No matches found
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main content -->
                    <div class="lg:col-span-3 space-y-4">
                        <div v-if="filteredDocs.length === 0" class="flex flex-col items-center justify-center py-12">
                            <FileText class="h-12 w-12 text-muted-foreground/50 mb-4" />
                            <p class="text-muted-foreground">No documentations match your search.</p>
                        </div>

                        <div v-for="doc in filteredDocs" :key="doc.id" class="block cursor-pointer" @click="router.visit(`/projects/${project.id}/documentations/${doc.id}`)">
                            <Card class="hover:border-primary transition-colors">
                                <CardHeader class="pb-2">
                                    <div class="flex items-start justify-between">
                                        <CardTitle class="text-lg flex items-start gap-2">
                                            <FileText class="h-4 w-4 shrink-0 mt-0.5 text-primary" />
                                            <span v-html="highlight(doc.title)" />
                                        </CardTitle>
                                        <span v-if="doc.category" class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ doc.category }}
                                        </span>
                                    </div>
                                    <CardDescription v-if="doc.content" class="line-clamp-2" v-html="highlightDescription(doc.content)" />
                                </CardHeader>
                                <CardContent v-if="doc.children && doc.children.length > 0">
                                    <div class="flex flex-wrap gap-1.5">
                                        <Link
                                            v-for="child in doc.children"
                                            :key="child.id"
                                            :href="`/projects/${project.id}/documentations/${child.id}`"
                                            class="px-2.5 py-1 rounded-md bg-muted/60 text-xs font-medium text-muted-foreground hover:bg-primary/10 hover:text-primary transition-colors cursor-pointer"
                                            @click.stop
                                        >
                                            <span v-html="highlight(child.title)" />
                                        </Link>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </template>
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
