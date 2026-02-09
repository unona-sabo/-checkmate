<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import { TestTube } from 'lucide-vue-next';

const props = defineProps<{
    project: Project;
    parentSuites: Pick<TestSuite, 'id' | 'name'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
    { title: 'Create', href: `/projects/${props.project.id}/test-suites/create` },
];

const form = useForm({
    name: '',
    description: '',
    parent_id: null as number | null,
});

const submit = () => {
    form.post(`/projects/${props.project.id}/test-suites`);
};
</script>

<template>
    <Head title="Create Test Suite" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <TestTube class="h-5 w-5 text-primary" />
                            Create Test Suite
                        </CardTitle>
                        <CardDescription>
                            Create a new test suite to organize your test cases.
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
