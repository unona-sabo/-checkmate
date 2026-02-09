<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type Checklist, type ColumnConfig, type SelectOption } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { Edit, Plus, Trash2, ChevronDown, ChevronUp, Palette } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    project: Project;
    checklist: Checklist;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Checklists', href: `/projects/${props.project.id}/checklists` },
    { title: props.checklist.name, href: `/projects/${props.project.id}/checklists/${props.checklist.id}` },
    { title: 'Edit', href: `/projects/${props.project.id}/checklists/${props.checklist.id}/edit` },
];

const form = useForm({
    name: props.checklist.name,
    columns_config: props.checklist.columns_config || [
        { key: 'item', label: 'Item', type: 'text' as const },
        { key: 'status', label: 'Status', type: 'checkbox' as const },
    ] as ColumnConfig[],
});

const showDeleteDialog = ref(false);
const expandedColumns = ref<Record<number, boolean>>({});

const predefinedColors = [
    { name: 'Red', value: '#fee2e2', text: '#dc2626' },
    { name: 'Orange', value: '#ffedd5', text: '#ea580c' },
    { name: 'Yellow', value: '#fef9c3', text: '#ca8a04' },
    { name: 'Green', value: '#dcfce7', text: '#16a34a' },
    { name: 'Blue', value: '#dbeafe', text: '#2563eb' },
    { name: 'Purple', value: '#f3e8ff', text: '#9333ea' },
    { name: 'Pink', value: '#fce7f3', text: '#db2777' },
    { name: 'Gray', value: '#f3f4f6', text: '#4b5563' },
];

const toggleColumnExpand = (index: number) => {
    expandedColumns.value[index] = !expandedColumns.value[index];
};

const addColumn = () => {
    const newIndex = form.columns_config.length;
    form.columns_config.push({
        key: `column_${Date.now()}`,
        label: '',
        type: 'text',
    });
    expandedColumns.value[newIndex] = true;
};

const removeColumn = (index: number) => {
    form.columns_config.splice(index, 1);
};

const addOption = (column: ColumnConfig) => {
    if (!column.options) {
        column.options = [];
    }
    column.options.push({
        value: `option_${Date.now()}`,
        label: '',
        color: '#dbeafe',
    });
};

const removeOption = (column: ColumnConfig, optionIndex: number) => {
    if (column.options) {
        column.options.splice(optionIndex, 1);
    }
};

const setOptionColor = (option: SelectOption, color: string) => {
    option.color = color;
};

// Initialize options array when type changes to select
watch(() => form.columns_config, (columns) => {
    columns.forEach(col => {
        if (col.type === 'select' && !col.options) {
            col.options = [];
        }
    });
}, { deep: true });

const submit = () => {
    form.put(`/projects/${props.project.id}/checklists/${props.checklist.id}`);
};

const deleteChecklist = () => {
    router.delete(`/projects/${props.project.id}/checklists/${props.checklist.id}`);
};
</script>

<template>
    <Head title="Edit Checklist" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Edit class="h-5 w-5 text-primary" />
                            Edit Checklist
                        </CardTitle>
                        <CardDescription>
                            Update checklist settings and columns.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="space-y-2">
                                <Label for="name">Checklist Name</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    :class="{ 'border-destructive': form.errors.name }"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>Columns</Label>
                                    <Button type="button" variant="outline" size="sm" @click="addColumn" class="gap-1">
                                        <Plus class="h-3 w-3" />
                                        Add Column
                                    </Button>
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="(column, index) in form.columns_config"
                                        :key="index"
                                        class="rounded-lg border"
                                    >
                                        <div class="flex items-center gap-2 p-3">
                                            <div class="flex-1">
                                                <Input v-model="column.label" placeholder="Column name" />
                                            </div>
                                            <Select v-model="column.type">
                                                <SelectTrigger class="w-32">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="text">Text</SelectItem>
                                                    <SelectItem value="checkbox">Checkbox</SelectItem>
                                                    <SelectItem value="select">Select</SelectItem>
                                                    <SelectItem value="date">Date</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <Button
                                                v-if="column.type === 'select'"
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click="toggleColumnExpand(index)"
                                                class="h-8 w-8 p-0"
                                            >
                                                <ChevronDown v-if="!expandedColumns[index]" class="h-4 w-4" />
                                                <ChevronUp v-else class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                v-if="form.columns_config.length > 1"
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click="removeColumn(index)"
                                                class="h-8 w-8 p-0 text-muted-foreground hover:text-destructive"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>

                                        <!-- Select Options -->
                                        <div
                                            v-if="column.type === 'select' && expandedColumns[index]"
                                            class="border-t bg-muted/30 p-3"
                                        >
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <Label class="text-xs text-muted-foreground">Select Options</Label>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        @click="addOption(column)"
                                                        class="h-6 text-xs gap-1"
                                                    >
                                                        <Plus class="h-3 w-3" />
                                                        Add Option
                                                    </Button>
                                                </div>

                                                <div v-if="!column.options?.length" class="text-xs text-muted-foreground text-center py-2">
                                                    No options yet. Add options for your select dropdown.
                                                </div>

                                                <div v-else class="space-y-1">
                                                    <div
                                                        v-for="(option, optIndex) in column.options"
                                                        :key="optIndex"
                                                        class="flex items-center gap-2"
                                                    >
                                                        <Input
                                                            v-model="option.label"
                                                            placeholder="Option label"
                                                            class="h-8 text-sm flex-1"
                                                        />

                                                        <!-- Color picker dropdown -->
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1">
                                                                <div
                                                                    v-for="color in predefinedColors"
                                                                    :key="color.value"
                                                                    @click="setOptionColor(option, color.value)"
                                                                    class="w-5 h-5 rounded cursor-pointer border hover:scale-110 transition-transform"
                                                                    :class="option.color === color.value ? 'ring-2 ring-primary ring-offset-1' : ''"
                                                                    :style="{ backgroundColor: color.value }"
                                                                    :title="color.name"
                                                                />
                                                                <label class="flex items-center cursor-pointer ml-1">
                                                                    <input
                                                                        type="color"
                                                                        :value="option.color || '#dbeafe'"
                                                                        @input="(e) => setOptionColor(option, (e.target as HTMLInputElement).value)"
                                                                        class="w-5 h-5 rounded cursor-pointer"
                                                                    />
                                                                </label>
                                                            </div>
                                                        </div>

                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            @click="removeOption(column, optIndex)"
                                                            class="h-6 w-6 p-0 text-muted-foreground hover:text-destructive"
                                                        >
                                                            <Trash2 class="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </div>

                                                <!-- Preview -->
                                                <div v-if="column.options?.length" class="pt-2 border-t mt-2">
                                                    <Label class="text-xs text-muted-foreground mb-1 block">Preview</Label>
                                                    <div class="flex flex-wrap gap-1">
                                                        <span
                                                            v-for="(option, optIndex) in column.options"
                                                            :key="optIndex"
                                                            class="px-2 py-0.5 rounded text-xs font-medium"
                                                            :style="{
                                                                backgroundColor: option.color || '#dbeafe',
                                                                color: predefinedColors.find(c => c.value === option.color)?.text || '#2563eb'
                                                            }"
                                                        >
                                                            {{ option.label || 'Untitled' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Save Changes
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/checklists/${checklist.id}`)">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card class="border-destructive/50">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-destructive">
                            <Trash2 class="h-5 w-5" />
                            Danger Zone
                        </CardTitle>
                        <CardDescription>
                            Permanently delete this checklist and all its items.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Dialog v-model:open="showDeleteDialog">
                            <DialogTrigger as-child>
                                <Button variant="destructive">Delete Checklist</Button>
                            </DialogTrigger>
                            <DialogContent class="max-w-sm">
                                <DialogHeader>
                                    <DialogTitle>Delete Checklist?</DialogTitle>
                                    <DialogDescription>
                                        Are you sure you want to delete "{{ checklist.name }}"? This action cannot be undone.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="flex gap-4 sm:justify-end">
                                    <Button variant="secondary" @click="showDeleteDialog = false" class="flex-1 sm:flex-none">
                                        No
                                    </Button>
                                    <Button variant="destructive" @click="deleteChecklist" class="flex-1 sm:flex-none">
                                        Yes
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
