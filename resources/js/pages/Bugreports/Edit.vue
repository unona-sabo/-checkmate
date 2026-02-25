<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type Attachment } from '@/types';
import { type ProjectFeature } from '@/types/checkmate';
import FeatureSelector from '@/components/FeatureSelector.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import { useClearErrorsOnInput } from '@/composables/useClearErrorsOnInput';
import { Bug, Paperclip, X, Download, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface User {
    id: number;
    name: string;
}

interface Bugreport {
    id: number;
    title: string;
    description: string | null;
    steps_to_reproduce: string | null;
    expected_result: string | null;
    actual_result: string | null;
    severity: string;
    priority: string;
    status: string;
    environment: string | null;
    fixed_on: string[] | null;
    assigned_to: number | null;
    attachments?: Attachment[];
    project_features?: { id: number }[];
}

const props = defineProps<{
    project: Project;
    bugreport: Bugreport;
    users: User[];
    features: Pick<ProjectFeature, 'id' | 'name' | 'module' | 'priority'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Bugreports', href: `/projects/${props.project.id}/bugreports` },
    { title: props.bugreport.title, href: `/projects/${props.project.id}/bugreports/${props.bugreport.id}` },
    { title: 'Edit', href: `/projects/${props.project.id}/bugreports/${props.bugreport.id}/edit` },
];

const form = useForm({
    _method: 'put',
    title: props.bugreport.title,
    description: props.bugreport.description || '',
    steps_to_reproduce: props.bugreport.steps_to_reproduce || '',
    expected_result: props.bugreport.expected_result || '',
    actual_result: props.bugreport.actual_result || '',
    severity: props.bugreport.severity,
    priority: props.bugreport.priority,
    status: props.bugreport.status,
    environment: props.bugreport.environment || '',
    fixed_on: props.bugreport.fixed_on || [],
    assigned_to: props.bugreport.assigned_to,
    feature_ids: (props.bugreport.project_features ?? []).map(f => f.id),
    attachments: [] as File[],
});
useClearErrorsOnInput(form);

const fileInput = ref<HTMLInputElement | null>(null);

const onFilesSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        for (const file of Array.from(target.files)) {
            form.attachments.push(file);
        }
    }
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const removeFile = (index: number) => {
    form.attachments.splice(index, 1);
};

const attachmentErrors = computed(() => {
    return Object.entries(form.errors)
        .filter(([key]) => key.startsWith('attachments'))
        .map(([, message]) => message);
});

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const isImage = (mimeType: string): boolean => {
    return mimeType.startsWith('image/');
};

const deleteAttachment = (attachmentId: number) => {
    router.delete(`/projects/${props.project.id}/bugreports/${props.bugreport.id}/attachments/${attachmentId}`, {
        preserveScroll: true,
    });
};

const envOptions = ['develop', 'staging', 'production'] as const;
const allEnvsSelected = computed(() => envOptions.every(e => form.fixed_on.includes(e)));
const toggleEnv = (env: string) => {
    const idx = form.fixed_on.indexOf(env);
    if (idx >= 0) {
        form.fixed_on.splice(idx, 1);
    } else {
        form.fixed_on.push(env);
    }
};
const toggleAllEnvs = () => {
    if (allEnvsSelected.value) {
        form.fixed_on = [];
    } else {
        form.fixed_on = [...envOptions];
    }
};
const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

const submit = () => {
    form.post(`/projects/${props.project.id}/bugreports/${props.bugreport.id}`, {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Edit Bug Report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Bug class="h-5 w-5 text-primary" />
                            Edit Bug Report
                        </CardTitle>
                        <CardDescription>
                            Update the bug report details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="space-y-2">
                                <Label for="title">Title *</Label>
                                <Input
                                    id="title"
                                    v-model="form.title"
                                    type="text"
                                    :class="{ 'border-destructive': form.errors.title }"
                                />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="3"
                                    autoResize
                                />
                                <InputError :message="form.errors.description" />
                            </div>

                            <div class="space-y-2">
                                <Label for="steps_to_reproduce">Steps to Reproduce</Label>
                                <Textarea
                                    id="steps_to_reproduce"
                                    v-model="form.steps_to_reproduce"
                                    rows="4"
                                    autoResize
                                />
                                <InputError :message="form.errors.steps_to_reproduce" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="expected_result">Expected Result</Label>
                                    <Textarea
                                        id="expected_result"
                                        v-model="form.expected_result"
                                        rows="2"
                                        autoResize
                                    />
                                    <InputError :message="form.errors.expected_result" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="actual_result">Actual Result</Label>
                                    <Textarea
                                        id="actual_result"
                                        v-model="form.actual_result"
                                        rows="2"
                                        autoResize
                                    />
                                    <InputError :message="form.errors.actual_result" />
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div class="space-y-2">
                                    <Label>Severity</Label>
                                    <Select v-model="form.severity">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="critical">Critical</SelectItem>
                                            <SelectItem value="major">Major</SelectItem>
                                            <SelectItem value="minor">Minor</SelectItem>
                                            <SelectItem value="trivial">Trivial</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label>Priority</Label>
                                    <Select v-model="form.priority">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="high">High</SelectItem>
                                            <SelectItem value="medium">Medium</SelectItem>
                                            <SelectItem value="low">Low</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label>Status</Label>
                                    <Select v-model="form.status">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="new">New</SelectItem>
                                            <SelectItem value="open">Open</SelectItem>
                                            <SelectItem value="in_progress">In Progress</SelectItem>
                                            <SelectItem value="resolved">Resolved</SelectItem>
                                            <SelectItem value="closed">Closed</SelectItem>
                                            <SelectItem value="reopened">Reopened</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="environment">Environment</Label>
                                <Input
                                    id="environment"
                                    v-model="form.environment"
                                    type="text"
                                />
                                <InputError :message="form.errors.environment" />
                            </div>

                            <div class="space-y-2">
                                <Label>Fixed On</Label>
                                <div class="flex flex-wrap gap-2">
                                    <Button type="button" size="sm" :variant="allEnvsSelected ? 'default' : 'outline'" @click="toggleAllEnvs" class="cursor-pointer">All</Button>
                                    <Button v-for="env in envOptions" :key="env" type="button" size="sm" :variant="form.fixed_on.includes(env) ? 'default' : 'outline'" @click="toggleEnv(env)" class="cursor-pointer">
                                        {{ capitalize(env) }}
                                    </Button>
                                </div>
                            </div>

                            <!-- Features -->
                            <FeatureSelector
                                v-model="form.feature_ids"
                                :features="features"
                                :project-id="project.id"
                            />

                            <!-- Existing Attachments -->
                            <div v-if="bugreport.attachments?.length" class="space-y-2">
                                <Label>Current Attachments</Label>
                                <div class="space-y-2">
                                    <div v-for="attachment in bugreport.attachments" :key="attachment.id" class="flex items-center justify-between rounded-lg border p-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <img v-if="isImage(attachment.mime_type)" :src="attachment.url" :alt="attachment.original_filename" class="h-10 w-10 rounded object-cover shrink-0" />
                                            <Paperclip v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                                            <span class="truncate text-sm">{{ attachment.original_filename }}</span>
                                            <span class="shrink-0 text-xs text-muted-foreground">{{ formatFileSize(attachment.size) }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <a :href="attachment.url" target="_blank" download>
                                                <Button type="button" variant="ghost" size="sm" class="h-6 w-6 p-0">
                                                    <Download class="h-4 w-4" />
                                                </Button>
                                            </a>
                                            <Button type="button" variant="ghost" size="sm" @click="deleteAttachment(attachment.id)" class="h-6 w-6 p-0 text-destructive hover:text-destructive">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- New Attachments -->
                            <div class="space-y-2">
                                <Label>Add Attachments</Label>
                                <div class="flex items-center gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="fileInput?.click()" class="gap-2">
                                        <Paperclip class="h-4 w-4" />
                                        Add Files
                                    </Button>
                                    <span class="text-xs text-muted-foreground">Max 10MB per file</span>
                                </div>
                                <input
                                    ref="fileInput"
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"
                                    class="hidden"
                                    @change="onFilesSelected"
                                />
                                <div v-if="form.attachments.length" class="space-y-2">
                                    <div v-for="(file, index) in form.attachments" :key="index" class="flex items-center justify-between rounded-lg border p-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <Paperclip class="h-4 w-4 shrink-0 text-muted-foreground" />
                                            <span class="truncate text-sm">{{ file.name }}</span>
                                            <span class="shrink-0 text-xs text-muted-foreground">{{ formatFileSize(file.size) }}</span>
                                        </div>
                                        <Button type="button" variant="ghost" size="sm" @click="removeFile(index)" class="h-6 w-6 p-0 shrink-0">
                                            <X class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                                <div v-if="attachmentErrors.length" class="space-y-1">
                                    <p v-for="(error, i) in attachmentErrors" :key="i" class="text-sm text-destructive">{{ error }}</p>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Update Bug Report
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/bugreports/${bugreport.id}`)">
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
