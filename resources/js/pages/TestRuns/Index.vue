<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestRun } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Plus, Play, CheckCircle2, Archive, ArrowRight } from 'lucide-vue-next';

const props = defineProps<{
    project: Project;
    testRuns: TestRun[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Runs', href: `/projects/${props.project.id}/test-runs` },
];

const getStatusColor = (status: string) => {
    switch (status) {
        case 'active': return 'bg-green-500/10 text-green-500 border-green-500/20';
        case 'completed': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        case 'archived': return 'bg-gray-500/10 text-gray-500 border-gray-500/20';
        default: return '';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'active': return Play;
        case 'completed': return CheckCircle2;
        case 'archived': return Archive;
        default: return Play;
    }
};
</script>

<template>
    <Head title="Test Runs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Test Runs</h1>
                    <p class="text-muted-foreground">Execute and track test case results</p>
                </div>
                <Link :href="`/projects/${project.id}/test-runs/create`">
                    <Button variant="cta" class="gap-2">
                        <Plus class="h-4 w-4" />
                        New Test Run
                    </Button>
                </Link>
            </div>

            <div v-if="testRuns.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <Play class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No test runs yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Create a test run to start executing your test cases.</p>
                    <Link :href="`/projects/${project.id}/test-runs/create`" class="mt-4 inline-block">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Create Test Run
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-else class="space-y-4">
                <Link
                    v-for="run in testRuns"
                    :key="run.id"
                    :href="`/projects/${project.id}/test-runs/${run.id}`"
                >
                    <Card class="transition-all hover:border-primary cursor-pointer">
                        <CardContent class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <component :is="getStatusIcon(run.status)" class="h-5 w-5 text-primary" />
                                        <h3 class="text-lg font-semibold">{{ run.name }}</h3>
                                    </div>
                                    <p v-if="run.description" class="text-sm text-muted-foreground line-clamp-1">
                                        {{ run.description }}
                                    </p>
                                    <div class="flex items-center gap-3 text-sm text-muted-foreground">
                                        <Badge :class="getStatusColor(run.status)" variant="outline">
                                            {{ run.status }}
                                        </Badge>
                                        <span v-if="run.environment">{{ run.environment }}</span>
                                        <span v-if="run.milestone">{{ run.milestone }}</span>
                                        <span>{{ run.test_run_cases_count || 0 }} cases</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-primary">{{ run.progress }}%</div>
                                        <Progress :model-value="run.progress" class="w-24" />
                                    </div>
                                    <ArrowRight class="h-5 w-5 text-muted-foreground" />
                                </div>
                            </div>

                            <!-- Stats -->
                            <div v-if="run.stats" class="mt-4 flex gap-4">
                                <div v-if="run.stats.passed" class="flex items-center gap-1 text-sm">
                                    <div class="h-2 w-2 rounded-full bg-green-500"></div>
                                    <span>{{ run.stats.passed }} passed</span>
                                </div>
                                <div v-if="run.stats.failed" class="flex items-center gap-1 text-sm">
                                    <div class="h-2 w-2 rounded-full bg-red-500"></div>
                                    <span>{{ run.stats.failed }} failed</span>
                                </div>
                                <div v-if="run.stats.blocked" class="flex items-center gap-1 text-sm">
                                    <div class="h-2 w-2 rounded-full bg-orange-500"></div>
                                    <span>{{ run.stats.blocked }} blocked</span>
                                </div>
                                <div v-if="run.stats.skipped" class="flex items-center gap-1 text-sm">
                                    <div class="h-2 w-2 rounded-full bg-purple-500"></div>
                                    <span>{{ run.stats.skipped }} skipped</span>
                                </div>
                                <div v-if="run.stats.untested" class="flex items-center gap-1 text-sm">
                                    <div class="h-2 w-2 rounded-full bg-gray-500"></div>
                                    <span>{{ run.stats.untested }} untested</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
