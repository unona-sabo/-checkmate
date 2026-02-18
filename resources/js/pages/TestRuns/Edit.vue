<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestRun } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { Edit, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    project: Project;
    testRun: TestRun;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Runs', href: `/projects/${props.project.id}/test-runs` },
    { title: props.testRun.name, href: `/projects/${props.project.id}/test-runs/${props.testRun.id}` },
    { title: 'Edit', href: `/projects/${props.project.id}/test-runs/${props.testRun.id}/edit` },
];

const form = useForm({
    name: props.testRun.name,
    description: props.testRun.description || '',
    priority: props.testRun.priority || '' as string,
    environment: props.testRun.environment || '',
    milestone: props.testRun.milestone || '',
    status: props.testRun.status,
});

const environmentPresets = ['Develop', 'Staging', 'Production'];

const parseEnvironment = (env: string): { preset: string; notes: string } => {
    for (const p of environmentPresets) {
        if (env.startsWith(p)) {
            const rest = env.slice(p.length).replace(/^\s*[—–\-]\s*/, '').trim();
            return { preset: p, notes: rest };
        }
    }
    return { preset: '', notes: env };
};

const parsed = parseEnvironment(props.testRun.environment || '');
const envPreset = ref(parsed.preset);
const envNotes = ref(parsed.notes);

watch([envPreset, envNotes], () => {
    const parts = [envPreset.value, envNotes.value.trim()].filter(Boolean);
    form.environment = parts.join(' — ');
});

const showDeleteDialog = ref(false);

const submit = () => {
    form.put(`/projects/${props.project.id}/test-runs/${props.testRun.id}`);
};

const deleteRun = () => {
    router.delete(`/projects/${props.project.id}/test-runs/${props.testRun.id}`);
};
</script>

<template>
    <Head title="Edit Test Run" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Edit class="h-5 w-5 text-primary" />
                            Edit Test Run
                        </CardTitle>
                        <CardDescription>
                            Update test run details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="name">Run Name</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    :class="{ 'border-destructive': form.errors.name }"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="2"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label>Priority</Label>
                                <Select v-model="form.priority">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select priority..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Low</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                        <SelectItem value="critical">Critical</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.priority" />
                            </div>

                            <div class="space-y-2">
                                <Label>Environment</Label>
                                <div class="grid gap-3 md:grid-cols-3">
                                    <Select v-model="envPreset">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Develop">Develop</SelectItem>
                                            <SelectItem value="Staging">Staging</SelectItem>
                                            <SelectItem value="Production">Production</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        v-model="envNotes"
                                        type="text"
                                        placeholder="Devices, browser..."
                                        class="md:col-span-2"
                                    />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="milestone">Milestone</Label>
                                <Input
                                    id="milestone"
                                    v-model="form.milestone"
                                    type="text"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label>Status</Label>
                                <Select v-model="form.status">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="completed">Completed</SelectItem>
                                        <SelectItem value="archived">Archived</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Save Changes
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/test-runs/${testRun.id}`)">
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
                            Permanently delete this test run and all its results.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Dialog v-model:open="showDeleteDialog">
                            <DialogTrigger as-child>
                                <Button variant="destructive">Delete Test Run</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Are you absolutely sure?</DialogTitle>
                                    <DialogDescription>
                                        This action cannot be undone. This will permanently delete the test run
                                        "{{ testRun.name }}" and all of its results.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                                    <Button variant="destructive" @click="deleteRun">Delete Test Run</Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
