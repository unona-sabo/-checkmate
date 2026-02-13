<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite, type TestCase } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Edit, FileText, AlertCircle, ListOrdered, Target, Tag, Paperclip, Download, Trash2, Link2, Check } from 'lucide-vue-next';
import { ref, computed } from 'vue';

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

const copied = ref(false);
const showDeleteConfirm = ref(false);

const titleStart = computed(() => {
    const words = props.testCase.title.split(' ');
    return words.length > 1 ? words.slice(0, -1).join(' ') + ' ' : '';
});
const titleEnd = computed(() => {
    const words = props.testCase.title.split(' ');
    return words[words.length - 1];
});

const copyLink = () => {
    const route = `/projects/${props.project.id}/test-suites/${props.testSuite.id}/test-cases/${props.testCase.id}`;
    const url = window.location.origin + route;
    const textArea = document.createElement('textarea');
    textArea.value = url;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
};

const isImage = (mimeType: string): boolean => {
    return mimeType.startsWith('image/');
};

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const formatDate = (date: string): string => {
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head :title="testCase.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-[15px] p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        <FileText class="inline-block h-6 w-6 align-text-top text-primary mr-2" />{{ titleStart }}<span class="whitespace-nowrap">{{ titleEnd }}<button
                            @click="copyLink"
                            class="inline-flex align-middle ml-1.5 p-1 rounded-md text-muted-foreground hover:text-primary hover:bg-muted transition-colors cursor-pointer"
                            :title="copied ? 'Copied!' : 'Copy link'"
                        ><Check v-if="copied" class="h-4 w-4 text-green-500" /><Link2 v-else class="h-4 w-4" /></button></span>
                    </h1>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/${testCase.id}/edit`">
                        <Button variant="outline" class="gap-2">
                            <Edit class="h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                    <Button variant="destructive" class="gap-2" @click="showDeleteConfirm = true">
                        <Trash2 class="h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <div class="grid gap-[15px] lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-[15px]">
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

                    <!-- Attachments -->
                    <Card v-if="testCase.attachments?.length">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Paperclip class="h-4 w-4" />
                                Attachments
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <!-- Image previews -->
                            <div v-if="testCase.attachments.some(a => isImage(a.mime_type))" class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div
                                    v-for="attachment in testCase.attachments.filter(a => isImage(a.mime_type))"
                                    :key="attachment.id"
                                    class="group relative overflow-hidden rounded-lg border"
                                >
                                    <a :href="attachment.url" target="_blank" class="block">
                                        <img :src="attachment.url" :alt="attachment.original_filename" class="aspect-video w-full object-cover transition-transform group-hover:scale-105" />
                                    </a>
                                    <div class="flex items-center justify-between p-2">
                                        <span class="truncate text-xs text-muted-foreground">{{ attachment.original_filename }}</span>
                                        <Link
                                            :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/${testCase.id}/attachments/${attachment.id}`"
                                            method="delete"
                                            as="button"
                                            class="p-1 text-muted-foreground hover:text-destructive cursor-pointer shrink-0"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                            <!-- File list -->
                            <div class="space-y-2">
                                <div v-for="attachment in testCase.attachments.filter(a => !isImage(a.mime_type))" :key="attachment.id" class="flex items-center justify-between rounded-lg border p-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <Paperclip class="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span class="truncate text-sm">{{ attachment.original_filename }}</span>
                                        <span class="shrink-0 text-xs text-muted-foreground">{{ formatFileSize(attachment.size) }}</span>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <a :href="attachment.url" target="_blank" download>
                                            <Button type="button" variant="ghost" size="icon-sm" class="p-0">
                                                <Download class="h-4 w-4" />
                                            </Button>
                                        </a>
                                        <Link
                                            :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/${testCase.id}/attachments/${attachment.id}`"
                                            method="delete"
                                            as="button"
                                            class="p-1 text-muted-foreground hover:text-destructive cursor-pointer"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-[15px]">
                    <!-- Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div>
                                <p class="text-xs text-muted-foreground">Priority</p>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getPriorityColor(testCase.priority)]">
                                    {{ testCase.priority }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Severity</p>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getSeverityColor(testCase.severity)]">
                                    {{ testCase.severity }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Type</p>
                                <p class="text-sm font-medium">{{ testCase.type }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Automation</p>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getAutomationColor(testCase.automation_status)]">
                                    {{ testCase.automation_status.replace(/_/g, ' ') }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Created</p>
                                <p class="text-sm font-medium">{{ formatDate(testCase.created_at) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Updated</p>
                                <p class="text-sm font-medium">{{ formatDate(testCase.updated_at) }}</p>
                            </div>
                        </CardContent>
                    </Card>

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

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteConfirm">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Delete Test Case?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this test case? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-4 sm:justify-end">
                    <Button variant="secondary" @click="showDeleteConfirm = false" class="flex-1 sm:flex-none">
                        No
                    </Button>
                    <Link
                        :href="`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/${testCase.id}`"
                        method="delete"
                        as="button"
                    >
                        <Button variant="destructive" class="flex-1 sm:flex-none">
                            Yes
                        </Button>
                    </Link>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
