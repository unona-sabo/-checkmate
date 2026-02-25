<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
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
import { Bug, Paperclip, X, FileText } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface User {
    id: number;
    name: string;
}

const props = defineProps<{
    project: Project;
    users: User[];
    features: Pick<ProjectFeature, 'id' | 'name' | 'module' | 'priority'>[];
    testCaseAttachments?: Attachment[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Bugreports', href: `/projects/${props.project.id}/bugreports` },
    { title: 'Create', href: `/projects/${props.project.id}/bugreports/create` },
];

const urlParams = new URLSearchParams(window.location.search);

const validSeverities = ['critical', 'major', 'minor', 'trivial'] as const;
const validPriorities = ['high', 'medium', 'low'] as const;

const parseSeverity = (): typeof validSeverities[number] => {
    const raw = urlParams.get('severity');
    return raw && (validSeverities as readonly string[]).includes(raw) ? raw as typeof validSeverities[number] : 'minor';
};

const parsePriority = (): typeof validPriorities[number] => {
    const raw = urlParams.get('priority');
    return raw && (validPriorities as readonly string[]).includes(raw) ? raw as typeof validPriorities[number] : 'medium';
};

const form = useForm({
    title: urlParams.get('title') || '',
    description: urlParams.get('description') || '',
    steps_to_reproduce: urlParams.get('steps_to_reproduce') || '',
    expected_result: urlParams.get('expected_result') || '',
    actual_result: urlParams.get('actual_result') || '',
    severity: parseSeverity(),
    priority: parsePriority(),
    status: 'new',
    environment: '',
    assigned_to: null as number | null,
    fixed_on: [] as string[],
    feature_ids: [] as number[],
    attachments: [] as File[],
    checklist_id: urlParams.get('checklist_id') || null as string | null,
    checklist_row_ids: urlParams.get('checklist_row_ids') || null as string | null,
    checklist_link_column: urlParams.get('checklist_link_column') || null as string | null,
    test_case_id: urlParams.get('test_case_id') || null as string | null,
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

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
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
    form.post(`/projects/${props.project.id}/bugreports`, {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Report Bug" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Bug class="h-5 w-5 text-primary" />
                            Report Bug
                        </CardTitle>
                        <CardDescription>
                            Create a new bug report for this project.
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
                                    placeholder="Brief description of the bug"
                                    :class="{ 'border-destructive': form.errors.title }"
                                />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Detailed description of the bug"
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
                                    placeholder="1. Go to...&#10;2. Click on...&#10;3. See error"
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
                                        placeholder="What should happen"
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
                                        placeholder="What actually happens"
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
                                    placeholder="e.g., Chrome 120, Windows 11, Production"
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

                            <!-- Test Case Attachments -->
                            <div v-if="testCaseAttachments?.length" class="space-y-2">
                                <Label class="flex items-center gap-1.5">
                                    <FileText class="h-3.5 w-3.5" />
                                    Attachments from Test Case
                                </Label>
                                <p class="text-xs text-muted-foreground">These files will be copied to the new bug report.</p>
                                <div class="space-y-2">
                                    <div v-for="attachment in testCaseAttachments" :key="attachment.id" class="flex items-center gap-2 rounded-lg border border-dashed p-2">
                                        <Paperclip class="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span class="truncate text-sm">{{ attachment.original_filename }}</span>
                                        <span class="shrink-0 text-xs text-muted-foreground">{{ formatFileSize(attachment.size) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Attachments -->
                            <div class="space-y-2">
                                <Label>Attachments</Label>
                                <div class="flex items-center gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="fileInput?.click()" class="gap-2">
                                        <Paperclip class="h-4 w-4" />
                                        Add Files
                                    </Button>
                                    <span class="text-xs text-muted-foreground">Max 10MB per file. JPG, PNG, GIF, WebP, PDF, DOC, XLS, TXT, CSV, ZIP</span>
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
                                <InputError :message="form.errors.attachments" />
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Create Bug Report
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/bugreports`)">
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
