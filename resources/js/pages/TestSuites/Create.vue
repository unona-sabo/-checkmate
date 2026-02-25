<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite } from '@/types';
import { type ProjectFeature } from '@/types/checkmate';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import { useClearErrorsOnInput } from '@/composables/useClearErrorsOnInput';
import { Checkbox } from '@/components/ui/checkbox';
import FeatureSelector from '@/components/FeatureSelector.vue';
import { Layers, Boxes } from 'lucide-vue-next';

const MODULE_OPTIONS = ['UI', 'API', 'Backend', 'Database', 'Integration'] as const;

const props = defineProps<{
    project: Project;
    parentSuites: Pick<TestSuite, 'id' | 'name'>[];
    features: Pick<ProjectFeature, 'id' | 'name' | 'module' | 'priority'>[];
}>();

// Get parent_id from URL query params
const urlParams = new URLSearchParams(window.location.search);
const preselectedParentId = urlParams.get('parent_id') ? Number(urlParams.get('parent_id')) : null;
const preselectedParent = preselectedParentId ? props.parentSuites.find(s => s.id === preselectedParentId) : null;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
    { title: preselectedParent ? 'Create Subcategory' : 'Create', href: `/projects/${props.project.id}/test-suites/create` },
];

const form = useForm({
    name: '',
    description: '',
    type: 'functional',
    module: [] as string[],
    parent_id: preselectedParentId,
    feature_ids: [] as number[],
});
useClearErrorsOnInput(form);

const submit = () => {
    form.post(`/projects/${props.project.id}/test-suites`);
};
</script>

<template>
    <Head :title="preselectedParent ? 'Create Subcategory' : 'Create Test Suite'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Boxes v-if="preselectedParent" class="h-5 w-5 text-yellow-500" />
                            <Layers v-else class="h-5 w-5 text-primary" />
                            {{ preselectedParent ? 'Create Subcategory' : 'Create Test Suite' }}
                        </CardTitle>
                        <CardDescription>
                            <template v-if="preselectedParent">
                                Create a new subcategory in <strong>{{ preselectedParent.name }}</strong>
                            </template>
                            <template v-else>
                                Create a new test suite to organize your test cases.
                            </template>
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
                                    placeholder="e.g., Login Module, User Management"
                                    :class="{ 'border-destructive': form.errors.name }"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Describe the purpose of this test suite..."
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

                            <!-- Module -->
                            <div class="space-y-2">
                                <Label>Module</Label>
                                <div class="flex items-center gap-4">
                                    <button type="button" class="text-xs text-primary hover:underline cursor-pointer" @click="form.module = [...MODULE_OPTIONS]">Select All</button>
                                    <button type="button" class="text-xs text-muted-foreground hover:underline cursor-pointer" @click="form.module = []">Clear</button>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <label v-for="opt in MODULE_OPTIONS" :key="opt" class="flex items-center gap-2 cursor-pointer">
                                        <Checkbox
                                            :model-value="form.module.includes(opt)"
                                            @update:model-value="(checked: boolean) => {
                                                if (checked) { form.module.push(opt); }
                                                else { form.module = form.module.filter(m => m !== opt); }
                                            }"
                                        />
                                        <span class="text-sm">{{ opt }}</span>
                                    </label>
                                </div>
                                <InputError :message="form.errors.module" />
                            </div>

                            <FeatureSelector v-model="form.feature_ids" :features="features" :project-id="project.id" />

                            <div v-if="parentSuites.length" class="space-y-2">
                                <Label for="parent">Parent Suite (optional)</Label>
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
                                    Create Test Suite
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/test-suites`)">
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
