<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type Attachment } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Bug, Edit, Trash2, Paperclip, Download, Link2, Check } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Bugreport {
    id: number;
    title: string;
    description: string | null;
    steps_to_reproduce: string | null;
    expected_result: string | null;
    actual_result: string | null;
    severity: 'critical' | 'major' | 'minor' | 'trivial';
    priority: 'high' | 'medium' | 'low';
    status: 'new' | 'open' | 'in_progress' | 'resolved' | 'closed' | 'reopened';
    environment: string | null;
    reporter: { id: number; name: string } | null;
    assignee: { id: number; name: string } | null;
    attachments?: Attachment[];
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    project: Project;
    bugreport: Bugreport;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Bugreports', href: `/projects/${props.project.id}/bugreports` },
    { title: props.bugreport.title, href: `/projects/${props.project.id}/bugreports/${props.bugreport.id}` },
];

const getSeverityColor = (severity: string) => {
    switch (severity) {
        case 'critical': return 'bg-red-100 text-red-800';
        case 'major': return 'bg-orange-100 text-orange-800';
        case 'minor': return 'bg-yellow-100 text-yellow-800';
        case 'trivial': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'new': return 'bg-blue-100 text-blue-800';
        case 'open': return 'bg-purple-100 text-purple-800';
        case 'in_progress': return 'bg-yellow-100 text-yellow-800';
        case 'resolved': return 'bg-green-100 text-green-800';
        case 'closed': return 'bg-gray-100 text-gray-800';
        case 'reopened': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'high': return 'bg-red-100 text-red-800';
        case 'medium': return 'bg-yellow-100 text-yellow-800';
        case 'low': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const isImage = (mimeType: string): boolean => {
    return mimeType.startsWith('image/');
};

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const copied = ref(false);
const showDeleteConfirm = ref(false);

const titleStart = computed(() => {
    const words = props.bugreport.title.split(' ');
    return words.length > 1 ? words.slice(0, -1).join(' ') + ' ' : '';
});
const titleEnd = computed(() => {
    const words = props.bugreport.title.split(' ');
    return words[words.length - 1];
});

const copyLink = () => {
    const route = `/projects/${props.project.id}/bugreports/${props.bugreport.id}`;
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

const formatDate = (date: string): string => {
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head :title="bugreport.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-[15px] p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold tracking-tight">
                    <Bug class="inline-block h-6 w-6 align-text-top text-primary mr-2" />{{ titleStart }}<span class="whitespace-nowrap">{{ titleEnd }}<button
                        @click="copyLink"
                        class="inline-flex align-middle ml-1.5 p-1 rounded-md text-muted-foreground hover:text-primary hover:bg-muted transition-colors cursor-pointer"
                        :title="copied ? 'Copied!' : 'Copy link'"
                    ><Check v-if="copied" class="h-4 w-4 text-green-500" /><Link2 v-else class="h-4 w-4" /></button></span>
                </h1>
                <div class="flex gap-2">
                    <Link :href="`/projects/${project.id}/bugreports/${bugreport.id}/edit`">
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
                    <Card>
                        <CardHeader>
                            <CardTitle>Description</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="whitespace-pre-wrap">{{ bugreport.description || 'No description provided.' }}</p>
                        </CardContent>
                    </Card>

                    <Card v-if="bugreport.steps_to_reproduce">
                        <CardHeader>
                            <CardTitle>Steps to Reproduce</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="whitespace-pre-wrap">{{ bugreport.steps_to_reproduce }}</p>
                        </CardContent>
                    </Card>

                    <div class="grid gap-6 md:grid-cols-2">
                        <Card v-if="bugreport.expected_result">
                            <CardHeader>
                                <CardTitle>Expected Result</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="whitespace-pre-wrap">{{ bugreport.expected_result }}</p>
                            </CardContent>
                        </Card>

                        <Card v-if="bugreport.actual_result">
                            <CardHeader>
                                <CardTitle>Actual Result</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="whitespace-pre-wrap">{{ bugreport.actual_result }}</p>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Attachments -->
                    <Card v-if="bugreport.attachments?.length">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Paperclip class="h-4 w-4" />
                                Attachments
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <!-- Image previews -->
                            <div v-if="bugreport.attachments.some(a => isImage(a.mime_type))" class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div
                                    v-for="attachment in bugreport.attachments.filter(a => isImage(a.mime_type))"
                                    :key="attachment.id"
                                    class="group relative overflow-hidden rounded-lg border"
                                >
                                    <a :href="attachment.url" target="_blank" class="block">
                                        <img :src="attachment.url" :alt="attachment.original_filename" class="aspect-video w-full object-cover transition-transform group-hover:scale-105" />
                                    </a>
                                    <div class="flex items-center justify-between p-2">
                                        <span class="truncate text-xs text-muted-foreground">{{ attachment.original_filename }}</span>
                                        <Link
                                            :href="`/projects/${project.id}/bugreports/${bugreport.id}/attachments/${attachment.id}`"
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
                                <div v-for="attachment in bugreport.attachments.filter(a => !isImage(a.mime_type))" :key="attachment.id" class="flex items-center justify-between rounded-lg border p-2">
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
                                            :href="`/projects/${project.id}/bugreports/${bugreport.id}/attachments/${attachment.id}`"
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

                <div class="space-y-[15px]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div>
                                <p class="text-xs text-muted-foreground">Status</p>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getStatusColor(bugreport.status)]">
                                    {{ bugreport.status.replace('_', ' ') }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Severity</p>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getSeverityColor(bugreport.severity)]">
                                    {{ bugreport.severity }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Priority</p>
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getPriorityColor(bugreport.priority)]">
                                    {{ bugreport.priority }}
                                </span>
                            </div>
                            <div v-if="bugreport.environment">
                                <p class="text-xs text-muted-foreground">Environment</p>
                                <p class="text-sm font-medium">{{ bugreport.environment }}</p>
                            </div>
                            <div v-if="bugreport.reporter">
                                <p class="text-xs text-muted-foreground">Reported by</p>
                                <p class="text-sm font-medium">{{ bugreport.reporter.name }}</p>
                            </div>
                            <div v-if="bugreport.assignee">
                                <p class="text-xs text-muted-foreground">Assigned to</p>
                                <p class="text-sm font-medium">{{ bugreport.assignee.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Created</p>
                                <p class="text-sm font-medium">{{ formatDate(bugreport.created_at) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Updated</p>
                                <p class="text-sm font-medium">{{ formatDate(bugreport.updated_at) }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteConfirm">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Delete Bug Report?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this bug report? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-4 sm:justify-end">
                    <Button variant="secondary" @click="showDeleteConfirm = false" class="flex-1 sm:flex-none">
                        No
                    </Button>
                    <Link
                        :href="`/projects/${project.id}/bugreports/${bugreport.id}`"
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
