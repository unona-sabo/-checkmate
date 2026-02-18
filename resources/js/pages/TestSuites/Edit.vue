<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite } from '@/types';
import { type ProjectFeature } from '@/types/checkmate';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import FeatureSelector from '@/components/FeatureSelector.vue';
import { Edit, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    project: Project;
    testSuite: TestSuite;
    parentSuites: Pick<TestSuite, 'id' | 'name'>[];
    features: Pick<ProjectFeature, 'id' | 'name' | 'module' | 'priority'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
    { title: props.testSuite.name, href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}` },
    { title: 'Edit', href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}/edit` },
];

const form = useForm({
    name: props.testSuite.name,
    description: props.testSuite.description || '',
    type: props.testSuite.type || 'functional',
    parent_id: props.testSuite.parent_id,
    feature_ids: (props.testSuite.project_features ?? []).map(f => f.id),
});

const showDeleteDialog = ref(false);

const submit = () => {
    form.put(`/projects/${props.project.id}/test-suites/${props.testSuite.id}`);
};

const deleteSuite = () => {
    router.delete(`/projects/${props.project.id}/test-suites/${props.testSuite.id}`);
};
</script>

<template>
    <Head title="Edit Test Suite" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Edit class="h-5 w-5 text-primary" />
                            Edit Test Suite
                        </CardTitle>
                        <CardDescription>
                            Update your test suite details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="name">Suite Name</Label>
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
                                    rows="3"
                                />
                                <InputError :message="form.errors.description" />
                            </div>

                            <div class="space-y-2">
                                <Label>Type</Label>
                                <Select v-model="form.type">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="functional">Functional</SelectItem>
                                        <SelectItem value="smoke">Smoke</SelectItem>
                                        <SelectItem value="regression">Regression</SelectItem>
                                        <SelectItem value="integration">Integration</SelectItem>
                                        <SelectItem value="acceptance">Acceptance</SelectItem>
                                        <SelectItem value="performance">Performance</SelectItem>
                                        <SelectItem value="security">Security</SelectItem>
                                        <SelectItem value="usability">Usability</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.type" />
                            </div>

                            <FeatureSelector v-model="form.feature_ids" :features="features" :project-id="project.id" />

                            <div v-if="parentSuites.length" class="space-y-2">
                                <Label for="parent">Parent Suite</Label>
                                <Select v-model="form.parent_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select parent suite" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="null">None (top-level suite)</SelectItem>
                                        <SelectItem v-for="suite in parentSuites" :key="suite.id" :value="suite.id">
                                            {{ suite.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.parent_id" />
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Save Changes
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/test-suites/${testSuite.id}`)">
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
                            Permanently delete this test suite and all its test cases.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Dialog v-model:open="showDeleteDialog">
                            <DialogTrigger as-child>
                                <Button variant="destructive">Delete Test Suite</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Are you absolutely sure?</DialogTitle>
                                    <DialogDescription>
                                        This action cannot be undone. This will permanently delete the test suite
                                        "{{ testSuite.name }}" and all of its test cases.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                                    <Button variant="destructive" @click="deleteSuite">Delete Test Suite</Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
