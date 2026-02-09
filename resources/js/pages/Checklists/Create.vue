<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type ColumnConfig } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import { ClipboardList, Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    project: Project;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Checklists', href: `/projects/${props.project.id}/checklists` },
    { title: 'Create', href: `/projects/${props.project.id}/checklists/create` },
];

const form = useForm({
    name: '',
    columns_config: [
        { key: 'item', label: 'Item', type: 'text' as const },
        { key: 'status', label: 'Status', type: 'checkbox' as const },
    ] as ColumnConfig[],
});

const addColumn = () => {
    form.columns_config.push({
        key: `column_${Date.now()}`,
        label: '',
        type: 'text',
    });
};

const removeColumn = (index: number) => {
    form.columns_config.splice(index, 1);
};

const submit = () => {
    form.post(`/projects/${props.project.id}/checklists`);
};
</script>

<template>
    <Head title="Create Checklist" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <ClipboardList class="h-5 w-5 text-primary" />
                            Create Checklist
                        </CardTitle>
                        <CardDescription>
                            Create a new checklist with custom columns.
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
                                    placeholder="e.g., Deployment Checklist, Code Review Items"
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
                                        class="flex items-center gap-2 rounded-lg border p-3"
                                    >
                                        <div class="flex-1 space-y-2">
                                            <Input
                                                v-model="column.label"
                                                placeholder="Column name"
                                            />
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
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Create Checklist
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/checklists`)">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
