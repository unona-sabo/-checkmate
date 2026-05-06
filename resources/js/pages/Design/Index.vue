<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Palette,
    Plus,
    Search,
    X,
    ExternalLink,
    Copy,
    Check,
    Edit,
    Trash2,
    Figma,
    Layers,
    Monitor,
    FileDown,
    Link2,
    Globe,
    Filter,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import RestrictedAction from '@/components/RestrictedAction.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { writeToClipboard } from '@/composables/useClipboard';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClearErrorsOnInput } from '@/composables/useClearErrorsOnInput';

interface DesignLink {
    id: number;
    project_id: number;
    title: string;
    url: string;
    icon: string | null;
    color: string | null;
    description: string | null;
    category: string | null;
    created_by: number | null;
    creator: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    project: Project;
    designLinks: DesignLink[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Design', href: `/projects/${props.project.id}/design` },
];

const iconComponents: Record<string, typeof Figma> = {
    figma: Figma,
    zeplin: Layers,
    invision: Monitor,
    pdf: FileDown,
    link: Link2,
};

const getIconComponent = (icon: string | null) => {
    if (icon && iconComponents[icon]) {
        return iconComponents[icon];
    }
    return Globe;
};

const getDomain = (url: string): string => {
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
};

const copiedLinkId = ref<number | null>(null);

const copyToClipboard = (link: DesignLink) => {
    writeToClipboard(link.url).then(() => {
        copiedLinkId.value = link.id;
        setTimeout(() => {
            copiedLinkId.value = null;
        }, 2000);
    });
};

// Search & Filters
const searchQuery = ref('');
const showFilters = ref(false);
const filterCategory = ref('');
const filterCreatedFrom = ref('');
const filterCreatedTo = ref('');

const activeFilterCount = computed(() => {
    return (
        [filterCreatedFrom, filterCreatedTo].filter((f) => f.value !== '')
            .length +
        (filterCategory.value && filterCategory.value !== 'all' ? 1 : 0)
    );
});

const clearFilters = () => {
    filterCategory.value = 'all';
    filterCreatedFrom.value = '';
    filterCreatedTo.value = '';
};

const uniqueCategories = computed(() => {
    const cats = new Set<string>();
    props.designLinks.forEach((link) => {
        if (link.category) cats.add(link.category);
    });
    return Array.from(cats).sort();
});

const filteredLinks = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const hasSearch = q.length > 0;
    const hasFilters = activeFilterCount.value > 0;

    if (!hasSearch && !hasFilters) return props.designLinks;

    return props.designLinks.filter((link) => {
        if (
            hasSearch &&
            !link.title.toLowerCase().includes(q) &&
            !(link.description && link.description.toLowerCase().includes(q)) &&
            !link.url.toLowerCase().includes(q) &&
            !(link.category && link.category.toLowerCase().includes(q))
        )
            return false;
        if (
            filterCategory.value &&
            filterCategory.value !== 'all' &&
            link.category !== filterCategory.value
        )
            return false;
        if (
            filterCreatedFrom.value &&
            link.created_at < filterCreatedFrom.value
        )
            return false;
        if (
            filterCreatedTo.value &&
            link.created_at.slice(0, 10) > filterCreatedTo.value
        )
            return false;
        return true;
    });
});

// Add/Edit Dialog
const showFormDialog = ref(false);
const editingLink = ref<DesignLink | null>(null);

const form = useForm({
    title: '',
    url: '',
    icon: '' as string,
    color: '',
    description: '',
    category: '' as string,
});
useClearErrorsOnInput(form);

watch(showFormDialog, (open) => {
    if (!open) {
        form.clearErrors();
    }
});

const openAddDialog = () => {
    editingLink.value = null;
    form.reset();
    form.clearErrors();
    showFormDialog.value = true;
};

const openEditDialog = (link: DesignLink) => {
    editingLink.value = link;
    form.title = link.title;
    form.url = link.url;
    form.icon = link.icon || '';
    form.color = link.color || '';
    form.description = link.description || '';
    form.category = link.category || '';
    form.clearErrors();
    showFormDialog.value = true;
};

const submitForm = () => {
    if (editingLink.value) {
        form.put(
            `/projects/${props.project.id}/design/${editingLink.value.id}`,
            {
                onSuccess: () => {
                    showFormDialog.value = false;
                    editingLink.value = null;
                },
            },
        );
    } else {
        form.post(`/projects/${props.project.id}/design`, {
            onSuccess: () => {
                showFormDialog.value = false;
                form.reset();
            },
        });
    }
};

// Delete Dialog
const showDeleteConfirm = ref(false);
const linkToDelete = ref<DesignLink | null>(null);

const confirmDelete = (link: DesignLink) => {
    linkToDelete.value = link;
    showDeleteConfirm.value = true;
};

const deleteLink = () => {
    if (!linkToDelete.value) return;
    router.delete(
        `/projects/${props.project.id}/design/${linkToDelete.value.id}`,
        {
            onSuccess: () => {
                showDeleteConfirm.value = false;
                linkToDelete.value = null;
            },
        },
    );
};

const iconOptions = [
    { value: 'figma', label: 'Figma' },
    { value: 'zeplin', label: 'Zeplin' },
    { value: 'invision', label: 'InVision' },
    { value: 'pdf', label: 'PDF' },
    { value: 'link', label: 'Link' },
    { value: 'globe', label: 'Globe' },
];

const categoryOptions = [
    { value: 'Figma', label: 'Figma' },
    { value: 'Mockups', label: 'Mockups' },
    { value: 'Assets', label: 'Assets' },
    { value: 'Guidelines', label: 'Guidelines' },
];
</script>

<template>
    <Head title="Design" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1
                    class="flex items-start gap-2 text-2xl font-bold tracking-tight"
                >
                    <Palette class="mt-1 h-6 w-6 shrink-0 text-primary" />
                    Design Resources
                </h1>
                <div class="flex items-center gap-3">
                    <template v-if="designLinks.length > 0">
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search links..."
                                class="w-48 bg-background/60 pr-8 pl-9"
                            />
                            <button
                                v-if="searchQuery"
                                class="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                                @click="searchQuery = ''"
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
                        <Button
                            variant="cta"
                            class="cursor-pointer gap-2"
                            @click="openAddDialog"
                        >
                            <Plus class="h-4 w-4" />
                            Add Link
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
                            Filters
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
                    <!-- Row 1: Category -->
                    <div class="grid grid-cols-3 gap-x-3 gap-y-2.5">
                        <div class="relative">
                            <Label
                                class="mb-1 block text-[11px] text-muted-foreground"
                                >Category</Label
                            >
                            <Select v-model="filterCategory">
                                <SelectTrigger
                                    class="h-8 cursor-pointer text-xs"
                                >
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem
                                        v-for="cat in uniqueCategories"
                                        :key="cat"
                                        :value="cat"
                                        >{{ cat }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <button
                                v-if="
                                    filterCategory && filterCategory !== 'all'
                                "
                                @click="filterCategory = 'all'"
                                class="absolute right-1.5 bottom-1.5 z-10 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>
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
                    <!-- Results count -->
                    <div class="mt-3 flex items-center justify-end">
                        <span class="text-sm text-muted-foreground">
                            Found
                            <span class="font-semibold text-foreground">{{
                                filteredLinks.length
                            }}</span>
                            {{ filteredLinks.length === 1 ? 'link' : 'links' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-if="designLinks.length === 0"
                class="flex flex-1 items-center justify-center"
            >
                <div class="text-center">
                    <Palette class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">
                        No design links yet
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Add links to your Figma files, prototypes, and other
                        design resources.
                    </p>
                    <RestrictedAction>
                        <Button
                            variant="cta"
                            class="mt-4 cursor-pointer gap-2"
                            @click="openAddDialog"
                        >
                            <Plus class="h-4 w-4" />
                            Add Link
                        </Button>
                    </RestrictedAction>
                </div>
            </div>

            <!-- No results state -->
            <div
                v-else-if="filteredLinks.length === 0"
                class="flex flex-1 items-center justify-center"
            >
                <div class="text-center">
                    <Search class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">
                        No matching links
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        No design links match "{{ searchQuery }}".
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-4 cursor-pointer gap-2"
                        @click="searchQuery = ''"
                    >
                        <X class="h-3.5 w-3.5" />
                        Clear Search
                    </Button>
                </div>
            </div>

            <!-- Card grid -->
            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="link in filteredLinks"
                    :key="link.id"
                    class="flex flex-col transition-colors hover:border-primary"
                >
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-3">
                                <div
                                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                    :style="{
                                        backgroundColor: link.color
                                            ? link.color + '20'
                                            : 'hsl(var(--muted))',
                                    }"
                                >
                                    <component
                                        :is="getIconComponent(link.icon)"
                                        class="h-4 w-4"
                                        :style="{
                                            color:
                                                link.color ||
                                                'hsl(var(--muted-foreground))',
                                        }"
                                    />
                                </div>
                                <div>
                                    <CardTitle class="text-base">
                                        {{ link.title }}
                                    </CardTitle>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ getDomain(link.url) }}
                                    </p>
                                </div>
                            </div>
                            <Badge v-if="link.category" variant="secondary">
                                {{ link.category }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col">
                        <p
                            v-if="link.description"
                            class="mb-3 line-clamp-2 text-sm text-muted-foreground"
                        >
                            {{ link.description }}
                        </p>

                        <div
                            class="mt-auto flex items-center justify-between pt-3"
                        >
                            <div class="flex gap-1">
                                <a
                                    :href="link.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="cursor-pointer gap-1.5"
                                    >
                                        Open
                                        <ExternalLink class="h-3 w-3" />
                                    </Button>
                                </a>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="cursor-pointer"
                                    :title="
                                        copiedLinkId === link.id
                                            ? 'Copied!'
                                            : 'Copy link'
                                    "
                                    @click="copyToClipboard(link)"
                                >
                                    <Check
                                        v-if="copiedLinkId === link.id"
                                        class="h-4 w-4 text-green-500"
                                    />
                                    <Copy v-else class="h-4 w-4" />
                                </Button>
                            </div>
                            <div class="flex gap-1">
                                <RestrictedAction>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="cursor-pointer"
                                        @click="openEditDialog(link)"
                                    >
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                </RestrictedAction>
                                <RestrictedAction>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        class="cursor-pointer text-destructive hover:text-destructive"
                                        @click="confirmDelete(link)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </RestrictedAction>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Add/Edit Dialog -->
            <Dialog v-model:open="showFormDialog">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editingLink
                                    ? 'Edit Design Link'
                                    : 'Add Design Link'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                editingLink
                                    ? 'Update the design resource link.'
                                    : 'Add an external design resource link.'
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitForm">
                        <div class="space-y-2">
                            <Label for="title">Title</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                placeholder="e.g. Main Figma File"
                            />
                            <InputError :message="form.errors.title" />
                        </div>
                        <div class="space-y-2">
                            <Label for="url">URL</Label>
                            <Input
                                id="url"
                                v-model="form.url"
                                type="url"
                                placeholder="https://..."
                            />
                            <InputError :message="form.errors.url" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Icon</Label>
                                <Select v-model="form.icon">
                                    <SelectTrigger class="cursor-pointer">
                                        <SelectValue
                                            placeholder="Select icon"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="opt in iconOptions"
                                            :key="opt.value"
                                            :value="opt.value"
                                            class="cursor-pointer"
                                        >
                                            {{ opt.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.icon" />
                            </div>
                            <div class="space-y-2">
                                <Label for="color">Color</Label>
                                <div class="flex gap-2">
                                    <Input
                                        id="color"
                                        v-model="form.color"
                                        placeholder="#F24E1E"
                                        maxlength="7"
                                        class="flex-1"
                                    />
                                    <input
                                        type="color"
                                        :value="form.color || '#6366f1'"
                                        class="h-9 w-9 shrink-0 cursor-pointer rounded border"
                                        @input="
                                            form.color = (
                                                $event.target as HTMLInputElement
                                            ).value
                                        "
                                    />
                                </div>
                                <InputError :message="form.errors.color" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Category</Label>
                            <Select v-model="form.category">
                                <SelectTrigger class="cursor-pointer">
                                    <SelectValue
                                        placeholder="Select category"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="opt in categoryOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                        class="cursor-pointer"
                                    >
                                        {{ opt.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.category" />
                        </div>
                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Optional description..."
                                rows="2"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                        <DialogFooter class="flex gap-4 sm:justify-end">
                            <Button
                                type="button"
                                variant="secondary"
                                class="flex-1 cursor-pointer sm:flex-none"
                                @click="showFormDialog = false"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="cta"
                                class="flex-1 cursor-pointer sm:flex-none"
                                :disabled="form.processing"
                            >
                                {{ editingLink ? 'Update' : 'Add Link' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Dialog -->
            <Dialog v-model:open="showDeleteConfirm">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Design Link?</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete "{{
                                linkToDelete?.title
                            }}"? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="flex gap-4 sm:justify-end">
                        <Button
                            variant="secondary"
                            class="flex-1 cursor-pointer sm:flex-none"
                            @click="showDeleteConfirm = false"
                        >
                            No
                        </Button>
                        <Button
                            variant="destructive"
                            class="flex-1 cursor-pointer sm:flex-none"
                            @click="deleteLink"
                        >
                            Yes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
