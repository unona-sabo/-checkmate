<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite, type TestCase } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Edit, FileText, CheckCircle2, AlertCircle, ListOrdered, Target, Bot, Tag } from 'lucide-vue-next';

const props = defineProps<{
    project: Project;
    testSuite: TestSuite;
    testCase: TestCase;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
    { title: props.testSuite.name, href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}` },
    { title: props.testCase.title, href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}/test-cases/${props.testCase.id}` },
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

const getSeverityColor = (severity: string) => {
    switch (severity) {
        case 'blocker': return 'bg-red-500/10 text-red-500 border-red-500/20';
        case 'critical': return 'bg-red-400/10 text-red-400 border-red-400/20';
        case 'major': return 'bg-orange-500/10 text-orange-500 border-orange-500/20';
        case 'minor': return 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20';
        case 'trivial': return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        default: return '';
    }
};

const getAutomationColor = (status: string) => {
    switch (status) {
        case 'automated': return 'bg-green-500/10 text-green-500 border-green-500/20';
        case 'to_be_automated': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        case 'not_automated': return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        default: return '';
    }
};
</script>

<template>
    <Head :title="testCase.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <FileText class="h-6 w-6 text-primary" />
                        {{ testCase.title }}
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <Badge :class="getPriorityColor(testCase.priority)" variant="outline">
                            Priority: {{ testCase.priority }}
                        </Badge>
                        <Badge :class="getSeverityColor(testCase.severity)" variant="outline">
                            Severity: {{ testCase.severity }}
                        </Badge>
                        <Badge variant="secondary">{{ testCase.type }}</Badge>
                        <Badge :class="getAutomationColor(testCase.automation_status)" variant="outline">
                            <Bot class="mr-1 h-3 w-3" />
                            {{ testCase.automation_status.replace(/_/g, ' ') }}
                        </Badge>
                    </div>
                </div>
                <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/${testCase.id}/edit`">
                    <Button variant="outline" class="gap-2">
                        <Edit class="h-4 w-4" />
                        Edit
                    </Button>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <!-- Description -->
                    <Card v-if="testCase.description">
                        <CardHeader>
                            <CardTitle class="text-base">Description</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground whitespace-pre-wrap">{{ testCase.description }}</p>
                        </CardContent>
                    </Card>

                    <!-- Preconditions -->
                    <Card v-if="testCase.preconditions">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <AlertCircle class="h-4 w-4 text-yellow-500" />
                                Preconditions
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground whitespace-pre-wrap">{{ testCase.preconditions }}</p>
                        </CardContent>
                    </Card>

                    <!-- Test Steps -->
                    <Card v-if="testCase.steps?.length">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <ListOrdered class="h-4 w-4 text-primary" />
                                Test Steps
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="(step, index) in testCase.steps" :key="index" class="flex gap-3 rounded-lg border p-3">
                                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-medium text-primary-foreground">
                                    {{ index + 1 }}
                                </div>
                                <div class="flex-1 space-y-2">
                                    <p class="text-sm">{{ step.action }}</p>
                                    <p v-if="step.expected" class="text-sm text-muted-foreground">
                                        <span class="font-medium text-green-500">Expected:</span> {{ step.expected }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Expected Result -->
                    <Card v-if="testCase.expected_result">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Target class="h-4 w-4 text-green-500" />
                                Expected Result
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground whitespace-pre-wrap">{{ testCase.expected_result }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Tags -->
                    <Card v-if="testCase.tags?.length">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Tag class="h-4 w-4" />
                                Tags
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-wrap gap-2">
                                <Badge v-for="tag in testCase.tags" :key="tag" variant="secondary">
                                    {{ tag }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Note -->
                    <Card v-if="testCase.note?.content">
                        <CardHeader>
                            <CardTitle class="text-base">Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground whitespace-pre-wrap">{{ testCase.note.content }}</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
