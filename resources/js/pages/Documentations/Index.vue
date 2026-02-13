<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { FileText, Plus, Search, X } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { ref, computed } from 'vue';

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

const searchQuery = ref('');

const filteredDocs = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.documentations;
    }
    const query = searchQuery.value.toLowerCase();

    const filterDocs = (docs: Documentation[]): Documentation[] => {
        return docs.filter(doc => {
            const matchesSelf = doc.title.toLowerCase().includes(query) ||
                doc.content?.toLowerCase().includes(query);
            const matchingChildren = doc.children ? filterDocs(doc.children) : [];
            return matchesSelf || matchingChildren.length > 0;
        });
    };

    return filterDocs(props.documentations);
});

const escapeRegExp = (str: string): string => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const escapeHtml = (str: string): string => str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const highlight = (text: string): string => {
    const safe = escapeHtml(text);
    if (!searchQuery.value.trim()) return safe;
    const query = escapeRegExp(searchQuery.value.trim());
    return safe.replace(new RegExp(`(${query})`, 'gi'), '<mark class="search-highlight">$1</mark>');
};

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
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search documentations..."
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
                    <Link :href="`/projects/${project.id}/documentations/create`">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Add Documentation
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-if="filteredDocs.length === 0" class="flex flex-col items-center justify-center py-12">
                <FileText class="h-12 w-12 text-muted-foreground/50 mb-4" />
                <p class="text-muted-foreground">No documentations found.</p>
            </div>

            <div v-else class="grid gap-4">
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
    </AppLayout>
</template>

<style scoped>
:deep(.search-highlight) {
    background-color: rgb(147 197 253 / 0.5);
    border-radius: 0.125rem;
    padding: 0.0625rem 0.125rem;
}
</style>
