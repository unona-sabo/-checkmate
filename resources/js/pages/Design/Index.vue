<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
import InputError from '@/components/InputError.vue';
import RestrictedAction from '@/components/RestrictedAction.vue';
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
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

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
    const textArea = document.createElement('textarea');
    textArea.value = link.url;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
    copiedLinkId.value = link.id;
    setTimeout(() => { copiedLinkId.value = null; }, 2000);
};

// Search
const searchQuery = ref('');

const filteredLinks = computed(() => {
    if (!searchQuery.value.trim()) return props.designLinks;
    const q = searchQuery.value.toLowerCase();
    return props.designLinks.filter(
        (link) =>
            link.title.toLowerCase().includes(q) ||
            (link.description && link.description.toLowerCase().includes(q)) ||
            link.url.toLowerCase().includes(q) ||
            (link.category && link.category.toLowerCase().includes(q)),
    );
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
        form.put(`/projects/${props.project.id}/design/${editingLink.value.id}`, {
            onSuccess: () => {
                showFormDialog.value = false;
                editingLink.value = null;
            },
        });
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
    router.delete(`/projects/${props.project.id}/design/${linkToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteConfirm.value = false;
            linkToDelete.value = null;
        },
    });
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
                <h1 class="flex items-start gap-2 text-2xl font-bold tracking-tight">
                    <Palette class="mt-1 h-6 w-6 shrink-0 text-primary" />
                    Design Resources
                </h1>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search links..."
                            class="w-48 pl-9 pr-8 bg-background/60"
                        />
                        <button
                            v-if="searchQuery"
                            class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                            @click="searchQuery = ''"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <RestrictedAction>
                        <Button variant="cta" class="gap-2 cursor-pointer" @click="openAddDialog">
                            <Plus class="h-4 w-4" />
                            Add Link
                        </Button>
                    </RestrictedAction>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-if="designLinks.length === 0"
                class="flex flex-1 items-center justify-center"
            >
                <div class="text-center">
                    <Palette class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No design links yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Add links to your Figma files, prototypes, and other design resources.
                    </p>
                    <RestrictedAction>
                        <Button variant="cta" class="mt-4 gap-2 cursor-pointer" @click="openAddDialog">
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
                    <h3 class="mt-4 text-lg font-semibold">No matching links</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        No design links match "{{ searchQuery }}".
                    </p>
                    <Button
                        variant="secondary"
                        class="mt-4 cursor-pointer"
                        @click="searchQuery = ''"
                    >
                        Clear search
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
                                        :style="{ color: link.color || 'hsl(var(--muted-foreground))' }"
                                    />
                                </div>
                                <div>
                                    <CardTitle class="text-base">
                                        {{ link.title }}
                                    </CardTitle>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
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

                        <div class="mt-auto flex items-center justify-between pt-3">
                            <div class="flex gap-1">
                                <a :href="link.url" target="_blank" rel="noopener noreferrer">
                                    <Button variant="outline" size="sm" class="gap-1.5 cursor-pointer">
                                        Open
                                        <ExternalLink class="h-3 w-3" />
                                    </Button>
                                </a>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="cursor-pointer"
                                    :title="copiedLinkId === link.id ? 'Copied!' : 'Copy link'"
                                    @click="copyToClipboard(link)"
                                >
                                    <Check v-if="copiedLinkId === link.id" class="h-4 w-4 text-green-500" />
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
                            {{ editingLink ? 'Edit Design Link' : 'Add Design Link' }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ editingLink ? 'Update the design resource link.' : 'Add an external design resource link.' }}
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
                                        <SelectValue placeholder="Select icon" />
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
                                        @input="form.color = ($event.target as HTMLInputElement).value"
                                    />
                                </div>
                                <InputError :message="form.errors.color" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Category</Label>
                            <Select v-model="form.category">
                                <SelectTrigger class="cursor-pointer">
                                    <SelectValue placeholder="Select category" />
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
                            Are you sure you want to delete "{{ linkToDelete?.title }}"? This action cannot be undone.
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
