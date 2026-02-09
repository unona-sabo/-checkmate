<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite, type TestCase } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Plus, Edit, TestTube, FileText, ArrowRight,
    AlertTriangle, CircleAlert, Zap, Bug
} from 'lucide-vue-next';

const props = defineProps<{
    project: Project;
    testSuite: TestSuite;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
    { title: props.testSuite.name, href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}` },
];

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'critical': return 'bg-red-500/10 text-red-500 border-red-500/20';
        case 'high': return 'bg-orange-500/10 text-orange-500 border-orange-500/20';
        case 'medium': return 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20';
        case 'low': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        default: return '';
    }
};

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'smoke': return Zap;
        case 'regression': return Bug;
        default: return FileText;
    }
};
</script>

<template>
    <Head :title="testSuite.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <TestTube class="h-6 w-6 text-primary" />
                        {{ testSuite.name }}
                    </h1>
                    <p v-if="testSuite.description" class="text-muted-foreground">
                        {{ testSuite.description }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/create`">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Add Test Case
                        </Button>
                    </Link>
                    <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/edit`">
                        <Button variant="outline" class="gap-2">
                            <Edit class="h-4 w-4" />
                            Edit Suite
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Child Suites -->
            <div v-if="testSuite.children?.length" class="space-y-3">
                <h2 class="text-lg font-semibold">Nested Suites</h2>
                <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="child in testSuite.children"
                        :key="child.id"
                        :href="`/projects/${project.id}/test-suites/${child.id}`"
                    >
                        <Card class="transition-all hover:border-primary cursor-pointer h-full">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center justify-between text-base">
                                    <span class="flex items-center gap-2">
                                        <TestTube class="h-4 w-4 text-primary" />
                                        {{ child.name }}
                                    </span>
                                    <Badge variant="outline">
                                        {{ child.test_cases?.length || 0 }} cases
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    </Link>
                </div>
            </div>

            <!-- Test Cases -->
            <div class="space-y-3">
                <h2 class="text-lg font-semibold">Test Cases</h2>

                <div v-if="!testSuite.test_cases?.length" class="rounded-lg border border-dashed p-8 text-center">
                    <FileText class="mx-auto h-10 w-10 text-muted-foreground" />
                    <h3 class="mt-3 text-sm font-semibold">No test cases yet</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Add test cases to this suite.</p>
                    <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/create`" class="mt-4 inline-block">
                        <Button size="sm" variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Add Test Case
                        </Button>
                    </Link>
                </div>

                <div v-else class="space-y-2">
                    <Link
                        v-for="testCase in testSuite.test_cases"
                        :key="testCase.id"
                        :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/${testCase.id}`"
                    >
                        <Card class="transition-all hover:border-primary cursor-pointer">
                            <CardContent class="flex items-center justify-between p-4">
                                <div class="flex items-center gap-3">
                                    <component :is="getTypeIcon(testCase.type)" class="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p class="font-medium">{{ testCase.title }}</p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <Badge :class="getPriorityColor(testCase.priority)" variant="outline" class="text-xs">
                                                {{ testCase.priority }}
                                            </Badge>
                                            <Badge variant="secondary" class="text-xs">{{ testCase.type }}</Badge>
                                            <span v-if="testCase.tags?.length" class="text-xs text-muted-foreground">
                                                {{ testCase.tags.join(', ') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <ArrowRight class="h-4 w-4 text-muted-foreground" />
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
