<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FileText,
    Plus,
    Search,
    X,
    FolderTree,
    ExternalLink,
    ChevronRight,
    GripVertical,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useSearch } from '@/composables/useSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';

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
    {
        title: 'Documentations',
        href: `/projects/${props.project.id}/documentations`,
    },
];

const { searchQuery, highlight } = useSearch();

// Local state for drag-and-drop
const localDocs = ref<Documentation[]>([...props.documentations]);

watch(
    () => props.documentations,
    (val) => {
        localDocs.value = [...val];
    },
);

const filterDocs = (docs: Documentation[]): Documentation[] => {
    if (!searchQuery.value.trim()) return docs;
    const query = searchQuery.value.toLowerCase();
    return docs.filter((doc) => {
        const matchesSelf =
            doc.title.toLowerCase().includes(query) ||
            doc.content?.toLowerCase().includes(query);
        const matchingChildren = doc.children ? filterDocs(doc.children) : [];
        return matchesSelf || matchingChildren.length > 0;
    });
};

const filteredDocs = computed(() => filterDocs(localDocs.value));

const filteredChildren = (children: Documentation[]): Documentation[] =>
    filterDocs(children);

// Drag-and-drop
const canDrag = computed(() => !searchQuery.value.trim());
const draggedDoc = ref<Documentation | null>(null);
const dragOverDocId = ref<number | null>(null);
const isDragging = ref(false);

const onDragStart = (e: DragEvent, doc: Documentation) => {
    if (!canDrag.value) return;
    draggedDoc.value = doc;
    isDragging.value = true;
    e.dataTransfer!.effectAllowed = 'move';
    e.dataTransfer!.setData('text/plain', String(doc.id));
    const rowEl = (e.currentTarget as HTMLElement).parentElement;
    if (rowEl) {
        e.dataTransfer!.setDragImage(rowEl, 0, 0);
    }
};

const onDragEnd = () => {
    draggedDoc.value = null;
    dragOverDocId.value = null;
    isDragging.value = false;
};

const onDragOverDoc = (e: DragEvent, targetDoc: Documentation) => {
    e.preventDefault();
    e.dataTransfer!.dropEffect = 'move';
    dragOverDocId.value = targetDoc.id;
};

const onDragLeaveDoc = (e: DragEvent, targetDoc: Documentation) => {
    if (dragOverDocId.value === targetDoc.id) {
        dragOverDocId.value = null;
    }
};

const onDropOnDoc = (
    e: DragEvent,
    targetDoc: Documentation,
    parentId: number | null,
) => {
    e.preventDefault();
    if (!draggedDoc.value || draggedDoc.value.id === targetDoc.id) {
        onDragEnd();
        return;
    }

    const dragged = draggedDoc.value;

    const isRootDrag = localDocs.value.some((d) => d.id === dragged.id);

    if (parentId === null && isRootDrag) {
        // Root doc dropped on another root doc — reorder among roots
        const list = [...localDocs.value];
        const dragIdx = list.findIndex((d) => d.id === dragged.id);
        list.splice(dragIdx, 1);
        const targetIdx = list.findIndex((d) => d.id === targetDoc.id);
        list.splice(targetIdx, 0, dragged);
        list.forEach((d, i) => {
            d.order = i;
        });
        localDocs.value = list;
    } else if (parentId === null && !isRootDrag) {
        // Child dropped on a root doc — move as child of that root doc
        for (const doc of localDocs.value) {
            if (doc.children) {
                const childIdx = doc.children.findIndex(
                    (c) => c.id === dragged.id,
                );
                if (childIdx !== -1) {
                    doc.children.splice(childIdx, 1);
                    break;
                }
            }
        }
        if (!targetDoc.children) targetDoc.children = [];
        dragged.order = targetDoc.children.length;
        targetDoc.children.push(dragged);
    } else {
        // Target is a child — reorder within parent or move into parent
        const parentDoc = localDocs.value.find((d) => d.id === parentId);
        if (!parentDoc || !parentDoc.children) {
            onDragEnd();
            return;
        }

        // Remove dragged from wherever it is
        const rootIdx = localDocs.value.findIndex((d) => d.id === dragged.id);
        if (rootIdx !== -1) {
            localDocs.value.splice(rootIdx, 1);
            localDocs.value.forEach((d, i) => {
                d.order = i;
            });
        } else {
            for (const doc of localDocs.value) {
                if (doc.children) {
                    const childIdx = doc.children.findIndex(
                        (c) => c.id === dragged.id,
                    );
                    if (childIdx !== -1) {
                        doc.children.splice(childIdx, 1);
                        break;
                    }
                }
            }
        }

        // Insert before target in parent's children
        const targetIdx = parentDoc.children.findIndex(
            (c) => c.id === targetDoc.id,
        );
        parentDoc.children.splice(targetIdx, 0, dragged);
        parentDoc.children.forEach((c, i) => {
            c.order = i;
        });
    }

    saveReorder();
    onDragEnd();
};

const onDropOnParent = (e: DragEvent, parentDoc: Documentation) => {
    e.preventDefault();
    if (!draggedDoc.value || draggedDoc.value.id === parentDoc.id) {
        onDragEnd();
        return;
    }

    const dragged = draggedDoc.value;

    // Remove from wherever it currently is
    const rootIdx = localDocs.value.findIndex((d) => d.id === dragged.id);
    if (rootIdx !== -1) {
        localDocs.value.splice(rootIdx, 1);
        localDocs.value.forEach((d, i) => {
            d.order = i;
        });
    } else {
        for (const doc of localDocs.value) {
            if (doc.children) {
                const childIdx = doc.children.findIndex(
                    (c) => c.id === dragged.id,
                );
                if (childIdx !== -1) {
                    doc.children.splice(childIdx, 1);
                    break;
                }
            }
        }
    }

    // Add as last child of parentDoc
    if (!parentDoc.children) parentDoc.children = [];
    dragged.order = parentDoc.children.length;
    parentDoc.children.push(dragged);

    saveReorder();
    onDragEnd();
};

const saveReorder = () => {
    const items: { id: number; order: number; parent_id: number | null }[] = [];

    localDocs.value.forEach((doc, i) => {
        items.push({ id: doc.id, order: i, parent_id: null });
        if (doc.children) {
            doc.children.forEach((child, j) => {
                items.push({ id: child.id, order: j, parent_id: doc.id });
            });
        }
    });

    router.post(
        `/projects/${props.project.id}/documentations/reorder`,
        { items },
        { preserveScroll: true, preserveState: true },
    );
};

const highlightDescription = (content: string): string => {
    const plain =
        content
            .replace(/<[^>]*>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .substring(0, 200) + '...';
    return highlight(plain);
};
</script>

<template>
    <Head title="Documentations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1
                    class="flex items-start gap-2 text-2xl font-bold tracking-tight"
                >
                    <FileText class="mt-1 h-6 w-6 shrink-0 text-primary" />
                    Documentations
                </h1>
                <RestrictedAction>
                    <Link
                        :href="`/projects/${project.id}/documentations/create`"
                    >
                        <Button variant="cta" class="cursor-pointer gap-2">
                            <Plus class="h-4 w-4" />
                            Documentation
                        </Button>
                    </Link>
                </RestrictedAction>
            </div>

            <div
                v-if="documentations.length === 0"
                class="flex flex-1 items-center justify-center"
            >
                <div class="text-center">
                    <FileText class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">
                        No documentations yet
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Create your first documentation page.
                    </p>
                    <RestrictedAction>
                        <Link
                            :href="`/projects/${project.id}/documentations/create`"
                            class="mt-4 inline-block"
                        >
                            <Button variant="cta" class="cursor-pointer gap-2">
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
                    <div class="sticky top-6 self-start lg:col-span-1">
                        <div class="rounded-xl border bg-card shadow-sm">
                            <div class="border-b bg-muted/30 p-3">
                                <div
                                    class="flex items-center gap-2 text-sm font-medium"
                                >
                                    <FolderTree class="h-4 w-4 text-primary" />
                                    <span>Documents</span>
                                </div>
                                <div class="relative mt-2">
                                    <Search
                                        class="absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Search..."
                                        class="h-8 bg-background/60 pr-7 pl-7 text-xs"
                                    />
                                    <button
                                        v-if="searchQuery"
                                        @click="searchQuery = ''"
                                        class="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div
                                class="max-h-[calc(100vh-270px)] space-y-0.5 overflow-y-auto p-2"
                            >
                                <template v-if="filteredDocs.length">
                                    <template
                                        v-for="doc in filteredDocs"
                                        :key="doc.id"
                                    >
                                        <div
                                            class="group flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 transition-all duration-150 hover:bg-muted/70"
                                            :class="{
                                                'opacity-50':
                                                    isDragging &&
                                                    draggedDoc?.id === doc.id,
                                                'bg-primary/5 ring-2 ring-primary':
                                                    isDragging &&
                                                    dragOverDocId === doc.id &&
                                                    draggedDoc?.id !== doc.id,
                                            }"
                                            @dragover="
                                                onDragOverDoc($event, doc)
                                            "
                                            @dragleave="
                                                onDragLeaveDoc($event, doc)
                                            "
                                            @drop="
                                                onDropOnDoc($event, doc, null)
                                            "
                                        >
                                            <div
                                                v-if="canDrag"
                                                draggable="true"
                                                class="mr-1 shrink-0 cursor-grab opacity-0 transition-opacity group-hover:opacity-100 active:cursor-grabbing"
                                                @dragstart="
                                                    onDragStart($event, doc)
                                                "
                                                @dragend="onDragEnd"
                                            >
                                                <GripVertical
                                                    class="h-3.5 w-3.5 text-muted-foreground"
                                                />
                                            </div>
                                            <Link
                                                :href="`/projects/${project.id}/documentations/${doc.id}`"
                                                class="flex min-w-0 flex-1 items-center gap-2"
                                            >
                                                <FileText
                                                    class="h-4 w-4 shrink-0 text-primary"
                                                />
                                                <span
                                                    class="truncate text-sm font-medium"
                                                    >{{ doc.title }}</span
                                                >
                                            </Link>
                                            <Link
                                                :href="`/projects/${project.id}/documentations/${doc.id}`"
                                                @click.stop
                                                class="ml-2 shrink-0 rounded p-1 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-muted"
                                            >
                                                <ExternalLink class="h-3 w-3" />
                                            </Link>
                                        </div>
                                        <!-- Nested children -->
                                        <template v-if="doc.children?.length">
                                            <template
                                                v-for="child in filteredChildren(
                                                    doc.children,
                                                )"
                                                :key="child.id"
                                            >
                                                <div
                                                    class="group ml-4 flex cursor-pointer items-center justify-between rounded-lg px-3 py-1.5 transition-all duration-150 hover:bg-muted/70"
                                                    :class="{
                                                        'opacity-50':
                                                            isDragging &&
                                                            draggedDoc?.id ===
                                                                child.id,
                                                        'bg-primary/5 ring-2 ring-primary':
                                                            isDragging &&
                                                            dragOverDocId ===
                                                                child.id &&
                                                            draggedDoc?.id !==
                                                                child.id,
                                                    }"
                                                    @dragover="
                                                        onDragOverDoc(
                                                            $event,
                                                            child,
                                                        )
                                                    "
                                                    @dragleave="
                                                        onDragLeaveDoc(
                                                            $event,
                                                            child,
                                                        )
                                                    "
                                                    @drop="
                                                        onDropOnDoc(
                                                            $event,
                                                            child,
                                                            doc.id,
                                                        )
                                                    "
                                                >
                                                    <div
                                                        v-if="canDrag"
                                                        draggable="true"
                                                        class="mr-1 shrink-0 cursor-grab opacity-0 transition-opacity group-hover:opacity-100 active:cursor-grabbing"
                                                        @dragstart="
                                                            onDragStart(
                                                                $event,
                                                                child,
                                                            )
                                                        "
                                                        @dragend="onDragEnd"
                                                    >
                                                        <GripVertical
                                                            class="h-3 w-3 text-muted-foreground"
                                                        />
                                                    </div>
                                                    <Link
                                                        :href="`/projects/${project.id}/documentations/${child.id}`"
                                                        class="flex min-w-0 flex-1 items-center gap-2"
                                                    >
                                                        <ChevronRight
                                                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                                                        />
                                                        <span
                                                            class="truncate text-sm"
                                                            >{{
                                                                child.title
                                                            }}</span
                                                        >
                                                    </Link>
                                                    <Link
                                                        :href="`/projects/${project.id}/documentations/${child.id}`"
                                                        @click.stop
                                                        class="ml-2 shrink-0 rounded p-1 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-muted"
                                                    >
                                                        <ExternalLink
                                                            class="h-3 w-3"
                                                        />
                                                    </Link>
                                                </div>
                                            </template>
                                        </template>
                                    </template>
                                </template>
                                <div
                                    v-else-if="searchQuery.trim()"
                                    class="px-3 py-2 text-sm text-muted-foreground"
                                >
                                    No matches found
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main content -->
                    <div class="space-y-4 lg:col-span-3">
                        <div
                            v-if="filteredDocs.length === 0"
                            class="flex flex-col items-center justify-center py-12"
                        >
                            <FileText
                                class="mb-4 h-12 w-12 text-muted-foreground/50"
                            />
                            <p class="text-muted-foreground">
                                No documentations match your search.
                            </p>
                        </div>

                        <div
                            v-for="doc in filteredDocs"
                            :key="doc.id"
                            class="block cursor-pointer"
                            @click="
                                router.visit(
                                    `/projects/${project.id}/documentations/${doc.id}`,
                                )
                            "
                        >
                            <Card
                                class="transition-colors hover:border-primary"
                            >
                                <CardHeader class="pb-2">
                                    <div
                                        class="flex items-start justify-between"
                                    >
                                        <CardTitle
                                            class="flex items-start gap-2 text-lg"
                                        >
                                            <FileText
                                                class="mt-1 h-4 w-4 shrink-0 text-primary"
                                            />
                                            <span
                                                v-html="highlight(doc.title)"
                                            />
                                        </CardTitle>
                                        <span
                                            v-if="doc.category"
                                            class="rounded bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800"
                                        >
                                            {{ doc.category }}
                                        </span>
                                    </div>
                                    <CardDescription
                                        v-if="doc.content"
                                        class="line-clamp-2"
                                        v-html="
                                            highlightDescription(doc.content)
                                        "
                                    />
                                </CardHeader>
                                <CardContent
                                    v-if="
                                        doc.children && doc.children.length > 0
                                    "
                                >
                                    <div class="flex flex-wrap gap-1.5">
                                        <Link
                                            v-for="child in doc.children"
                                            :key="child.id"
                                            :href="`/projects/${project.id}/documentations/${child.id}`"
                                            class="cursor-pointer rounded-md bg-muted/60 px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                                            @click.stop
                                        >
                                            <span
                                                v-html="highlight(child.title)"
                                            />
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
