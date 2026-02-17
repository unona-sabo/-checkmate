<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';
import { Head, router, Deferred } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Project } from '@/types';
import type {
    AutomationTestResult,
    AutomationScanResult,
    AutomationScanTest,
    AutomationRunStats,
    TestEnvironment,
    TestRunTemplate,
    CursorPagination,
} from '@/types/checkmate';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Drama,
    Settings,
    Search,
    Play,
    History,
    FolderSearch,
    CheckCircle,
    XCircle,
    SkipForward,
    Clock,
    Link,
    Loader2,
    AlertTriangle,
    FileCode,
    X,
    Trash2,
    Plus,
    Pencil,
    Globe,
    BookTemplate,
    Tag,
    Server,
} from 'lucide-vue-next';
import RestrictedAction from '@/components/RestrictedAction.vue';

const props = defineProps<{
    project: Project & { automation_tests_path?: string | null };
    recentResults: CursorPagination<AutomationTestResult>;
    latestRunStats: AutomationRunStats | Record<string, never>;
    environments?: TestEnvironment[];
    templates?: TestRunTemplate[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: props.project.name, href: `/projects/${props.project.id}` },
    { title: 'Automation', href: `/projects/${props.project.id}/automation` },
];

// Tabs
type TabKey = 'config' | 'run' | 'templates' | 'environments' | 'history' | 'discover';
const activeTab = ref<TabKey>(props.project.automation_tests_path ? 'run' : 'config');
const tabs: { key: TabKey; label: string; icon: typeof Settings }[] = [
    { key: 'config', label: 'Configuration', icon: Settings },
    { key: 'discover', label: 'Discover Tests', icon: FolderSearch },
    { key: 'environments', label: 'Environments', icon: Globe },
    { key: 'templates', label: 'Templates', icon: BookTemplate },
    { key: 'run', label: 'Run Tests', icon: Play },
    { key: 'history', label: 'Results History', icon: History },
];

// ========== Config ==========
const configPath = ref(props.project.automation_tests_path || '');
const savingConfig = ref(false);

const saveConfig = () => {
    savingConfig.value = true;
    router.put(`/projects/${props.project.id}/automation/config`, {
        automation_tests_path: configPath.value,
    }, {
        preserveScroll: true,
        onFinish: () => { savingConfig.value = false; },
    });
};

// ========== Scan / Discover ==========
const isScanning = ref(false);
const scanResults = ref<AutomationScanResult | null>(null);
const scanError = ref('');

const scanTests = async () => {
    isScanning.value = true;
    scanError.value = '';
    try {
        const { data } = await axios.get(`/projects/${props.project.id}/automation/scan`);
        if (data.error) {
            scanError.value = data.error;
        } else {
            scanResults.value = data;
        }
    } catch (error: any) {
        scanError.value = error.response?.data?.error || 'Failed to scan tests';
    } finally {
        isScanning.value = false;
    }
};

// ========== Run Tests ==========
const isRunning = ref(false);
const runError = ref('');
const runMessage = ref('');
const runFile = ref('');
const runEnvironmentId = ref<string>('');
const runTags = ref<string[]>([]);
const runTagMode = ref<string>('or');
const tagInput = ref('');

const addRunTag = () => {
    const tag = tagInput.value.trim();
    if (tag && !runTags.value.includes(tag)) {
        runTags.value.push(tag.startsWith('@') ? tag : `@${tag}`);
    }
    tagInput.value = '';
};

const removeRunTag = (tag: string) => {
    runTags.value = runTags.value.filter((t) => t !== tag);
};

const runTests = async () => {
    isRunning.value = true;
    runError.value = '';
    runMessage.value = '';
    try {
        const { data } = await axios.post(`/projects/${props.project.id}/automation/run`, {
            file: runFile.value.trim() || null,
            environment_id: runEnvironmentId.value ? Number(runEnvironmentId.value) : null,
            tags: runTags.value.length > 0 ? runTags.value : null,
            tag_mode: runTagMode.value,
        });
        if (data.error) {
            runError.value = data.error;
        } else {
            runMessage.value = data.message;
            router.reload({ only: ['recentResults', 'latestRunStats'] });
        }
    } catch (error: any) {
        runError.value = error.response?.data?.error || error.response?.data?.message || 'Failed to run tests';
    } finally {
        isRunning.value = false;
    }
};

const runFromTemplate = async (template: TestRunTemplate) => {
    isRunning.value = true;
    runError.value = '';
    runMessage.value = '';
    activeTab.value = 'run';
    try {
        const { data } = await axios.post(`/projects/${props.project.id}/automation/run`, {
            template_id: template.id,
        });
        if (data.error) {
            runError.value = data.error;
        } else {
            runMessage.value = data.message;
            router.reload({ only: ['recentResults', 'latestRunStats'] });
        }
    } catch (error: any) {
        runError.value = error.response?.data?.error || error.response?.data?.message || 'Failed to run tests';
    } finally {
        isRunning.value = false;
    }
};

// ========== Environments ==========
const showEnvDialog = ref(false);
const editingEnv = ref<TestEnvironment | null>(null);
const envForm = ref({
    name: '',
    base_url: '',
    workers: 1,
    retries: 0,
    browser: 'chromium',
    headed: false,
    timeout: 30000,
    description: '',
    is_default: false,
    variables: {} as Record<string, string>,
});
const envVarKey = ref('');
const envVarValue = ref('');

const openEnvDialog = (env?: TestEnvironment) => {
    if (env) {
        editingEnv.value = env;
        envForm.value = {
            name: env.name,
            base_url: env.base_url || '',
            workers: env.workers,
            retries: env.retries,
            browser: env.browser,
            headed: env.headed,
            timeout: env.timeout,
            description: env.description || '',
            is_default: env.is_default,
            variables: env.variables ? { ...env.variables } : {},
        };
    } else {
        editingEnv.value = null;
        envForm.value = {
            name: '',
            base_url: '',
            workers: 1,
            retries: 0,
            browser: 'chromium',
            headed: false,
            timeout: 30000,
            description: '',
            is_default: false,
            variables: {},
        };
    }
    showEnvDialog.value = true;
};

const addEnvVar = () => {
    const key = envVarKey.value.trim();
    if (key) {
        envForm.value.variables[key] = envVarValue.value;
        envVarKey.value = '';
        envVarValue.value = '';
    }
};

const removeEnvVar = (key: string) => {
    delete envForm.value.variables[key];
};

const saveEnv = () => {
    const data = {
        ...envForm.value,
        variables: Object.keys(envForm.value.variables).length > 0 ? envForm.value.variables : null,
    };

    if (editingEnv.value) {
        router.put(`/projects/${props.project.id}/automation/environments/${editingEnv.value.id}`, data, {
            preserveScroll: true,
            onSuccess: () => { showEnvDialog.value = false; },
        });
    } else {
        router.post(`/projects/${props.project.id}/automation/environments`, data, {
            preserveScroll: true,
            onSuccess: () => { showEnvDialog.value = false; },
        });
    }
};

const showDeleteEnvDialog = ref(false);
const deletingEnv = ref<TestEnvironment | null>(null);

const confirmDeleteEnv = (env: TestEnvironment) => {
    deletingEnv.value = env;
    showDeleteEnvDialog.value = true;
};

const deleteEnv = () => {
    if (!deletingEnv.value) return;
    router.delete(`/projects/${props.project.id}/automation/environments/${deletingEnv.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { showDeleteEnvDialog.value = false; },
    });
};

// ========== Templates ==========
const showTemplateDialog = ref(false);
const editingTemplate = ref<TestRunTemplate | null>(null);
const templateForm = ref({
    name: '',
    description: '',
    environment_id: '' as string,
    tags: [] as string[],
    tag_mode: 'or',
    file_pattern: '',
});
const templateTagInput = ref('');

const openTemplateDialog = (tmpl?: TestRunTemplate) => {
    if (tmpl) {
        editingTemplate.value = tmpl;
        templateForm.value = {
            name: tmpl.name,
            description: tmpl.description || '',
            environment_id: tmpl.environment_id ? String(tmpl.environment_id) : '',
            tags: tmpl.tags ? [...tmpl.tags] : [],
            tag_mode: tmpl.tag_mode,
            file_pattern: tmpl.file_pattern || '',
        };
    } else {
        editingTemplate.value = null;
        templateForm.value = {
            name: '',
            description: '',
            environment_id: '',
            tags: [],
            tag_mode: 'or',
            file_pattern: '',
        };
    }
    showTemplateDialog.value = true;
};

const addTemplateTag = () => {
    const tag = templateTagInput.value.trim();
    if (tag && !templateForm.value.tags.includes(tag)) {
        templateForm.value.tags.push(tag.startsWith('@') ? tag : `@${tag}`);
    }
    templateTagInput.value = '';
};

const removeTemplateTag = (tag: string) => {
    templateForm.value.tags = templateForm.value.tags.filter((t) => t !== tag);
};

const saveTemplate = () => {
    const data = {
        ...templateForm.value,
        environment_id: templateForm.value.environment_id ? Number(templateForm.value.environment_id) : null,
        tags: templateForm.value.tags.length > 0 ? templateForm.value.tags : null,
    };

    if (editingTemplate.value) {
        router.put(`/projects/${props.project.id}/automation/templates/${editingTemplate.value.id}`, data, {
            preserveScroll: true,
            onSuccess: () => { showTemplateDialog.value = false; },
        });
    } else {
        router.post(`/projects/${props.project.id}/automation/templates`, data, {
            preserveScroll: true,
            onSuccess: () => { showTemplateDialog.value = false; },
        });
    }
};

const showDeleteTemplateDialog = ref(false);
const deletingTemplate = ref<TestRunTemplate | null>(null);

const confirmDeleteTemplate = (tmpl: TestRunTemplate) => {
    deletingTemplate.value = tmpl;
    showDeleteTemplateDialog.value = true;
};

const deleteTemplate = () => {
    if (!deletingTemplate.value) return;
    router.delete(`/projects/${props.project.id}/automation/templates/${deletingTemplate.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { showDeleteTemplateDialog.value = false; },
    });
};

// ========== Link dialog ==========
const showLinkDialog = ref(false);
const linkFile = ref('');
const linkTestName = ref('');

const openLinkDialog = (file: string, testName: string) => {
    linkFile.value = file;
    linkTestName.value = testName;
    showLinkDialog.value = true;
};

// ========== History ==========
const historySearch = ref('');
const filteredResults = computed(() => {
    if (!historySearch.value.trim()) return props.recentResults.data;
    const q = historySearch.value.toLowerCase();
    return props.recentResults.data.filter(
        (r) => r.test_name.toLowerCase().includes(q) || r.test_file.toLowerCase().includes(q),
    );
});

const loadingMore = ref(false);
const loadMore = () => {
    if (!props.recentResults.next_cursor) return;
    loadingMore.value = true;
    router.reload({
        data: { cursor: props.recentResults.next_cursor },
        only: ['recentResults'],
        preserveState: true,
        onFinish: () => { loadingMore.value = false; },
    });
};

// ========== Clear results ==========
const showClearDialog = ref(false);
const clearResults = () => {
    router.delete(`/projects/${props.project.id}/automation/clear-results`, {
        preserveScroll: true,
        onSuccess: () => { showClearDialog.value = false; },
    });
};

// ========== Remove test / file from scan results ==========
const removeTest = (fileIndex: number, test: AutomationScanTest) => {
    if (!scanResults.value) return;
    const file = scanResults.value.files[fileIndex];
    file.tests = file.tests.filter((t) => t.full_name !== test.full_name);
    scanResults.value.total_tests--;
    if (file.tests.length === 0 && file.skipped_tests.length === 0) {
        scanResults.value.files.splice(fileIndex, 1);
        scanResults.value.total_files--;
    }
};

const removeFile = (fileIndex: number) => {
    if (!scanResults.value) return;
    const file = scanResults.value.files[fileIndex];
    scanResults.value.total_tests -= file.tests.length;
    scanResults.value.total_files--;
    scanResults.value.files.splice(fileIndex, 1);
};

// ========== Helpers ==========
const getStatusIcon = (status: string) => {
    if (status === 'passed') return CheckCircle;
    if (status === 'failed') return XCircle;
    if (status === 'skipped') return SkipForward;
    return Clock;
};

const getStatusColor = (status: string): string => {
    if (status === 'passed') return 'text-emerald-500';
    if (status === 'failed') return 'text-red-500';
    if (status === 'skipped') return 'text-muted-foreground';
    return 'text-amber-500';
};

const getStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'passed') return 'default';
    if (status === 'failed') return 'destructive';
    return 'secondary';
};

const formatDuration = (ms: number): string => {
    if (ms < 1000) return `${ms}ms`;
    return `${(ms / 1000).toFixed(1)}s`;
};

const formatDate = (date: string): string => {
    return new Date(date).toLocaleString('en-US', {
        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};

const passRate = computed(() => {
    const stats = props.latestRunStats;
    if (!stats || !stats.total) return 0;
    return Math.round((stats.passed / stats.total) * 100);
});
</script>

<template>
    <Head :title="`Automation - ${project.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 px-[150px] py-6">
            <!-- Header -->
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                    <Drama class="h-6 w-6 shrink-0 text-primary" />
                    Test Automation
                </h1>
                <p class="text-muted-foreground">Playwright integration with tags and environments</p>
            </div>

            <!-- Stats Cards (if we have results) -->
            <div v-if="latestRunStats && latestRunStats.total" class="grid grid-cols-4 gap-4">
                <Card>
                    <CardContent class="px-4 py-3">
                        <div class="text-xs text-muted-foreground">Total Tests</div>
                        <div class="text-2xl font-bold">{{ latestRunStats.total }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="px-4 py-3">
                        <div class="text-xs text-muted-foreground">Passed</div>
                        <div class="text-2xl font-bold text-emerald-500">{{ latestRunStats.passed }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="px-4 py-3">
                        <div class="text-xs text-muted-foreground">Failed</div>
                        <div class="text-2xl font-bold text-red-500">{{ latestRunStats.failed }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="px-4 py-3">
                        <div class="text-xs text-muted-foreground">Pass Rate</div>
                        <div class="text-2xl font-bold text-primary">{{ passRate }}%</div>
                        <Progress :model-value="passRate" class="mt-1 h-1.5" />
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs -->
            <Card>
                <div class="border-b">
                    <nav class="flex gap-0 overflow-x-auto px-4" aria-label="Tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            class="flex cursor-pointer items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
                            :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:border-muted-foreground/30 hover:text-foreground'"
                        >
                            <component :is="tab.icon" class="h-4 w-4" />
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <!-- ==================== Configuration Tab ==================== -->
                <div v-if="activeTab === 'config'" class="space-y-6 p-6">
                    <div class="max-w-xl space-y-4">
                        <div>
                            <Label>Path to Playwright Tests</Label>
                            <Input
                                v-model="configPath"
                                placeholder="C:\AutotestMilx\milx-qa"
                                class="mt-1"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                Full path to your Playwright project folder
                            </p>
                        </div>
                        <RestrictedAction>
                            <Button
                                variant="cta"
                                @click="saveConfig"
                                :disabled="savingConfig || !configPath.trim()"
                                class="cursor-pointer"
                            >
                                <Loader2 v-if="savingConfig" class="mr-2 h-4 w-4 animate-spin" />
                                Save Configuration
                            </Button>
                        </RestrictedAction>
                        <div v-if="project.automation_tests_path" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950">
                            <p class="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-300">
                                <CheckCircle class="h-4 w-4" />
                                Configured: {{ project.automation_tests_path }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ==================== Run Tests Tab ==================== -->
                <div v-if="activeTab === 'run'" class="space-y-6 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold">Run Playwright Tests</h3>
                            <p class="text-xs text-muted-foreground">Execute tests with optional environment and tag filters</p>
                        </div>
                    </div>

                    <div v-if="!project.automation_tests_path" class="rounded-md border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950">
                        <p class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-300">
                            <AlertTriangle class="h-4 w-4" />
                            Configure the tests path first in the Configuration tab.
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Specific file (optional)</Label>
                                <Input
                                    v-model="runFile"
                                    placeholder="e.g. tests/login.spec.ts"
                                    class="mt-1"
                                />
                            </div>
                            <Deferred data="environments">
                                <template #fallback>
                                    <div>
                                        <Label>Environment</Label>
                                        <div class="mt-1 h-9 w-full animate-pulse rounded-md bg-muted" />
                                    </div>
                                </template>
                                <div>
                                    <Label>Environment</Label>
                                    <Select v-model="runEnvironmentId">
                                        <SelectTrigger class="mt-1 cursor-pointer bg-background/60">
                                            <SelectValue placeholder="Default (no env)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="" class="cursor-pointer">Default (no env)</SelectItem>
                                            <SelectItem
                                                v-for="env in environments"
                                                :key="env.id"
                                                :value="String(env.id)"
                                                class="cursor-pointer"
                                            >
                                                {{ env.name }}
                                                <span v-if="env.is_default" class="text-xs text-muted-foreground ml-1">(default)</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </Deferred>
                        </div>

                        <!-- Tags filter -->
                        <div>
                            <Label>Tags filter (optional)</Label>
                            <div class="mt-1 flex items-center gap-2">
                                <Input
                                    v-model="tagInput"
                                    placeholder="@smoke"
                                    class="max-w-48"
                                    @keydown.enter.prevent="addRunTag"
                                />
                                <Button variant="outline" size="sm" @click="addRunTag" class="cursor-pointer">
                                    <Plus class="mr-1 h-3 w-3" />Add
                                </Button>
                                <Select v-model="runTagMode">
                                    <SelectTrigger class="w-24 cursor-pointer bg-background/60">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="or" class="cursor-pointer">OR</SelectItem>
                                        <SelectItem value="and" class="cursor-pointer">AND</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div v-if="runTags.length" class="mt-2 flex flex-wrap gap-1">
                                <Badge
                                    v-for="tag in runTags"
                                    :key="tag"
                                    variant="secondary"
                                    class="cursor-pointer gap-1"
                                    @click="removeRunTag(tag)"
                                >
                                    <Tag class="h-3 w-3" />
                                    {{ tag }}
                                    <X class="h-3 w-3" />
                                </Badge>
                            </div>
                        </div>

                        <RestrictedAction>
                            <Button
                                variant="cta"
                                @click="runTests"
                                :disabled="isRunning"
                                class="cursor-pointer"
                            >
                                <Loader2 v-if="isRunning" class="mr-2 h-4 w-4 animate-spin" />
                                <Play v-else class="mr-2 h-4 w-4" />
                                {{ isRunning ? 'Running tests...' : 'Run Tests' }}
                            </Button>
                        </RestrictedAction>

                        <div v-if="runMessage" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950">
                            <p class="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-300">
                                <CheckCircle class="h-4 w-4" />
                                {{ runMessage }}
                            </p>
                        </div>

                        <div v-if="runError" class="rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950">
                            <p class="text-sm text-red-700 dark:text-red-300">{{ runError }}</p>
                        </div>
                    </div>
                </div>

                <!-- ==================== Templates Tab ==================== -->
                <div v-if="activeTab === 'templates'" class="space-y-6 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold">Run Templates</h3>
                            <p class="text-xs text-muted-foreground">Pre-configured test execution profiles</p>
                        </div>
                        <RestrictedAction>
                            <Button variant="cta" @click="openTemplateDialog()" class="cursor-pointer">
                                <Plus class="mr-2 h-4 w-4" />
                                Create Template
                            </Button>
                        </RestrictedAction>
                    </div>

                    <Deferred data="templates">
                        <template #fallback>
                            <div class="space-y-3">
                                <div v-for="i in 3" :key="i" class="h-20 w-full animate-pulse rounded-lg bg-muted" />
                            </div>
                        </template>
                    <div v-if="templates?.length" class="space-y-3">
                        <Card v-for="tmpl in templates" :key="tmpl.id" class="transition-colors hover:border-primary">
                            <CardContent class="px-4 py-3">
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <BookTemplate class="h-4 w-4 shrink-0 text-primary" />
                                            <h4 class="text-sm font-semibold truncate">{{ tmpl.name }}</h4>
                                        </div>
                                        <p v-if="tmpl.description" class="mt-1 text-xs text-muted-foreground truncate">
                                            {{ tmpl.description }}
                                        </p>
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <Badge v-if="tmpl.environment" variant="outline" class="text-xs gap-1">
                                                <Globe class="h-3 w-3" />
                                                {{ tmpl.environment.name }}
                                            </Badge>
                                            <Badge v-if="tmpl.file_pattern" variant="outline" class="text-xs gap-1">
                                                <FileCode class="h-3 w-3" />
                                                {{ tmpl.file_pattern }}
                                            </Badge>
                                            <Badge
                                                v-for="tag in tmpl.tags || []"
                                                :key="tag"
                                                variant="secondary"
                                                class="text-xs gap-1"
                                            >
                                                <Tag class="h-3 w-3" />{{ tag }}
                                            </Badge>
                                            <Badge v-if="tmpl.tags?.length" variant="outline" class="text-xs">
                                                {{ tmpl.tag_mode.toUpperCase() }}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex items-center gap-2 shrink-0">
                                        <RestrictedAction>
                                            <Button
                                                variant="cta"
                                                size="sm"
                                                @click="runFromTemplate(tmpl)"
                                                :disabled="isRunning || !project.automation_tests_path"
                                                class="cursor-pointer"
                                            >
                                                <Play class="mr-1 h-3 w-3" />Run
                                            </Button>
                                        </RestrictedAction>
                                        <RestrictedAction>
                                            <Button variant="ghost" size="sm" @click="openTemplateDialog(tmpl)" class="cursor-pointer">
                                                <Pencil class="h-3.5 w-3.5" />
                                            </Button>
                                        </RestrictedAction>
                                        <RestrictedAction>
                                            <Button variant="ghost" size="sm" @click="confirmDeleteTemplate(tmpl)" class="cursor-pointer text-destructive hover:text-destructive">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </RestrictedAction>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="py-12 text-center">
                        <BookTemplate class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">No templates yet. Create one to save test run configurations.</p>
                    </div>
                    </Deferred>
                </div>

                <!-- ==================== Environments Tab ==================== -->
                <div v-if="activeTab === 'environments'" class="space-y-6 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold">Test Environments</h3>
                            <p class="text-xs text-muted-foreground">Configure environments with base URLs, browsers, and custom variables</p>
                        </div>
                        <RestrictedAction>
                            <Button variant="cta" @click="openEnvDialog()" class="cursor-pointer">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Environment
                            </Button>
                        </RestrictedAction>
                    </div>

                    <Deferred data="environments">
                        <template #fallback>
                            <div class="space-y-3">
                                <div v-for="i in 3" :key="i" class="h-20 w-full animate-pulse rounded-lg bg-muted" />
                            </div>
                        </template>
                    <div v-if="environments?.length" class="space-y-3">
                        <Card v-for="env in environments" :key="env.id" class="transition-colors hover:border-primary">
                            <CardContent class="px-4 py-3">
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <Server class="h-4 w-4 shrink-0 text-primary" />
                                            <h4 class="text-sm font-semibold truncate">{{ env.name }}</h4>
                                            <Badge v-if="env.is_default" variant="default" class="text-xs">Default</Badge>
                                        </div>
                                        <p v-if="env.description" class="mt-1 text-xs text-muted-foreground truncate">
                                            {{ env.description }}
                                        </p>
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <Badge v-if="env.base_url" variant="outline" class="text-xs gap-1">
                                                <Globe class="h-3 w-3" />
                                                {{ env.base_url }}
                                            </Badge>
                                            <Badge variant="secondary" class="text-xs">{{ env.browser }}</Badge>
                                            <Badge v-if="env.workers > 1" variant="secondary" class="text-xs">{{ env.workers }} workers</Badge>
                                            <Badge v-if="env.retries > 0" variant="secondary" class="text-xs">{{ env.retries }} retries</Badge>
                                            <Badge v-if="env.headed" variant="secondary" class="text-xs">headed</Badge>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex items-center gap-2 shrink-0">
                                        <RestrictedAction>
                                            <Button variant="ghost" size="sm" @click="openEnvDialog(env)" class="cursor-pointer">
                                                <Pencil class="h-3.5 w-3.5" />
                                            </Button>
                                        </RestrictedAction>
                                        <RestrictedAction>
                                            <Button variant="ghost" size="sm" @click="confirmDeleteEnv(env)" class="cursor-pointer text-destructive hover:text-destructive">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </RestrictedAction>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="py-12 text-center">
                        <Globe class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">No environments configured. Add one to run tests against different targets.</p>
                    </div>
                    </Deferred>
                </div>

                <!-- ==================== Results History Tab ==================== -->
                <div v-if="activeTab === 'history'" class="space-y-4 p-6">
                    <div class="flex items-center justify-between">
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="historySearch"
                                placeholder="Search results..."
                                class="w-64 bg-background/60 pl-9 pr-8"
                            />
                            <button
                                v-if="historySearch"
                                @click="historySearch = ''"
                                class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <RestrictedAction>
                            <Button
                                v-if="recentResults.data.length"
                                variant="outline"
                                size="sm"
                                @click="showClearDialog = true"
                                class="cursor-pointer text-destructive hover:text-destructive"
                            >
                                <Trash2 class="mr-1 h-3.5 w-3.5" />
                                Clear All
                            </Button>
                        </RestrictedAction>
                    </div>

                    <div v-if="filteredResults.length" class="space-y-1">
                        <div
                            v-for="result in filteredResults"
                            :key="result.id"
                            class="flex items-center justify-between rounded-md border px-4 py-2.5 transition-colors hover:bg-muted/50"

                        >
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <component
                                    :is="getStatusIcon(result.status)"
                                    class="h-4 w-4 shrink-0"
                                    :class="getStatusColor(result.status)"
                                />
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium">{{ result.test_name }}</div>
                                    <div class="flex items-center gap-1 truncate text-xs text-muted-foreground">
                                        <FileCode class="h-3 w-3 shrink-0" />
                                        {{ result.test_file }}
                                        <template v-if="result.environment">
                                            <span class="mx-1">|</span>
                                            <Globe class="h-3 w-3 shrink-0" />
                                            {{ result.environment.name }}
                                        </template>
                                    </div>
                                    <div v-if="result.error_message" class="mt-0.5 truncate text-xs text-red-500">
                                        {{ result.error_message }}
                                    </div>
                                    <div v-if="result.tags?.length" class="mt-1 flex flex-wrap gap-1">
                                        <Badge v-for="tag in result.tags" :key="tag" variant="outline" class="text-[10px] px-1.5 py-0">{{ tag }}</Badge>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-4 flex shrink-0 items-center gap-3">
                                <Badge :variant="getStatusVariant(result.status)" class="text-xs">
                                    {{ result.status }}
                                </Badge>
                                <span class="w-16 text-right text-xs text-muted-foreground">
                                    {{ formatDuration(result.duration_ms) }}
                                </span>
                                <span class="w-28 text-right text-xs text-muted-foreground">
                                    {{ formatDate(result.executed_at) }}
                                </span>
                            </div>
                        </div>
                        <div v-if="recentResults.next_cursor && !historySearch.trim()" class="pt-2 text-center">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="loadMore"
                                :disabled="loadingMore"
                                class="cursor-pointer"
                            >
                                <Loader2 v-if="loadingMore" class="mr-2 h-3.5 w-3.5 animate-spin" />
                                Load More
                            </Button>
                        </div>
                    </div>

                    <div v-else class="py-12 text-center">
                        <History class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">
                            {{ recentResults.data.length ? 'No results match your search' : 'No test results yet. Run tests to see results here.' }}
                        </p>
                    </div>
                </div>

                <!-- ==================== Discover Tests Tab ==================== -->
                <div v-if="activeTab === 'discover'" class="space-y-6 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold">Discover Playwright Tests</h3>
                            <p class="text-xs text-muted-foreground">Scan your test directory to find all test files and tags</p>
                        </div>
                        <Button
                            @click="scanTests"
                            :disabled="isScanning || !project.automation_tests_path"
                            class="cursor-pointer"
                        >
                            <Loader2 v-if="isScanning" class="mr-2 h-4 w-4 animate-spin" />
                            <FolderSearch v-else class="mr-2 h-4 w-4" />
                            {{ isScanning ? 'Scanning...' : 'Scan for Tests' }}
                        </Button>
                    </div>

                    <div v-if="!project.automation_tests_path" class="rounded-md border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950">
                        <p class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-300">
                            <AlertTriangle class="h-4 w-4" />
                            Configure the tests path first in the Configuration tab.
                        </p>
                    </div>

                    <div v-if="scanError" class="rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950">
                        <p class="text-sm text-red-700 dark:text-red-300">{{ scanError }}</p>
                    </div>

                    <div v-if="scanResults">
                        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950">
                            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                                Found {{ scanResults.total_files }} test files with {{ scanResults.total_tests }} tests
                            </p>
                            <div v-if="scanResults.all_tags.length" class="mt-2 flex flex-wrap gap-1">
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 mr-1">Tags:</span>
                                <Badge
                                    v-for="tag in scanResults.all_tags"
                                    :key="tag"
                                    variant="secondary"
                                    class="text-xs gap-1"
                                >
                                    <Tag class="h-3 w-3" />{{ tag }}
                                </Badge>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <Card v-for="(file, fileIndex) in scanResults.files" :key="file.file">
                                <CardContent class="px-4 py-3">
                                    <div class="mb-2 flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-semibold">{{ file.suite }}</h4>
                                            <p class="flex items-center gap-1 text-xs text-muted-foreground">
                                                <FileCode class="h-3 w-3" />
                                                {{ file.file }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Badge variant="secondary">{{ file.tests.length }} tests</Badge>
                                            <button
                                                @click="removeFile(fileIndex)"
                                                class="cursor-pointer text-muted-foreground transition-colors hover:text-destructive"
                                                title="Remove file"
                                            >
                                                <X class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <div
                                            v-for="test in file.tests"
                                            :key="test.full_name"
                                            class="flex items-center justify-between rounded bg-muted/50 px-3 py-1.5 text-xs"
                                        >
                                            <div class="flex items-center gap-2 min-w-0 truncate">
                                                <span class="truncate">{{ test.name }}</span>
                                                <Badge
                                                    v-for="tag in test.tags"
                                                    :key="tag"
                                                    variant="outline"
                                                    class="text-[10px] px-1.5 py-0 shrink-0"
                                                >{{ tag }}</Badge>
                                            </div>
                                            <div class="ml-3 flex shrink-0 items-center gap-2">
                                                <RestrictedAction>
                                                    <button
                                                        @click="openLinkDialog(file.file, test.full_name)"
                                                        class="cursor-pointer text-primary hover:underline"
                                                    >
                                                        <Link class="mr-1 inline h-3 w-3" />Link
                                                    </button>
                                                </RestrictedAction>
                                                <button
                                                    @click="removeTest(fileIndex, test)"
                                                    class="cursor-pointer text-muted-foreground transition-colors hover:text-destructive"
                                                    title="Remove test"
                                                >
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>
                                        <div
                                            v-for="testName in file.skipped_tests"
                                            :key="'skip-' + testName"
                                            class="flex items-center justify-between rounded bg-muted/30 px-3 py-1.5 text-xs text-muted-foreground line-through"
                                        >
                                            <span class="truncate">{{ testName }} (skipped)</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <div v-else-if="!isScanning && !scanError" class="py-12 text-center">
                        <FolderSearch class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">Click "Scan for Tests" to discover Playwright tests</p>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Link Test Case Dialog -->
        <Dialog v-model:open="showLinkDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link to Test Case</DialogTitle>
                    <DialogDescription>
                        Link "{{ linkTestName }}" from {{ linkFile }} to a CheckMate test case.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4 text-sm text-muted-foreground">
                    <p>Navigate to Test Suites to link this Playwright test to a test case.</p>
                    <p class="mt-2">File: <code class="rounded bg-muted px-1">{{ linkFile }}</code></p>
                    <p>Test: <code class="rounded bg-muted px-1">{{ linkTestName }}</code></p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showLinkDialog = false" class="cursor-pointer">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Clear Results Dialog -->
        <Dialog v-model:open="showClearDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Clear All Results</DialogTitle>
                    <DialogDescription>
                        This will permanently delete all automation test results for this project.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showClearDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button variant="destructive" @click="clearResults" class="cursor-pointer">Clear All</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Environment Dialog -->
        <Dialog v-model:open="showEnvDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingEnv ? 'Edit' : 'Add' }} Environment</DialogTitle>
                    <DialogDescription>
                        Configure an environment for running Playwright tests.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div>
                        <Label>Name</Label>
                        <Input v-model="envForm.name" placeholder="e.g. Staging" class="mt-1" />
                    </div>
                    <div>
                        <Label>Base URL</Label>
                        <Input v-model="envForm.base_url" placeholder="https://staging.example.com" class="mt-1" />
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea v-model="envForm.description" placeholder="Optional description" class="mt-1" rows="2" />
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <Label>Browser</Label>
                            <Select v-model="envForm.browser">
                                <SelectTrigger class="mt-1 cursor-pointer">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="chromium" class="cursor-pointer">Chromium</SelectItem>
                                    <SelectItem value="firefox" class="cursor-pointer">Firefox</SelectItem>
                                    <SelectItem value="webkit" class="cursor-pointer">WebKit</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Workers</Label>
                            <Input v-model.number="envForm.workers" type="number" min="1" max="32" class="mt-1" />
                        </div>
                        <div>
                            <Label>Retries</Label>
                            <Input v-model.number="envForm.retries" type="number" min="0" max="10" class="mt-1" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Timeout (ms)</Label>
                            <Input v-model.number="envForm.timeout" type="number" min="1000" max="300000" class="mt-1" />
                        </div>
                        <div class="flex items-end gap-3 pb-1">
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" v-model="envForm.headed" class="cursor-pointer" />
                                Headed mode
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" v-model="envForm.is_default" class="cursor-pointer" />
                                Default
                            </label>
                        </div>
                    </div>
                    <!-- Custom variables -->
                    <div>
                        <Label>Environment Variables</Label>
                        <div class="mt-1 flex gap-2">
                            <Input v-model="envVarKey" placeholder="KEY" class="flex-1" @keydown.enter.prevent="addEnvVar" />
                            <Input v-model="envVarValue" placeholder="value" class="flex-1" @keydown.enter.prevent="addEnvVar" />
                            <Button variant="outline" size="sm" @click="addEnvVar" class="cursor-pointer shrink-0">Add</Button>
                        </div>
                        <div v-if="Object.keys(envForm.variables).length" class="mt-2 space-y-1">
                            <div
                                v-for="(val, key) in envForm.variables"
                                :key="key"
                                class="flex items-center justify-between rounded bg-muted/50 px-3 py-1.5 text-xs"
                            >
                                <span><strong>{{ key }}</strong> = {{ val }}</span>
                                <button @click="removeEnvVar(String(key))" class="cursor-pointer text-muted-foreground hover:text-destructive">
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showEnvDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button variant="cta" @click="saveEnv" :disabled="!envForm.name.trim()" class="cursor-pointer">
                        {{ editingEnv ? 'Update' : 'Create' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Environment Dialog -->
        <Dialog v-model:open="showDeleteEnvDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Environment</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ deletingEnv?.name }}"? This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteEnvDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button variant="destructive" @click="deleteEnv" class="cursor-pointer">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Template Dialog -->
        <Dialog v-model:open="showTemplateDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingTemplate ? 'Edit' : 'Create' }} Template</DialogTitle>
                    <DialogDescription>
                        Configure a reusable test run profile.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div>
                        <Label>Name</Label>
                        <Input v-model="templateForm.name" placeholder="e.g. Smoke Tests - Staging" class="mt-1" />
                    </div>
                    <div>
                        <Label>Description</Label>
                        <Textarea v-model="templateForm.description" placeholder="Optional description" class="mt-1" rows="2" />
                    </div>
                    <div>
                        <Label>Environment</Label>
                        <Select v-model="templateForm.environment_id">
                            <SelectTrigger class="mt-1 cursor-pointer bg-background/60">
                                <SelectValue placeholder="No environment" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="" class="cursor-pointer">No environment</SelectItem>
                                <SelectItem
                                    v-for="env in environments ?? []"
                                    :key="env.id"
                                    :value="String(env.id)"
                                    class="cursor-pointer"
                                >{{ env.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>File pattern</Label>
                        <Input v-model="templateForm.file_pattern" placeholder="e.g. tests/login.spec.ts" class="mt-1" />
                    </div>
                    <div>
                        <Label>Tags</Label>
                        <div class="mt-1 flex items-center gap-2">
                            <Input
                                v-model="templateTagInput"
                                placeholder="@smoke"
                                class="max-w-48"
                                @keydown.enter.prevent="addTemplateTag"
                            />
                            <Button variant="outline" size="sm" @click="addTemplateTag" class="cursor-pointer">
                                <Plus class="mr-1 h-3 w-3" />Add
                            </Button>
                            <Select v-model="templateForm.tag_mode">
                                <SelectTrigger class="w-24 cursor-pointer bg-background/60">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="or" class="cursor-pointer">OR</SelectItem>
                                    <SelectItem value="and" class="cursor-pointer">AND</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="templateForm.tags.length" class="mt-2 flex flex-wrap gap-1">
                            <Badge
                                v-for="tag in templateForm.tags"
                                :key="tag"
                                variant="secondary"
                                class="cursor-pointer gap-1"
                                @click="removeTemplateTag(tag)"
                            >
                                <Tag class="h-3 w-3" />
                                {{ tag }}
                                <X class="h-3 w-3" />
                            </Badge>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showTemplateDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button variant="cta" @click="saveTemplate" :disabled="!templateForm.name.trim()" class="cursor-pointer">
                        {{ editingTemplate ? 'Update' : 'Create' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Template Dialog -->
        <Dialog v-model:open="showDeleteTemplateDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Template</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ deletingTemplate?.name }}"? This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteTemplateDialog = false" class="cursor-pointer">Cancel</Button>
                    <Button variant="destructive" @click="deleteTemplate" class="cursor-pointer">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
