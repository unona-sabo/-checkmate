<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import type { TestSuite, AIGeneratedTestCaseInput } from '@/types/checkmate';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Sparkles,
    Upload,
    FileText,
    Image,
    Check,
    X,
    Edit,
    Loader2,
    CheckCircle,
    AlertTriangle,
    Save,
    RefreshCw,
    Trash2,
    Clipboard,
} from 'lucide-vue-next';
import { ref, computed, useTemplateRef, onMounted, onUnmounted, watch } from 'vue';
import RestrictedAction from '@/components/RestrictedAction.vue';

const props = defineProps<{
    project: Project;
    testSuites: Pick<TestSuite, 'id' | 'name' | 'parent_id'>[];
    defaultProvider: string;
    hasGeminiKey: boolean;
    hasClaudeKey: boolean;
    hasOpenaiKey: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'AI Generator', href: `/projects/${props.project.id}/ai-generator` },
];

// Input state
type InputTab = 'text' | 'file' | 'image';
const activeTab = ref<InputTab>('text');
const textInput = ref('');
const fileInput = ref<File | null>(null);
const imageInput = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const countInput = ref('');
const provider = ref(localStorage.getItem('ai_provider') || props.defaultProvider);

watch(provider, (val) => {
    localStorage.setItem('ai_provider', val);
});
const customPrompt = ref('');
const language = ref('auto');

// File input refs
const fileInputRef = useTemplateRef<HTMLInputElement>('fileInputRef');
const imageInputRef = useTemplateRef<HTMLInputElement>('imageInputRef');

// Target suite
const targetMode = ref<'existing' | 'new'>('new');
const selectedSuiteId = ref<string>('');
const newSuiteName = ref('AI Generated Tests');

// Generation state
const isGenerating = ref(false);
const generationError = ref('');
const generatedCases = ref<AIGeneratedTestCaseInput[]>([]);
const generationId = ref<number | null>(null);
const generationProvider = ref('');
const generationModel = ref('');

// Regenerate state
const regeneratePrompt = ref('');
const isRegenerating = ref(false);

// Import state
const isImporting = ref(false);

// Editing state
const editingIndex = ref<number | null>(null);

const hasApiKey = computed(() => {
    if (provider.value === 'gemini') return props.hasGeminiKey;
    if (provider.value === 'claude') return props.hasClaudeKey;
    if (provider.value === 'openai') return props.hasOpenaiKey;
    return false;
});

const canGenerate = computed(() => {
    if (!hasApiKey.value) return false;
    if (isGenerating.value) return false;
    if (activeTab.value === 'text' && !textInput.value.trim()) return false;
    if (activeTab.value === 'file' && !fileInput.value) return false;
    if (activeTab.value === 'image' && !imageInput.value) return false;
    return true;
});

const approvedCases = computed(() => generatedCases.value.filter(c => c.approved));

const canImport = computed(() => {
    if (isImporting.value) return false;
    if (approvedCases.value.length === 0) return false;
    if (targetMode.value === 'existing' && !selectedSuiteId.value) return false;
    if (targetMode.value === 'new' && !newSuiteName.value.trim()) return false;
    return true;
});

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    fileInput.value = target.files?.[0] ?? null;
}

function clearFile() {
    fileInput.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function onImageChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    if (file) {
        setImage(file);
    } else {
        imageInput.value = null;
        imagePreview.value = null;
    }
}

function clearImage() {
    imageInput.value = null;
    imagePreview.value = null;
    if (imageInputRef.value) {
        imageInputRef.value.value = '';
    }
}

function setImage(file: File) {
    imageInput.value = file;
    const reader = new FileReader();
    reader.onload = (e) => {
        imagePreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
}

function handlePaste(event: ClipboardEvent) {
    const items = event.clipboardData?.items;
    if (!items) return;

    for (let i = 0; i < items.length; i++) {
        if (items[i].type.startsWith('image/')) {
            event.preventDefault();
            const file = items[i].getAsFile();
            if (!file) continue;

            if (file.size > 10 * 1024 * 1024) {
                generationError.value = 'Pasted image exceeds 10 MB limit.';
                return;
            }

            const ext = file.type.split('/')[1] || 'png';
            const named = new File([file], `screenshot-${Date.now()}.${ext}`, { type: file.type });
            setImage(named);

            // Auto-switch to image tab if not already there
            activeTab.value = 'image';
            return;
        }
    }
}

const isMac = computed(() => navigator.platform.toUpperCase().includes('MAC'));
const pasteShortcut = computed(() => (isMac.value ? 'Cmd' : 'Ctrl') + '+V');

onMounted(() => {
    document.addEventListener('paste', handlePaste);
});

onUnmounted(() => {
    document.removeEventListener('paste', handlePaste);
});

function buildFormData(): FormData {
    const formData = new FormData();
    formData.append('input_type', activeTab.value);
    formData.append('provider', provider.value);

    const countVal = countInput.value.trim();
    if (countVal && !isNaN(Number(countVal))) {
        formData.append('count', countVal);
    }

    if (customPrompt.value.trim()) {
        formData.append('custom_prompt', customPrompt.value.trim());
    }

    if (language.value && language.value !== 'auto') {
        formData.append('language', language.value);
    }

    if (activeTab.value === 'text') {
        formData.append('text', textInput.value);
    } else if (activeTab.value === 'file' && fileInput.value) {
        formData.append('file', fileInput.value);
    } else if (activeTab.value === 'image' && imageInput.value) {
        formData.append('image', imageInput.value);
    }

    return formData;
}

async function sendGenerateRequest(formData: FormData) {
    const response = await fetch(`/projects/${props.project.id}/ai-generator/generate`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            'Accept': 'application/json',
        },
        body: formData,
    });

    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(data.message || `Generation failed (${response.status})`);
    }

    return await response.json();
}

async function generate() {
    isGenerating.value = true;
    generationError.value = '';
    generatedCases.value = [];
    generationId.value = null;

    try {
        const data = await sendGenerateRequest(buildFormData());
        generatedCases.value = (data.test_cases || []).map((tc: AIGeneratedTestCaseInput) => ({
            ...tc,
            approved: true,
            editing: false,
        }));
        generationId.value = data.generation_id;
        generationProvider.value = data.provider;
        generationModel.value = data.model;
    } catch (error: unknown) {
        generationError.value = error instanceof Error ? error.message : 'An unexpected error occurred.';
    } finally {
        isGenerating.value = false;
    }
}

async function regenerate() {
    if (!regeneratePrompt.value.trim()) return;

    isRegenerating.value = true;
    generationError.value = '';

    const formData = buildFormData();

    // Combine existing custom prompt with regeneration instructions
    const combined = customPrompt.value.trim()
        ? `${customPrompt.value.trim()}\n\nADDITIONAL REFINEMENT:\n${regeneratePrompt.value.trim()}`
        : regeneratePrompt.value.trim();
    formData.set('custom_prompt', combined);

    try {
        const data = await sendGenerateRequest(formData);
        generatedCases.value = (data.test_cases || []).map((tc: AIGeneratedTestCaseInput) => ({
            ...tc,
            approved: true,
            editing: false,
        }));
        generationId.value = data.generation_id;
        generationProvider.value = data.provider;
        generationModel.value = data.model;
        regeneratePrompt.value = '';
    } catch (error: unknown) {
        generationError.value = error instanceof Error ? error.message : 'An unexpected error occurred.';
    } finally {
        isRegenerating.value = false;
    }
}

function toggleApproval(index: number) {
    generatedCases.value[index].approved = !generatedCases.value[index].approved;
}

function approveAll() {
    generatedCases.value.forEach(c => c.approved = true);
}

function rejectAll() {
    generatedCases.value.forEach(c => c.approved = false);
}

function startEditing(index: number) {
    editingIndex.value = index;
}

function stopEditing() {
    editingIndex.value = null;
}

function importCases() {
    if (!canImport.value) return;
    isImporting.value = true;

    const payload: Record<string, unknown> = {
        test_cases: approvedCases.value.map(({ title, description, preconditions, steps, expected_result, priority, severity, type, automation_status }) => ({
            title, description, preconditions, steps, expected_result, priority, severity, type, automation_status,
        })),
    };

    if (generationId.value) {
        payload.ai_generation_id = generationId.value;
    }

    if (targetMode.value === 'existing') {
        payload.test_suite_id = parseInt(selectedSuiteId.value);
    } else {
        payload.test_suite_name = newSuiteName.value;
    }

    router.post(`/projects/${props.project.id}/ai-generator/save`, payload, {
        onFinish: () => {
            isImporting.value = false;
        },
    });
}

const priorityColors: Record<string, string> = {
    critical: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    high: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    low: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
};

const severityColors: Record<string, string> = {
    blocker: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    critical: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    major: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    minor: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    trivial: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="AI Generator" />

        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                        <Sparkles class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">AI Test Case Generator</h1>
                        <p class="text-sm text-muted-foreground">
                            Generate test cases from documentation, files, or screenshots using AI
                        </p>
                    </div>
                </div>
                <div v-if="generationProvider" class="text-right text-xs text-muted-foreground">
                    <span>Provider: {{ generationProvider }}</span>
                    <span class="ml-2">Model: {{ generationModel }}</span>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Left Column: Input -->
                <div class="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-lg">Input</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Input type tabs -->
                            <div class="flex gap-1 rounded-lg bg-muted p-1">
                                <button
                                    v-for="tab in ([
                                        { key: 'text', label: 'Text', icon: FileText },
                                        { key: 'file', label: 'File', icon: Upload },
                                        { key: 'image', label: 'Image', icon: Image },
                                    ] as { key: InputTab; label: string; icon: typeof FileText }[])"
                                    :key="tab.key"
                                    class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                                    :class="activeTab === tab.key
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'"
                                    @click="activeTab = tab.key"
                                >
                                    <component :is="tab.icon" class="h-4 w-4" />
                                    {{ tab.label }}
                                </button>
                            </div>

                            <!-- Text input -->
                            <div v-if="activeTab === 'text'">
                                <Label>Documentation / Requirements</Label>
                                <Textarea
                                    v-model="textInput"
                                    placeholder="Paste your documentation, user stories, requirements, or feature descriptions here..."
                                    class="mt-1.5 min-h-[200px]"
                                />
                                <p class="mt-1.5 text-xs text-muted-foreground">
                                    {{ textInput.length.toLocaleString() }} characters
                                </p>
                            </div>

                            <!-- File input (hidden native input + custom drop zone) -->
                            <div v-if="activeTab === 'file'">
                                <Label>Upload TXT or Markdown file</Label>
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    accept=".txt,.md"
                                    class="hidden"
                                    @change="onFileChange"
                                />

                                <div
                                    v-if="!fileInput"
                                    class="mt-1.5 flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-muted-foreground/25 p-8 transition-colors hover:border-primary/50"
                                    @click="fileInputRef?.click()"
                                >
                                    <Upload class="h-8 w-8 text-muted-foreground/40" />
                                    <div class="text-center">
                                        <p class="text-sm font-medium">Drop file here or click to browse</p>
                                        <p class="mt-1 text-xs text-muted-foreground">TXT, MD (max 2 MB)</p>
                                    </div>
                                </div>

                                <div v-else class="mt-1.5 flex items-center justify-between rounded-lg border p-4">
                                    <div class="flex items-center gap-3">
                                        <FileText class="h-5 w-5 text-muted-foreground" />
                                        <div>
                                            <p class="text-sm font-medium">{{ fileInput.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ (fileInput.size / 1024).toFixed(1) }} KB</p>
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="sm" class="cursor-pointer text-destructive hover:text-destructive" @click="clearFile">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <!-- Image input (hidden native input + custom drop zone) -->
                            <div v-if="activeTab === 'image'">
                                <Label>Upload Screenshot</Label>
                                <input
                                    ref="imageInputRef"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="hidden"
                                    @change="onImageChange"
                                />

                                <div
                                    v-if="!imageInput"
                                    class="mt-1.5 flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-muted-foreground/25 p-8 transition-colors hover:border-primary/50"
                                    @click="imageInputRef?.click()"
                                >
                                    <Image class="h-8 w-8 text-muted-foreground/40" />
                                    <div class="text-center">
                                        <p class="text-sm font-medium">Drop screenshot here or click to browse</p>
                                        <div class="mt-2 flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
                                            <Clipboard class="h-3 w-3" />
                                            <span>or press</span>
                                            <kbd class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px]">{{ pasteShortcut }}</kbd>
                                            <span>to paste from clipboard</span>
                                        </div>
                                        <p class="mt-1.5 text-xs text-muted-foreground">JPG, PNG, WebP (max 10 MB)</p>
                                    </div>
                                </div>

                                <div v-else class="mt-1.5 space-y-3">
                                    <div v-if="imagePreview" class="overflow-hidden rounded-lg border">
                                        <img :src="imagePreview" alt="Preview" class="max-h-48 w-full object-contain" />
                                    </div>
                                    <div class="flex items-center justify-between rounded-lg border p-4">
                                        <div class="flex items-center gap-3">
                                            <Clipboard v-if="imageInput.name.startsWith('screenshot-')" class="h-5 w-5 text-muted-foreground" />
                                            <Image v-else class="h-5 w-5 text-muted-foreground" />
                                            <div>
                                                <p class="text-sm font-medium">
                                                    {{ imageInput.name.startsWith('screenshot-') ? 'Pasted from clipboard' : imageInput.name }}
                                                </p>
                                                <p class="text-xs text-muted-foreground">{{ (imageInput.size / 1024).toFixed(1) }} KB</p>
                                            </div>
                                        </div>
                                        <Button variant="ghost" size="sm" class="cursor-pointer text-destructive hover:text-destructive" @click="clearImage">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Options -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-lg">Options</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Custom Instructions -->
                            <div>
                                <Label>Custom Instructions</Label>
                                <Textarea
                                    v-model="customPrompt"
                                    placeholder="Add specific requirements or focus areas, e.g.:
- Focus on security testing
- Include edge cases for empty inputs
- Cover internationalization scenarios"
                                    class="mt-1.5 min-h-[140px]"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Optional. Customize what the AI focuses on when generating test cases.
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <Label>Number of test cases</Label>
                                    <Input
                                        v-model="countInput"
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="Auto (all possible)"
                                        class="mt-1.5"
                                    />
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Leave empty for auto, or enter 1-20.
                                    </p>
                                </div>
                                <div>
                                    <Label>AI Provider</Label>
                                    <Select v-model="provider">
                                        <SelectTrigger class="mt-1.5">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="gemini">
                                                Gemini {{ hasGeminiKey ? '' : '(no key)' }}
                                            </SelectItem>
                                            <SelectItem value="claude">
                                                Claude {{ hasClaudeKey ? '' : '(no key)' }}
                                            </SelectItem>
                                            <SelectItem value="openai">
                                                OpenAI {{ hasOpenaiKey ? '' : '(no key)' }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label>Language</Label>
                                    <Select v-model="language">
                                        <SelectTrigger class="mt-1.5">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="auto">Auto</SelectItem>
                                            <SelectItem value="English">English</SelectItem>
                                            <SelectItem value="Українська">Українська</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div v-if="!hasApiKey" class="flex items-center gap-2 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400">
                                <AlertTriangle class="h-4 w-4 shrink-0" />
                                <span>
                                    {{ provider === 'gemini' ? 'GEMINI_API_KEY' : provider === 'openai' ? 'OPENAI_API_KEY' : 'ANTHROPIC_API_KEY' }} is not configured in your .env file.
                                </span>
                            </div>

                            <RestrictedAction>
                                <Button
                                    class="w-full cursor-pointer"
                                    :disabled="!canGenerate"
                                    @click="generate"
                                >
                                    <Loader2 v-if="isGenerating" class="mr-2 h-4 w-4 animate-spin" />
                                    <Sparkles v-else class="mr-2 h-4 w-4" />
                                    {{ isGenerating ? 'Generating...' : 'Generate Test Cases' }}
                                </Button>
                            </RestrictedAction>
                        </CardContent>
                    </Card>

                    <!-- Target Suite -->
                    <Card v-if="generatedCases.length > 0">
                        <CardHeader>
                            <CardTitle class="text-lg">Target Test Suite</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex gap-1 rounded-lg bg-muted p-1">
                                <button
                                    class="flex flex-1 cursor-pointer items-center justify-center rounded-md px-3 py-2 text-sm font-medium transition-colors"
                                    :class="targetMode === 'new'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'"
                                    @click="targetMode = 'new'"
                                >
                                    New Suite
                                </button>
                                <button
                                    class="flex flex-1 cursor-pointer items-center justify-center rounded-md px-3 py-2 text-sm font-medium transition-colors"
                                    :class="targetMode === 'existing'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'"
                                    @click="targetMode = 'existing'"
                                >
                                    Existing Suite
                                </button>
                            </div>

                            <div v-if="targetMode === 'new'">
                                <Label>New Suite Name</Label>
                                <Input v-model="newSuiteName" class="mt-1.5" placeholder="e.g. AI Generated Tests" />
                            </div>

                            <div v-if="targetMode === 'existing'">
                                <Label>Select Test Suite</Label>
                                <Select v-model="selectedSuiteId">
                                    <SelectTrigger class="mt-1.5">
                                        <SelectValue placeholder="Select a test suite..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="suite in testSuites"
                                            :key="suite.id"
                                            :value="suite.id.toString()"
                                        >
                                            {{ suite.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column: Review -->
                <div class="flex flex-col gap-4">
                    <!-- Empty state -->
                    <Card v-if="generatedCases.length === 0 && !isGenerating && !generationError" class="flex flex-1 items-center justify-center">
                        <CardContent class="py-16 text-center">
                            <Sparkles class="mx-auto mb-4 h-12 w-12 text-muted-foreground/40" />
                            <p class="text-lg font-medium text-muted-foreground">No test cases generated yet</p>
                            <p class="mt-1 text-sm text-muted-foreground/70">
                                Provide input on the left and click Generate to get started.
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Loading state -->
                    <Card v-if="isGenerating" class="flex flex-1 items-center justify-center">
                        <CardContent class="py-16 text-center">
                            <Loader2 class="mx-auto mb-4 h-12 w-12 animate-spin text-purple-500" />
                            <p class="text-lg font-medium">Generating test cases...</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                This may take a moment depending on the input size.
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Error state -->
                    <Card v-if="generationError" class="border-red-200 dark:border-red-900">
                        <CardContent class="py-6">
                            <div class="flex items-start gap-3">
                                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-red-500" />
                                <div>
                                    <p class="font-medium text-red-800 dark:text-red-400">Generation Failed</p>
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400/80">{{ generationError }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Results -->
                    <template v-if="generatedCases.length > 0 && !isGenerating">
                        <!-- Regenerate panel -->
                        <Card class="border-blue-200 bg-blue-50/50 dark:border-blue-900 dark:bg-blue-950/20">
                            <CardContent class="py-4">
                                <Label class="text-blue-900 dark:text-blue-300">Not satisfied? Refine the results</Label>
                                <Textarea
                                    v-model="regeneratePrompt"
                                    placeholder="e.g. Focus more on negative test cases, add performance tests, include accessibility checks..."
                                    class="mt-1.5 min-h-[60px] border-blue-200 bg-white dark:border-blue-800 dark:bg-background"
                                    rows="2"
                                />
                                <Button
                                    variant="outline"
                                    class="mt-2 w-full cursor-pointer border-blue-300 text-blue-700 hover:bg-blue-100 dark:border-blue-700 dark:text-blue-300 dark:hover:bg-blue-950"
                                    :disabled="!regeneratePrompt.trim() || isRegenerating"
                                    @click="regenerate"
                                >
                                    <Loader2 v-if="isRegenerating" class="mr-2 h-4 w-4 animate-spin" />
                                    <RefreshCw v-else class="mr-2 h-4 w-4" />
                                    {{ isRegenerating ? 'Regenerating...' : 'Regenerate with Instructions' }}
                                </Button>
                            </CardContent>
                        </Card>

                        <!-- Results header -->
                        <Card>
                            <CardContent class="py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <CheckCircle class="h-5 w-5 text-green-500" />
                                        <span class="font-medium">
                                            {{ generatedCases.length }} test case{{ generatedCases.length !== 1 ? 's' : '' }} generated
                                        </span>
                                        <Badge variant="secondary" class="ml-1">
                                            {{ approvedCases.length }} approved
                                        </Badge>
                                    </div>
                                    <div class="flex gap-2">
                                        <Button variant="outline" size="sm" class="cursor-pointer" @click="approveAll">
                                            <Check class="mr-1 h-3 w-3" />
                                            All
                                        </Button>
                                        <Button variant="outline" size="sm" class="cursor-pointer" @click="rejectAll">
                                            <X class="mr-1 h-3 w-3" />
                                            None
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Test case cards -->
                        <div class="flex flex-col gap-3">
                            <Card
                                v-for="(tc, index) in generatedCases"
                                :key="index"
                                class="transition-colors"
                                :class="tc.approved ? '' : 'opacity-50'"
                            >
                                <CardContent class="py-4">
                                    <!-- Header row -->
                                    <div class="flex items-start gap-3">
                                        <Checkbox
                                            :model-value="tc.approved"
                                            class="mt-1 cursor-pointer"
                                            @update:model-value="toggleApproval(index)"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <!-- View mode -->
                                            <template v-if="editingIndex !== index">
                                                <div class="flex items-start justify-between gap-2">
                                                    <h3 class="font-medium leading-snug">{{ tc.title }}</h3>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="shrink-0 cursor-pointer"
                                                        @click="startEditing(index)"
                                                    >
                                                        <Edit class="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>

                                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                    <span
                                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                        :class="priorityColors[tc.priority] || priorityColors.medium"
                                                    >
                                                        {{ tc.priority }}
                                                    </span>
                                                    <span
                                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                        :class="severityColors[tc.severity] || severityColors.major"
                                                    >
                                                        {{ tc.severity }}
                                                    </span>
                                                    <Badge variant="outline" class="text-xs">{{ tc.type }}</Badge>
                                                </div>

                                                <p v-if="tc.description" class="mt-2 text-sm text-muted-foreground">
                                                    {{ tc.description }}
                                                </p>

                                                <div v-if="tc.preconditions" class="mt-2">
                                                    <p class="text-xs font-medium text-muted-foreground">Preconditions</p>
                                                    <p class="text-sm">{{ tc.preconditions }}</p>
                                                </div>

                                                <div v-if="tc.steps" class="mt-2">
                                                    <p class="text-xs font-medium text-muted-foreground">Steps</p>
                                                    <pre class="mt-0.5 whitespace-pre-wrap text-sm">{{ tc.steps }}</pre>
                                                </div>

                                                <div v-if="tc.expected_result" class="mt-2">
                                                    <p class="text-xs font-medium text-muted-foreground">Expected Result</p>
                                                    <p class="text-sm">{{ tc.expected_result }}</p>
                                                </div>
                                            </template>

                                            <!-- Edit mode -->
                                            <template v-else>
                                                <div class="space-y-3">
                                                    <div>
                                                        <Label class="text-xs">Title</Label>
                                                        <Input v-model="tc.title" class="mt-1" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Description</Label>
                                                        <Textarea v-model="tc.description" class="mt-1" rows="2" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Preconditions</Label>
                                                        <Input v-model="tc.preconditions" class="mt-1" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Steps</Label>
                                                        <Textarea v-model="tc.steps" class="mt-1" rows="4" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Expected Result</Label>
                                                        <Textarea v-model="tc.expected_result" class="mt-1" rows="2" />
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-3">
                                                        <div>
                                                            <Label class="text-xs">Priority</Label>
                                                            <Select v-model="tc.priority">
                                                                <SelectTrigger class="mt-1">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="critical">Critical</SelectItem>
                                                                    <SelectItem value="high">High</SelectItem>
                                                                    <SelectItem value="medium">Medium</SelectItem>
                                                                    <SelectItem value="low">Low</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div>
                                                            <Label class="text-xs">Severity</Label>
                                                            <Select v-model="tc.severity">
                                                                <SelectTrigger class="mt-1">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="blocker">Blocker</SelectItem>
                                                                    <SelectItem value="critical">Critical</SelectItem>
                                                                    <SelectItem value="major">Major</SelectItem>
                                                                    <SelectItem value="minor">Minor</SelectItem>
                                                                    <SelectItem value="trivial">Trivial</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-3">
                                                        <div>
                                                            <Label class="text-xs">Type</Label>
                                                            <Select v-model="tc.type">
                                                                <SelectTrigger class="mt-1">
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
                                                                    <SelectItem value="exploratory">Exploratory</SelectItem>
                                                                    <SelectItem value="other">Other</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div>
                                                            <Label class="text-xs">Automation Status</Label>
                                                            <Select v-model="tc.automation_status">
                                                                <SelectTrigger class="mt-1">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="not_automated">Not Automated</SelectItem>
                                                                    <SelectItem value="automated">Automated</SelectItem>
                                                                    <SelectItem value="in_progress">In Progress</SelectItem>
                                                                    <SelectItem value="cannot_automate">Cannot Automate</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </div>
                                                    <div class="flex justify-end">
                                                        <Button size="sm" class="cursor-pointer" @click="stopEditing">
                                                            <Check class="mr-1 h-3.5 w-3.5" />
                                                            Done
                                                        </Button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Import button -->
                        <RestrictedAction>
                            <Button
                                class="w-full cursor-pointer"
                                size="lg"
                                :disabled="!canImport"
                                @click="importCases"
                            >
                                <Loader2 v-if="isImporting" class="mr-2 h-4 w-4 animate-spin" />
                                <Save v-else class="mr-2 h-4 w-4" />
                                {{ isImporting ? 'Importing...' : `Import ${approvedCases.length} Test Case${approvedCases.length !== 1 ? 's' : ''}` }}
                            </Button>
                        </RestrictedAction>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
