<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, Workspace } from '@/types';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    workspace: Workspace;
    settings: {
        has_gemini_key: boolean;
        gemini_model: string | null;
        has_claude_key: boolean;
        has_openai_key: boolean;
        openai_model: string | null;
        default_provider: string;
    };
}>();

const page = usePage<AppPageProps>();
const canManage = computed(() => {
    const role = page.props.currentWorkspace?.role;
    return role === 'owner' || role === 'admin';
});

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Workspace Settings', href: '/workspaces/settings' },
    { title: 'AI Providers', href: '/workspaces/settings/ai' },
];

const form = useForm({
    gemini_api_key: '',
    gemini_model: props.settings.gemini_model ?? '',
    anthropic_api_key: '',
    openai_api_key: '',
    openai_model: props.settings.openai_model ?? '',
    default_provider: props.settings.default_provider,
});

function save() {
    form.put('/workspaces/settings/ai', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('gemini_api_key', 'anthropic_api_key', 'openai_api_key');
            form.defaults();
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="AI Provider settings" />
        <div class="flex flex-1 flex-col gap-6 p-6">
            <Heading
                :title="`AI Providers — ${workspace.name}`"
                description="These keys are used for AI test case generation, translation, and coverage analysis in this workspace only."
            />

            <p
                v-if="!canManage"
                class="max-w-2xl rounded-md border border-amber-400/40 bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
            >
                Only workspace owners and admins can change these settings.
            </p>

            <form class="max-w-2xl space-y-6" @submit.prevent="save">
                <!-- Gemini -->
                <div class="grid gap-4 rounded-lg border p-4">
                    <Heading variant="small" title="Google Gemini" />

                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Label for="gemini_api_key">API Key</Label>
                            <span
                                v-if="settings.has_gemini_key"
                                class="text-sm text-green-600"
                            >
                                Key saved
                            </span>
                            <span v-else class="text-sm text-muted-foreground">
                                No key saved
                            </span>
                        </div>
                        <Input
                            id="gemini_api_key"
                            v-model="form.gemini_api_key"
                            type="password"
                            autocomplete="new-password"
                            :disabled="!canManage"
                            :placeholder="
                                settings.has_gemini_key
                                    ? '••••••••••••••••'
                                    : 'AIza...'
                            "
                        />
                        <p
                            v-if="form.errors.gemini_api_key"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.gemini_api_key }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Stored encrypted. Leave empty to keep the current
                            key.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="gemini_model">Model</Label>
                        <Input
                            id="gemini_model"
                            v-model="form.gemini_model"
                            :disabled="!canManage"
                            placeholder="gemini-flash-latest"
                        />
                        <p class="text-xs text-muted-foreground">
                            Defaults to Google's rolling "latest" alias, so it
                            keeps working as Google retires older model
                            versions. Set a specific version here only if you
                            need reproducible output — see
                            <a
                                href="https://ai.google.dev/gemini-api/docs/models"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="underline"
                                >Google's current model list</a
                            >.
                        </p>
                    </div>
                </div>

                <!-- Claude -->
                <div class="grid gap-4 rounded-lg border p-4">
                    <Heading variant="small" title="Anthropic Claude" />

                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Label for="anthropic_api_key">API Key</Label>
                            <span
                                v-if="settings.has_claude_key"
                                class="text-sm text-green-600"
                            >
                                Key saved
                            </span>
                            <span v-else class="text-sm text-muted-foreground">
                                No key saved
                            </span>
                        </div>
                        <Input
                            id="anthropic_api_key"
                            v-model="form.anthropic_api_key"
                            type="password"
                            autocomplete="new-password"
                            :disabled="!canManage"
                            :placeholder="
                                settings.has_claude_key
                                    ? '••••••••••••••••'
                                    : 'sk-ant-...'
                            "
                        />
                        <p
                            v-if="form.errors.anthropic_api_key"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.anthropic_api_key }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Stored encrypted. Leave empty to keep the current
                            key.
                        </p>
                    </div>
                </div>

                <!-- OpenAI -->
                <div class="grid gap-4 rounded-lg border p-4">
                    <Heading variant="small" title="OpenAI" />

                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Label for="openai_api_key">API Key</Label>
                            <span
                                v-if="settings.has_openai_key"
                                class="text-sm text-green-600"
                            >
                                Key saved
                            </span>
                            <span v-else class="text-sm text-muted-foreground">
                                No key saved
                            </span>
                        </div>
                        <Input
                            id="openai_api_key"
                            v-model="form.openai_api_key"
                            type="password"
                            autocomplete="new-password"
                            :disabled="!canManage"
                            :placeholder="
                                settings.has_openai_key
                                    ? '••••••••••••••••'
                                    : 'sk-...'
                            "
                        />
                        <p
                            v-if="form.errors.openai_api_key"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.openai_api_key }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Stored encrypted. Leave empty to keep the current
                            key.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="openai_model">Model</Label>
                        <Input
                            id="openai_model"
                            v-model="form.openai_model"
                            :disabled="!canManage"
                            placeholder="gpt-4o-mini"
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="default_provider">Default Provider</Label>
                    <Select
                        v-model="form.default_provider"
                        :disabled="!canManage"
                    >
                        <SelectTrigger id="default_provider" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="gemini">Gemini</SelectItem>
                            <SelectItem value="claude">Claude</SelectItem>
                            <SelectItem value="openai">OpenAI</SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="form.errors.default_provider"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.default_provider }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Which provider is preselected when generating test cases
                        or translating in this workspace.
                    </p>
                </div>

                <div v-if="canManage" class="flex items-center gap-4">
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.isDirty"
                        class="cursor-pointer"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Settings' }}
                    </Button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="form.recentlySuccessful"
                            class="text-sm text-neutral-600"
                        >
                            Saved.
                        </p>
                    </Transition>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
