<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

interface ClickupStatus {
    status: string;
    color: string;
    type: string;
}

const props = defineProps<{
    workspace: Workspace;
    settings: {
        has_token: boolean;
        list_id: string | null;
        status_mapping: Record<string, string>;
        has_webhook: boolean;
    };
    appStatuses: string[];
    queueDiagnostics: {
        pending: number;
        recentFailures: { failed_at: string; message: string }[];
    };
}>();

const page = usePage<AppPageProps>();
const canManage = computed(() => {
    const role = page.props.currentWorkspace?.role;
    return role === 'owner' || role === 'admin';
});

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Workspace Settings', href: '/workspaces/settings' },
    { title: 'ClickUp', href: '/workspaces/settings/clickup' },
];

const settingsForm = useForm({
    api_token: '',
    list_id: props.settings.list_id ?? '',
});

const mappingForm = useForm({
    status_mapping: { ...props.settings.status_mapping } as Record<
        string,
        string
    >,
});

const clickupStatuses = ref<ClickupStatus[]>([]);
const fetchingStatuses = ref(false);
const fetchError = ref('');
const registeringWebhook = ref(false);

interface WebhookHealth {
    endpoint: string | null;
    events: string[];
    health: { status: string; fail_count: number } | null;
    team_id: string;
}
const checkingWebhookHealth = ref(false);
const webhookHealth = ref<WebhookHealth | null>(null);
const webhookHealthError = ref('');

function saveSettings() {
    settingsForm.put('/workspaces/settings/clickup', {
        preserveScroll: true,
        onSuccess: () => {
            settingsForm.reset('api_token');
            settingsForm.defaults();
        },
    });
}

function saveMappings() {
    mappingForm.put('/workspaces/settings/clickup/status-mapping', {
        preserveScroll: true,
    });
}

async function fetchStatuses() {
    fetchingStatuses.value = true;
    fetchError.value = '';

    try {
        const response = await fetch(
            '/workspaces/settings/clickup/fetch-statuses',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? '',
                    Accept: 'application/json',
                },
            },
        );

        const data = await response.json();

        if (!response.ok) {
            fetchError.value = data.error || 'Failed to fetch statuses.';
            return;
        }

        clickupStatuses.value = data.statuses;
    } catch {
        fetchError.value = 'Network error fetching statuses.';
    } finally {
        fetchingStatuses.value = false;
    }
}

function registerWebhook() {
    registeringWebhook.value = true;
    router.post(
        '/workspaces/settings/clickup/register-webhook',
        {},
        {
            preserveScroll: true,
            onFinish: () => (registeringWebhook.value = false),
        },
    );
}

async function checkWebhookHealth() {
    checkingWebhookHealth.value = true;
    webhookHealthError.value = '';
    webhookHealth.value = null;

    try {
        const response = await fetch(
            '/workspaces/settings/clickup/webhook-health',
            { headers: { Accept: 'application/json' } },
        );
        const data = await response.json();

        if (!response.ok) {
            webhookHealthError.value =
                data.error || 'Failed to check webhook health.';
            return;
        }

        webhookHealth.value = data;
    } catch {
        webhookHealthError.value = 'Network error checking webhook health.';
    } finally {
        checkingWebhookHealth.value = false;
    }
}

function formatStatus(status: string): string {
    return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="ClickUp settings" />

        <div class="flex flex-1 flex-col gap-6 p-6">
            <Heading
                :title="`ClickUp — ${workspace.name}`"
                description="Bug reports from projects in this workspace export to this ClickUp list."
            />

            <p
                v-if="!canManage"
                class="rounded-md border border-amber-400/40 bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
            >
                Only workspace owners and admins can change these settings.
            </p>

            <div class="max-w-2xl space-y-12">
                <!-- API Configuration -->
                <div class="space-y-6">
                    <Heading
                        variant="small"
                        title="API Configuration"
                        description="Enter your ClickUp API token and the List ID where bug reports will be exported."
                    />

                    <form class="space-y-4" @submit.prevent="saveSettings">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <Label for="api_token">API Token</Label>
                                <span
                                    v-if="settings.has_token"
                                    class="text-sm text-green-600"
                                >
                                    Token saved
                                </span>
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                >
                                    No token saved
                                </span>
                            </div>
                            <Input
                                id="api_token"
                                v-model="settingsForm.api_token"
                                type="password"
                                autocomplete="new-password"
                                :disabled="!canManage"
                                :placeholder="
                                    settings.has_token
                                        ? '••••••••••••••••'
                                        : 'pk_...'
                                "
                            />
                            <p
                                v-if="settingsForm.errors.api_token"
                                class="text-sm text-destructive"
                            >
                                {{ settingsForm.errors.api_token }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="list_id">List ID</Label>
                            <Input
                                id="list_id"
                                v-model="settingsForm.list_id"
                                :disabled="!canManage"
                                placeholder="e.g. 901234567890"
                            />
                            <p
                                v-if="settingsForm.errors.list_id"
                                class="text-sm text-destructive"
                            >
                                {{ settingsForm.errors.list_id }}
                            </p>
                        </div>

                        <Button
                            v-if="canManage"
                            type="submit"
                            class="cursor-pointer"
                            :disabled="
                                settingsForm.processing || !settingsForm.isDirty
                            "
                        >
                            {{
                                settingsForm.processing
                                    ? 'Saving...'
                                    : 'Save settings'
                            }}
                        </Button>
                    </form>
                </div>

                <!-- Status Mapping -->
                <div v-if="canManage" class="space-y-6">
                    <Heading
                        variant="small"
                        title="Status Mapping"
                        description="Map your app's bug report statuses to ClickUp statuses for sync."
                    />

                    <div class="space-y-4">
                        <Button
                            variant="outline"
                            class="cursor-pointer"
                            :disabled="fetchingStatuses"
                            @click="fetchStatuses"
                        >
                            {{
                                fetchingStatuses
                                    ? 'Fetching...'
                                    : 'Fetch ClickUp statuses'
                            }}
                        </Button>

                        <p v-if="fetchError" class="text-sm text-destructive">
                            {{ fetchError }}
                        </p>

                        <div
                            v-if="clickupStatuses.length > 0"
                            class="space-y-4"
                        >
                            <div class="rounded-md border">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b bg-muted/50">
                                            <th
                                                class="px-4 py-2 text-left font-medium"
                                            >
                                                App Status
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-medium"
                                            >
                                                ClickUp Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="status in appStatuses"
                                            :key="status"
                                            class="border-b last:border-0"
                                        >
                                            <td class="px-4 py-2">
                                                {{ formatStatus(status) }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <Select
                                                    v-model="
                                                        mappingForm
                                                            .status_mapping[
                                                            status
                                                        ]
                                                    "
                                                >
                                                    <SelectTrigger
                                                        class="w-full cursor-pointer"
                                                    >
                                                        <SelectValue
                                                            placeholder="Select status"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="cs in clickupStatuses"
                                                            :key="cs.status"
                                                            :value="cs.status"
                                                            class="cursor-pointer"
                                                        >
                                                            <span
                                                                class="mr-2 inline-block h-2 w-2 rounded-full"
                                                                :style="{
                                                                    backgroundColor:
                                                                        cs.color,
                                                                }"
                                                            />
                                                            {{ cs.status }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <Button
                                class="cursor-pointer"
                                :disabled="mappingForm.processing"
                                @click="saveMappings"
                            >
                                {{
                                    mappingForm.processing
                                        ? 'Saving...'
                                        : 'Save mapping'
                                }}
                            </Button>
                        </div>

                        <p
                            v-else-if="!fetchingStatuses && !fetchError"
                            class="text-sm text-muted-foreground"
                        >
                            Save your API token and List ID first, then fetch
                            statuses to configure mapping.
                        </p>
                    </div>
                </div>

                <!-- Webhook -->
                <div v-if="canManage" class="space-y-6">
                    <Heading
                        variant="small"
                        title="Webhook"
                        description="Register a webhook so ClickUp status changes sync back to your bug reports."
                    />

                    <div class="flex items-center gap-4">
                        <Button
                            variant="outline"
                            class="cursor-pointer"
                            :disabled="registeringWebhook"
                            @click="registerWebhook"
                        >
                            {{
                                registeringWebhook
                                    ? 'Registering...'
                                    : settings.has_webhook
                                      ? 'Re-register webhook'
                                      : 'Register webhook'
                            }}
                        </Button>

                        <span
                            v-if="settings.has_webhook"
                            class="text-sm text-green-600"
                        >
                            Webhook active
                        </span>
                        <span v-else class="text-sm text-muted-foreground">
                            No webhook registered
                        </span>

                        <Button
                            v-if="settings.has_webhook"
                            variant="ghost"
                            size="sm"
                            class="cursor-pointer"
                            :disabled="checkingWebhookHealth"
                            @click="checkWebhookHealth"
                        >
                            {{
                                checkingWebhookHealth
                                    ? 'Checking...'
                                    : 'Check webhook health'
                            }}
                        </Button>
                    </div>

                    <p
                        v-if="webhookHealthError"
                        class="text-sm text-destructive"
                    >
                        {{ webhookHealthError }}
                    </p>

                    <div
                        v-if="webhookHealth"
                        class="space-y-1 rounded-lg border p-4 text-sm"
                    >
                        <p>
                            <span class="font-medium">Endpoint:</span>
                            {{ webhookHealth.endpoint }}
                        </p>
                        <p>
                            <span class="font-medium">Events:</span>
                            {{ webhookHealth.events.join(', ') }}
                        </p>
                        <p>
                            <span class="font-medium"
                                >ClickUp health status:</span
                            >
                            <span
                                :class="
                                    webhookHealth.health?.status === 'active'
                                        ? 'text-green-600'
                                        : 'text-destructive'
                                "
                            >
                                {{ webhookHealth.health?.status ?? 'unknown' }}
                            </span>
                            <span
                                v-if="
                                    webhookHealth.health &&
                                    webhookHealth.health.fail_count > 0
                                "
                                class="text-destructive"
                            >
                                ({{ webhookHealth.health.fail_count }} failed
                                delivery attempt(s) recorded by ClickUp)
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Export Queue Diagnostics -->
                <div
                    v-if="
                        queueDiagnostics.pending > 0 ||
                        queueDiagnostics.recentFailures.length > 0
                    "
                    class="space-y-4"
                >
                    <Heading
                        variant="small"
                        title="Bug Report Export Queue"
                        description="Bug reports are exported to ClickUp via a background queue worker. If exports aren't showing up in ClickUp, check here."
                    />

                    <div class="space-y-3 rounded-lg border p-4 text-sm">
                        <p>
                            <span class="font-medium"
                                >Pending export jobs:</span
                            >
                            {{ queueDiagnostics.pending }}
                            <span
                                v-if="queueDiagnostics.pending > 0"
                                class="text-muted-foreground"
                            >
                                — if this number doesn't go down after a minute
                                or two, the queue worker likely isn't running on
                                the server.
                            </span>
                        </p>

                        <div v-if="queueDiagnostics.recentFailures.length > 0">
                            <p class="font-medium text-destructive">
                                Recent export failures:
                            </p>
                            <ul class="mt-2 space-y-2">
                                <li
                                    v-for="(
                                        failure, index
                                    ) in queueDiagnostics.recentFailures"
                                    :key="index"
                                    class="rounded-md bg-destructive/10 p-3"
                                >
                                    <p class="text-xs text-muted-foreground">
                                        {{ failure.failed_at }}
                                    </p>
                                    <p
                                        class="mt-1 font-mono text-xs break-words text-destructive"
                                    >
                                        {{ failure.message }}
                                    </p>
                                </li>
                            </ul>
                        </div>
                        <p v-else class="text-muted-foreground">
                            No recent export failures recorded.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
