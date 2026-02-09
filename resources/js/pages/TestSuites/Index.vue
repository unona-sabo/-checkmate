<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Plus, TestTube, ChevronRight, FileText } from 'lucide-vue-next';

const props = defineProps<{
    project: Project;
    testSuites: TestSuite[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
];
</script>

<template>
    <Head title="Test Suites" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Test Suites</h1>
                    <p class="text-muted-foreground">Organize your test cases into logical groups</p>
                </div>
                <Link :href="`/projects/${project.id}/test-suites/create`">
                    <Button variant="cta" class="gap-2">
                        <Plus class="h-4 w-4" />
                        New Test Suite
                    </Button>
                </Link>
            </div>

            <div v-if="testSuites.length === 0" class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <TestTube class="mx-auto h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No test suites yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Create your first test suite to organize your test cases.</p>
                    <Link :href="`/projects/${project.id}/test-suites/create`" class="mt-4 inline-block">
                        <Button variant="cta" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Create Test Suite
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-else class="space-y-4">
                <Card v-for="suite in testSuites" :key="suite.id" class="transition-all hover:border-primary/50">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <Link :href="`/projects/${project.id}/test-suites/${suite.id}`" class="flex-1">
                                <CardTitle class="flex items-center gap-2 text-lg hover:text-primary">
                                    <TestTube class="h-5 w-5 text-primary" />
                                    {{ suite.name }}
                                </CardTitle>
                            </Link>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline">
                                    <FileText class="mr-1 h-3 w-3" />
                                    {{ suite.test_cases_count || 0 }} cases
                                </Badge>
                                <Link :href="`/projects/${project.id}/test-suites/${suite.id}`">
                                    <Button size="sm" variant="ghost">
                                        <ChevronRight class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p v-if="suite.description" class="text-sm text-muted-foreground">
                            {{ suite.description }}
                        </p>

                        <!-- Child suites -->
                        <div v-if="suite.children?.length" class="mt-4 space-y-2">
                            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Nested Suites</p>
                            <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                                <Link
                                    v-for="child in suite.children"
                                    :key="child.id"
                                    :href="`/projects/${project.id}/test-suites/${child.id}`"
                                    class="flex items-center justify-between rounded-lg border p-3 text-sm transition-colors hover:bg-muted/50"
                                >
                                    <span class="font-medium">{{ child.name }}</span>
                                    <Badge variant="secondary" class="text-xs">
                                        {{ child.test_cases?.length || 0 }}
                                    </Badge>
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
