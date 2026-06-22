<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ClickupController from '@/actions/App/Http/Controllers/Settings/ClickupController';
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
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { show } from '@/routes/clickup';
import { type BreadcrumbItem } from '@/types';

interface ClickupStatus {
    status: string;
    color: string;
    type: string;
}

const props = defineProps<{
    settings: {
        has_token: boolean;
        list_id: string | null;
        status_mapping: Record<string, string>;
        has_webhook: boolean;
    };
    appStatuses: string[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'ClickUp settings',
        href: show().url,
    },
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

function saveSettings() {
    settingsForm.put(ClickupController.update().url, {
        preserveScroll: true,
        onSuccess: () => {
            settingsForm.reset('api_token');
        },
    });
}

function saveMappings() {
    mappingForm.put(ClickupController.updateStatusMapping().url, {
        preserveScroll: true,
    });
}

async function fetchStatuses() {
    fetchingStatuses.value = true;
    fetchError.value = '';

    try {
        const response = await fetch(ClickupController.fetchStatuses().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
                Accept: 'application/json',
            },
        });

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
        ClickupController.registerWebhook().url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (registeringWebhook.value = false),
        },
    );
}

function formatStatus(status: string): string {
    return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="ClickUp settings" />

        <h1 class="sr-only">ClickUp Settings</h1>

        <SettingsLayout>
            <div class="space-y-12">
                <!-- API Configuration -->
                <div class="space-y-6">
                    <Heading
                        variant="small"
                        title="API Configuration"
                        description="Enter your ClickUp API token and the List ID where bug reports will be exported."
                    />

                    <form class="space-y-4" @submit.prevent="saveSettings">
                        <div class="space-y-2">
                            <Label for="api_token">API Token</Label>
                            <Input
                                id="api_token"
                                v-model="settingsForm.api_token"
                                type="password"
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
                            type="submit"
                            class="cursor-pointer"
                            :disabled="settingsForm.processing"
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
                <div class="space-y-6">
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
                <div class="space-y-6">
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
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
