<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project, type TestSuite, type TestStep, type Attachment } from '@/types';
import { type ProjectFeature } from '@/types/checkmate';
import FeatureSelector from '@/components/FeatureSelector.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { useClearErrorsOnInput } from '@/composables/useClearErrorsOnInput';
import { FileText, Plus, Trash2, Paperclip, X, Bug } from 'lucide-vue-next';
import { ref } from 'vue';

const MODULE_OPTIONS = ['UI', 'API', 'Backend', 'Database', 'Integration'] as const;

const props = defineProps<{
    project: Project;
    testSuite: TestSuite;
    features: Pick<ProjectFeature, 'id' | 'name' | 'module' | 'priority'>[];
    bugreportAttachments?: Attachment[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Test Suites', href: `/projects/${props.project.id}/test-suites` },
    { title: props.testSuite.name, href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}` },
    { title: 'Create Test Case', href: `/projects/${props.project.id}/test-suites/${props.testSuite.id}/test-cases/create` },
];

const urlParams = new URLSearchParams(window.location.search);

const parseStepsParam = (): TestStep[] => {
    const raw = urlParams.get('steps');
    if (!raw) return [];
    try {
        const parsed = JSON.parse(raw) as TestStep[];
        return parsed.length > 0 ? parsed : [];
    } catch {
        return [];
    }
};

const validPriorities = ['low', 'medium', 'high', 'critical'] as const;
const validSeverities = ['trivial', 'minor', 'major', 'critical', 'blocker'] as const;

const parsePriority = (): typeof validPriorities[number] => {
    const raw = urlParams.get('priority');
    return raw && (validPriorities as readonly string[]).includes(raw) ? raw as typeof validPriorities[number] : 'medium';
};

const parseSeverity = (): typeof validSeverities[number] => {
    const raw = urlParams.get('severity');
    return raw && (validSeverities as readonly string[]).includes(raw) ? raw as typeof validSeverities[number] : 'major';
};

const form = useForm({
    title: urlParams.get('title') || '',
    description: urlParams.get('description') || '',
    preconditions: urlParams.get('preconditions') || '',
    steps: parseStepsParam(),
    expected_result: urlParams.get('expected_result') || '',
    priority: parsePriority(),
    severity: parseSeverity(),
    type: 'functional' as const,
    automation_status: 'not_automated' as const,
    module: (props.testSuite.module ?? []) as string[],
    tags: [] as string[],
    feature_ids: [] as number[],
    attachments: [] as File[],
    checklist_id: urlParams.get('checklist_id') || null as string | null,
    checklist_row_ids: urlParams.get('checklist_row_ids') || null as string | null,
    checklist_link_column: urlParams.get('checklist_link_column') || null as string | null,
    bugreport_id: urlParams.get('bugreport_id') || null as string | null,
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

const addStep = () => {
    form.steps.push({ action: '', expected: '' });
};

const removeStep = (index: number) => {
    form.steps.splice(index, 1);
};

const submit = () => {
    form.post(`/projects/${props.project.id}/test-suites/${props.testSuite.id}/test-cases`, {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Create Test Case" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-3xl">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5 text-primary" />
                            Create Test Case
                        </CardTitle>
                        <CardDescription>
                            Add a new test case to "{{ testSuite.name }}"
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
                                    placeholder="e.g., Verify user can login with valid credentials"
                                    :class="{ 'border-destructive': form.errors.title }"
                                />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Describe what this test case verifies..."
                                    rows="2"
                                    autoResize
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="preconditions">Preconditions</Label>
                                <Textarea
                                    id="preconditions"
                                    v-model="form.preconditions"
                                    placeholder="What must be true before running this test..."
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
                                <Textarea
                                    id="expected_result"
                                    v-model="form.expected_result"
                                    placeholder="What should happen when all steps are completed..."
                                    rows="2"
                                    autoResize
                                />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>Priority</Label>
                                    <Select v-model="form.priority">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
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
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
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
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
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
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
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

                            <!-- Bug Report Attachments -->
                            <div v-if="bugreportAttachments?.length" class="space-y-2">
                                <Label class="flex items-center gap-1.5">
                                    <Bug class="h-3.5 w-3.5" />
                                    Attachments from Bug Report
                                </Label>
                                <p class="text-xs text-muted-foreground">These files will be copied to the new test case.</p>
                                <div class="space-y-2">
                                    <div v-for="attachment in bugreportAttachments" :key="attachment.id" class="flex items-center gap-2 rounded-lg border border-dashed p-2">
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
                                    Create Test Case
                                </Button>
                                <Button type="button" variant="outline" @click="$inertia.visit(`/projects/${project.id}/test-suites/${testSuite.id}`)">
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
