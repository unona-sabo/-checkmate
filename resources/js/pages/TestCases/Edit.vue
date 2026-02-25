<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite, type TestCase, type TestStep, type Attachment } from '@/types';
import { type ProjectFeature } from '@/types/checkmate';
import FeatureSelector from '@/components/FeatureSelector.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { useClearErrorsOnInput } from '@/composables/useClearErrorsOnInput';
import { Edit, Plus, Trash2, Paperclip, X, Download } from 'lucide-vue-next';
import { ref } from 'vue';

const MODULE_OPTIONS = ['UI', 'API', 'Backend', 'Database', 'Integration'] as const;

const props = defineProps<{
    project: Project;
    testSuite: TestSuite;
    testCase: TestCase;
    features: Pick<ProjectFeature, 'id' | 'name' | 'module' | 'priority'>[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
    { title: props.testSuite.name, href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}` },
    { title: props.testCase.title, href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}/test-cases/${props.testCase.id}` },
    { title: 'Edit', href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}/test-cases/${props.testCase.id}/edit` },
];

const form = useForm({
    _method: 'put',
    title: props.testCase.title,
    description: props.testCase.description || '',
    preconditions: props.testCase.preconditions || '',
    steps: (props.testCase.steps ?? []) as TestStep[],
    expected_result: props.testCase.expected_result || '',
    priority: props.testCase.priority,
    severity: props.testCase.severity,
    type: props.testCase.type,
    automation_status: props.testCase.automation_status,
    module: props.testCase.module ?? [] as string[],
    tags: props.testCase.tags || [],
    feature_ids: (props.testCase.project_features ?? []).map(f => f.id),
    attachments: [] as File[],
});
useClearErrorsOnInput(form);

const showDeleteDialog = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const addStep = () => {
    form.steps.push({ action: '', expected: '' });
};

const removeStep = (index: number) => {
    form.steps.splice(index, 1);
};

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

const isImage = (mimeType: string): boolean => {
    return mimeType.startsWith('image/');
};

const deleteAttachment = (attachmentId: number) => {
    router.delete(`/projects/${props.project.id}/test-suites/${props.testSuite.id}/test-cases/${props.testCase.id}/attachments/${attachmentId}`, {
        preserveScroll: true,
    });
};

const submit = () => {
    form.post(`/projects/${props.project.id}/test-suites/${props.testSuite.id}/test-cases/${props.testCase.id}`, {
        forceFormData: true,
    });
};

const deleteTestCase = () => {
    router.delete(`/projects/${props.project.id}/test-suites/${props.testSuite.id}/test-cases/${props.testCase.id}`);
};
</script>

<template>
    <Head title="Edit Test Case" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-3xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Edit class="h-5 w-5 text-primary" />
                            Edit Test Case
                        </CardTitle>
                        <CardDescription>
                            Update test case details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="space-y-2">
                                <Label for="title">Title</Label>
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
                                    rows="2"
                                    autoResize
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="preconditions">Preconditions</Label>
                                <Textarea
                                    id="preconditions"
                                    v-model="form.preconditions"
                                    rows="2"
                                    autoResize
                                />
                            </div>

                            <!-- Test Steps -->
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>Test Steps</Label>
                                    <Button type="button" variant="outline" size="sm" @click="addStep" class="gap-1">
                                        <Plus class="h-3 w-3" />
                                        Add Step
                                    </Button>
                                </div>
                                <p v-if="form.steps.length === 0" class="text-sm text-muted-foreground">No steps added yet.</p>
                                <div v-for="(step, index) in form.steps" :key="index" class="flex gap-2 rounded-lg border p-3">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-medium text-primary-foreground shrink-0">
                                        {{ index + 1 }}
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <Textarea
                                            v-model="step.action"
                                            placeholder="Action to perform..."
                                            rows="1"
                                            class="min-h-[36px]"
                                            autoResize
                                        />
                                        <Textarea
                                            v-model="step.expected"
                                            placeholder="Expected result (optional)..."
                                            rows="1"
                                            class="min-h-[36px]"
                                            autoResize
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        @click="removeStep(index)"
                                        class="p-0 text-muted-foreground hover:text-destructive shrink-0"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="expected_result">Expected Result</Label>
                                <Textarea id="expected_result" v-model="form.expected_result" rows="2" autoResize />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>Priority</Label>
                                    <Select v-model="form.priority">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="low">Low</SelectItem>
                                            <SelectItem value="medium">Medium</SelectItem>
                                            <SelectItem value="high">High</SelectItem>
                                            <SelectItem value="critical">Critical</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label>Severity</Label>
                                    <Select v-model="form.severity">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="trivial">Trivial</SelectItem>
                                            <SelectItem value="minor">Minor</SelectItem>
                                            <SelectItem value="major">Major</SelectItem>
                                            <SelectItem value="critical">Critical</SelectItem>
                                            <SelectItem value="blocker">Blocker</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label>Type</Label>
                                    <Select v-model="form.type">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
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
                                </div>

                                <div class="space-y-2">
                                    <Label>Automation Status</Label>
                                    <Select v-model="form.automation_status">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="not_automated">Not Automated</SelectItem>
                                            <SelectItem value="to_be_automated">To Be Automated</SelectItem>
                                            <SelectItem value="automated">Automated</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
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

                            <!-- Features -->
                            <FeatureSelector
                                v-model="form.feature_ids"
                                :features="features"
                                :project-id="project.id"
                            />

                            <!-- Existing Attachments -->
                            <div v-if="testCase.attachments?.length" class="space-y-2">
                                <Label>Current Attachments</Label>
                                <div class="space-y-2">
                                    <div v-for="attachment in testCase.attachments" :key="attachment.id" class="flex items-center justify-between rounded-lg border p-2">
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
                                <InputError :message="form.errors.attachments" />
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">Save Changes</Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/test-suites/${testSuite.id}/test-cases/${testCase.id}`)">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card class="border-destructive/50">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-destructive">
                            <Trash2 class="h-5 w-5" />
                            Danger Zone
                        </CardTitle>
                        <CardDescription>Permanently delete this test case.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Dialog v-model:open="showDeleteDialog">
                            <DialogTrigger as-child>
                                <Button variant="destructive">Delete Test Case</Button>
                            </DialogTrigger>
                            <DialogContent class="max-w-sm">
                                <DialogHeader>
                                    <DialogTitle>Delete Test Case?</DialogTitle>
                                    <DialogDescription>
                                        Are you sure you want to delete "{{ testCase.title }}"? This action cannot be undone.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="flex gap-4 sm:justify-end">
                                    <Button variant="secondary" @click="showDeleteDialog = false" class="flex-1 sm:flex-none">
                                        No
                                    </Button>
                                    <Button variant="destructive" @click="deleteTestCase" class="flex-1 sm:flex-none">
                                        Yes
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
