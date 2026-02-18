<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type Attachment } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FileText, Edit, Trash2, ChevronRight, Download, Paperclip, FolderTree, ExternalLink, Plus, Search, X, Link2, Check } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { useSearch, escapeRegExp } from '@/composables/useSearch';

interface Documentation {
    id: number;
    title: string;
    content: string | null;
    category: string | null;
    order: number;
    children?: Documentation[];
    attachments?: Attachment[];
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    project: Project;
    documentation: Documentation;
    allDocs: Documentation[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Documentations', href: `/projects/${props.project.id}/documentations` },
    { title: props.documentation.title, href: `/projects/${props.project.id}/documentations/${props.documentation.id}` },
];

const copied = ref(false);

const titleStart = computed(() => {
    const words = props.documentation.title.split(' ');
    return words.length > 1 ? words.slice(0, -1).join(' ') + ' ' : '';
});
const titleEnd = computed(() => {
    const words = props.documentation.title.split(' ');
    return words[words.length - 1];
});

const copyLink = () => {
    const route = `/projects/${props.project.id}/documentations/${props.documentation.id}`;
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

const { searchQuery } = useSearch();

const filterChildren = (children: Documentation[]): Documentation[] => {
    if (!searchQuery.value.trim()) return children;
    const query = searchQuery.value.toLowerCase();
    return children.filter(child => {
        if (child.title.toLowerCase().includes(query)) return true;
        if (child.children?.length) return filterChildren(child.children).length > 0;
        return false;
    });
};

const filteredChildren = computed(() => {
    return filterChildren(props.documentation.children ?? []);
});

const filteredGrandchildren = (children: Documentation[]): Documentation[] => {
    return filterChildren(children);
};

const highlightedContent = computed(() => {
    if (!props.documentation.content) return null;
    if (!searchQuery.value.trim()) return props.documentation.content;

    const query = escapeRegExp(searchQuery.value.trim());
    const regex = new RegExp(`(?<=>)([^<]*?)(?=${query})`, 'i');

    // Highlight text nodes only (not inside tags)
    return props.documentation.content.replace(
        new RegExp(`(>)([^<]*?)(<)`, 'g'),
        (match, open, text, close) => {
            const highlighted = text.replace(
                new RegExp(`(${query})`, 'gi'),
                '<mark class="search-highlight">$1</mark>',
            );
            return open + highlighted + close;
        },
    ).replace(
        // Also handle text at the very start (before first tag)
        new RegExp(`^([^<]+)`),
        (text) => text.replace(
            new RegExp(`(${query})`, 'gi'),
            '<mark class="search-highlight">$1</mark>',
        ),
    );
});

const isImage = (mimeType: string): boolean => mimeType.startsWith('image/');

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const nonImageAttachments = (attachments?: Attachment[]) =>
    attachments?.filter(a => !isImage(a.mime_type)) ?? [];

const imageAttachments = (attachments?: Attachment[]) =>
    attachments?.filter(a => isImage(a.mime_type)) ?? [];
</script>

<template>
    <Head :title="documentation.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold tracking-tight">
                    <FileText class="inline-block h-6 w-6 align-text-top text-primary mr-2" />{{ titleStart }}<span class="whitespace-nowrap">{{ titleEnd }}<button
                        @click="copyLink"
                        class="inline-flex align-middle ml-1.5 p-1 rounded-md text-muted-foreground hover:text-primary hover:bg-muted transition-colors cursor-pointer"
                        :title="copied ? 'Copied!' : 'Copy link'"
                    ><Check v-if="copied" class="h-4 w-4 text-green-500" /><Link2 v-else class="h-4 w-4" /></button></span>
                </h1>
                <div class="flex gap-2">
                    <RestrictedAction>
                        <Link :href="`/projects/${project.id}/documentations/${documentation.id}/edit`">
                            <Button variant="outline" class="gap-2">
                                <Edit class="h-4 w-4" />
                                Edit
                            </Button>
                        </Link>
                    </RestrictedAction>
                    <RestrictedAction>
                        <Link
                            :href="`/projects/${project.id}/documentations/${documentation.id}`"
                            method="delete"
                            as="button"
                        >
                            <Button variant="destructive" class="gap-2">
                                <Trash2 class="h-4 w-4" />
                                Delete
                            </Button>
                        </Link>
                    </RestrictedAction>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-4">
                <!-- Sidebar with navigation -->
                <div class="lg:col-span-1">
                    <div class="sticky top-0 rounded-xl border bg-card shadow-sm">
                        <div class="p-3 border-b bg-muted/30">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 text-sm font-medium">
                                    <FolderTree class="h-4 w-4 text-primary" />
                                    <span>Subcategories</span>
                                </div>
                                <RestrictedAction>
                                    <Link :href="`/projects/${project.id}/documentations/create?parent_id=${documentation.id}`">
                                        <Button size="icon-sm" variant="ghost" class="p-0 cursor-pointer h-6 w-6">
                                            <Plus class="h-4 w-4" />
                                        </Button>
                                    </Link>
                                </RestrictedAction>
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
                            <template v-if="filteredChildren.length">
                                <template v-for="child in filteredChildren" :key="child.id">
                                    <div class="group flex items-center justify-between rounded-lg px-3 py-2 cursor-pointer transition-all duration-150 hover:bg-muted/70">
                                        <Link
                                            :href="`/projects/${project.id}/documentations/${child.id}`"
                                            class="flex items-center gap-2 min-w-0 flex-1"
                                        >
                                            <FileText class="h-4 w-4 shrink-0 text-primary" />
                                            <span class="font-medium text-sm truncate">{{ child.title }}</span>
                                        </Link>
                                        <Link
                                            :href="`/projects/${project.id}/documentations/${child.id}`"
                                            @click.stop
                                            class="p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity shrink-0 ml-2 hover:bg-muted"
                                        >
                                            <ExternalLink class="h-3 w-3" />
                                        </Link>
                                    </div>
                                    <!-- Nested children (level 2) -->
                                    <template v-if="child.children?.length">
                                        <template v-for="grandchild in filteredGrandchildren(child.children)" :key="grandchild.id">
                                            <div class="group flex items-center justify-between rounded-lg px-3 py-1.5 ml-4 cursor-pointer transition-all duration-150 hover:bg-muted/70">
                                                <Link
                                                    :href="`/projects/${project.id}/documentations/${grandchild.id}`"
                                                    class="flex items-center gap-2 min-w-0 flex-1"
                                                >
                                                    <ChevronRight class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                    <span class="text-sm truncate">{{ grandchild.title }}</span>
                                                </Link>
                                                <Link
                                                    :href="`/projects/${project.id}/documentations/${grandchild.id}`"
                                                    @click.stop
                                                    class="p-1 rounded opacity-0 group-hover:opacity-100 transition-opacity shrink-0 ml-2 hover:bg-muted"
                                                >
                                                    <ExternalLink class="h-3 w-3" />
                                                </Link>
                                            </div>
                                            <!-- Nested children (level 3) -->
                                            <div
                                                v-for="deep in grandchild.children"
                                                :key="deep.id"
                                                class="group flex items-center justify-between rounded-lg px-3 py-1.5 ml-8 cursor-pointer transition-all duration-150 hover:bg-muted/70"
                                            >
                                                <Link
                                                    :href="`/projects/${project.id}/documentations/${deep.id}`"
                                                    class="flex items-center gap-2 min-w-0 flex-1"
                                                >
                                                    <ChevronRight class="h-3 w-3 shrink-0 text-muted-foreground" />
                                                    <span class="text-xs truncate">{{ deep.title }}</span>
                                                </Link>
                                                <Link
                                                    :href="`/projects/${project.id}/documentations/${deep.id}`"
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
                            <div v-else-if="searchQuery.trim() && documentation.children?.length" class="px-3 py-2 text-sm text-muted-foreground">
                                No matches found
                            </div>
                            <div v-else class="px-3 py-2 text-sm text-muted-foreground">
                                No subcategories
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main content -->
                <div class="lg:col-span-3">
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle>{{ documentation.title }}</CardTitle>
                                <span v-if="documentation.category" class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ documentation.category }}
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="documentation.content" class="prose prose-sm max-w-none" v-html="highlightedContent" />
                            <p v-else class="text-muted-foreground italic">No content yet.</p>
                        </CardContent>
                    </Card>

                    <!-- Attachments -->
                    <Card v-if="documentation.attachments && documentation.attachments.length > 0" class="mt-6">
                        <CardHeader>
                            <CardTitle class="text-base flex items-start gap-2">
                                <Paperclip class="h-4 w-4 shrink-0 mt-0.5" />
                                Attachments
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <!-- Image attachments gallery -->
                            <div v-if="imageAttachments(documentation.attachments).length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-4">
                                <div
                                    v-for="attachment in imageAttachments(documentation.attachments)"
                                    :key="attachment.id"
                                    class="group relative overflow-hidden rounded-lg border border-input hover:border-primary transition-colors"
                                >
                                    <a :href="attachment.url" target="_blank" class="block">
                                        <img
                                            :src="attachment.url"
                                            :alt="attachment.original_filename"
                                            class="aspect-video w-full object-cover"
                                        />
                                    </a>
                                    <div class="flex items-center justify-between p-2">
                                        <span class="text-xs text-muted-foreground truncate">{{ attachment.original_filename }}</span>
                                        <RestrictedAction>
                                            <Link
                                                :href="`/projects/${project.id}/documentations/${documentation.id}/attachments/${attachment.id}`"
                                                method="delete"
                                                as="button"
                                                class="p-1 text-muted-foreground hover:text-destructive cursor-pointer shrink-0"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Link>
                                        </RestrictedAction>
                                    </div>
                                </div>
                            </div>

                            <!-- Non-image attachments -->
                            <div v-if="nonImageAttachments(documentation.attachments).length > 0" class="space-y-2">
                                <div
                                    v-for="attachment in nonImageAttachments(documentation.attachments)"
                                    :key="attachment.id"
                                    class="flex items-center gap-3 rounded-md border border-input p-3"
                                >
                                    <Download class="h-4 w-4 text-muted-foreground shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium truncate">{{ attachment.original_filename }}</p>
                                        <p class="text-xs text-muted-foreground">{{ formatFileSize(attachment.size) }}</p>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <a :href="attachment.url" target="_blank" class="p-1 text-muted-foreground hover:text-foreground">
                                            <Download class="h-4 w-4" />
                                        </a>
                                        <RestrictedAction>
                                            <Link
                                                :href="`/projects/${project.id}/documentations/${documentation.id}/attachments/${attachment.id}`"
                                                method="delete"
                                                as="button"
                                                class="p-1 text-muted-foreground hover:text-destructive cursor-pointer"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Link>
                                        </RestrictedAction>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Child documents -->
                    <div v-if="documentation.children && documentation.children.length > 0" class="mt-6">
                        <h3 class="text-lg font-semibold mb-4">Related Documents</h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <Card v-for="child in documentation.children" :key="child.id" class="hover:border-primary transition-colors cursor-pointer">
                                <CardHeader class="pb-2">
                                    <Link :href="`/projects/${project.id}/documentations/${child.id}`" class="cursor-pointer">
                                        <CardTitle class="text-base flex items-start gap-2">
                                            <FileText class="h-4 w-4 shrink-0 mt-0.5 text-primary" />
                                            {{ child.title }}
                                        </CardTitle>
                                    </Link>
                                </CardHeader>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.prose img {
    max-width: 100%;
    height: auto;
    border-radius: 0.375rem;
}

.prose a {
    color: var(--primary);
    text-decoration: underline;
}

.prose h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.prose h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 0.75rem;
    margin-bottom: 0.5rem;
}

.prose h3 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 0.5rem;
    margin-bottom: 0.25rem;
}

.prose ul {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin: 0.5rem 0;
}

.prose ol {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin: 0.5rem 0;
}

.prose blockquote {
    border-left: 3px solid var(--border);
    padding-left: 1rem;
    margin: 0.5rem 0;
    color: var(--muted-foreground);
}

.prose code {
    background: var(--muted);
    border-radius: 0.25rem;
    padding: 0.125rem 0.375rem;
    font-size: 0.875rem;
    font-family: ui-monospace, monospace;
}

.prose pre {
    background: var(--muted);
    border-radius: 0.375rem;
    padding: 0.75rem 1rem;
    margin: 0.5rem 0;
    overflow-x: auto;
}

.prose pre code {
    background: transparent;
    padding: 0;
}

.prose hr {
    border-color: var(--border);
    margin: 1rem 0;
}

.prose p {
    margin: 0.25rem 0;
}

.search-highlight {
    background-color: rgb(147 197 253 / 0.5);
    border-radius: 0.125rem;
    padding: 0.0625rem 0.125rem;
}
</style>
