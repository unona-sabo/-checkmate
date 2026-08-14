<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, Workspace } from '@/types';
import { type BreadcrumbItem } from '@/types';

interface ConnectionAttempt {
    reachable: boolean;
    status: number | null;
    message: string | null;
}

interface ConnectionTestResult {
    base_url: string;
    dns: {
        host: string | null;
        resolved: boolean;
        ip: string | null;
    };
    connection: ConnectionAttempt;
    ip_connection: (ConnectionAttempt & { ip: string }) | null;
}

const props = defineProps<{
    workspace: Workspace;
    settings: {
        has_token: boolean;
        base_url: string | null;
        datasource_id: string | null;
        log_path: string | null;
    };
}>();

const page = usePage<AppPageProps>();
const canManage = computed(() => {
    const role = page.props.currentWorkspace?.role;
    return role === 'owner' || role === 'admin';
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Workspace Settings',
        href: `/workspaces/${props.workspace.id}-${props.workspace.slug}/settings`,
    },
    {
        title: 'Grafana',
        href: `/workspaces/${props.workspace.id}-${props.workspace.slug}/settings/grafana`,
    },
];

const form = useForm({
    api_token: '',
    base_url: props.settings.base_url ?? '',
    datasource_id: props.settings.datasource_id ?? '',
    log_path: props.settings.log_path ?? '',
});

function save() {
    form.put(
        `/workspaces/${props.workspace.id}-${props.workspace.slug}/settings/grafana`,
        {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('api_token');
                form.defaults();
            },
        },
    );
}

const testingConnection = ref(false);
const testError = ref('');
const testResult = ref<ConnectionTestResult | null>(null);
const manualIp = ref('');

async function testConnection() {
    testingConnection.value = true;
    testError.value = '';
    testResult.value = null;

    try {
        const response = await fetch(
            `/workspaces/${props.workspace.id}-${props.workspace.slug}/settings/grafana/test-connection`,
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
                body: JSON.stringify({
                    ip: manualIp.value.trim() || undefined,
                }),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            testError.value = data.error || 'Failed to test connection.';
            return;
        }

        testResult.value = data;
    } catch {
        testError.value = 'Network error while testing connection.';
    } finally {
        testingConnection.value = false;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Grafana settings" />
        <div class="flex flex-1 flex-col gap-6 p-6">
            <Heading
                :title="`Grafana / Loki — ${workspace.name}`"
                description="Configure the Grafana connection used by this workspace's Payout Monitor."
            />

            <p
                v-if="!canManage"
                class="max-w-2xl rounded-md border border-amber-400/40 bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
            >
                Only workspace owners and admins can change these settings.
            </p>

            <div class="max-w-2xl space-y-6">
                <form class="space-y-6" @submit.prevent="save">
                    <div class="grid gap-4 rounded-lg border p-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <Label for="api_token"
                                    >Service Account Token</Label
                                >
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
                                v-model="form.api_token"
                                type="password"
                                autocomplete="new-password"
                                :disabled="!canManage"
                                :placeholder="
                                    settings.has_token
                                        ? '••••••••••••••••'
                                        : 'glsa_...'
                                "
                            />
                            <p
                                v-if="form.errors.api_token"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.api_token }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Bearer token for Grafana API. Will be stored
                                encrypted. Leave empty to keep current token.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="base_url">Grafana Base URL</Label>
                            <Input
                                id="base_url"
                                v-model="form.base_url"
                                :disabled="!canManage"
                                placeholder="https://logging.air.io"
                            />
                            <p
                                v-if="form.errors.base_url"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.base_url }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="datasource_id"
                                >Loki Datasource ID or UID</Label
                            >
                            <Input
                                id="datasource_id"
                                v-model="form.datasource_id"
                                :disabled="!canManage"
                                placeholder="1 or Q111_7"
                            />
                            <p
                                v-if="form.errors.datasource_id"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.datasource_id }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Numeric ID or alphanumeric UID of the Loki
                                datasource in Grafana.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="log_path">Default Log File Path</Label>
                            <Input
                                id="log_path"
                                v-model="form.log_path"
                                :disabled="!canManage"
                                placeholder="/home/accountant/app/storage/logs/payouts-{YYYY-MM-DD}.log"
                            />
                            <p
                                v-if="form.errors.log_path"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.log_path }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Use {YYYY-MM-DD} as a date placeholder. Will be
                                auto-replaced.
                            </p>
                        </div>
                    </div>

                    <div v-if="canManage" class="flex items-center gap-4">
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.isDirty"
                            class="cursor-pointer"
                        >
                            {{
                                form.processing ? 'Saving...' : 'Save Settings'
                            }}
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

                <!-- Connection Diagnostics -->
                <div class="space-y-3 rounded-lg border p-4">
                    <Heading
                        variant="small"
                        title="Connection Diagnostics"
                        description="Test whether this server can reach Grafana over the network — useful when it works locally but not in production (e.g. Grafana only being reachable over a VPN/private network the server isn't on)."
                    />

                    <div class="flex flex-wrap items-end gap-2">
                        <div class="space-y-1">
                            <Label for="manual_ip" class="text-xs"
                                >Test by IP (optional, bypasses DNS)</Label
                            >
                            <Input
                                id="manual_ip"
                                v-model="manualIp"
                                placeholder="e.g. 10.1.1.105"
                                class="w-48"
                            />
                        </div>
                        <Button
                            variant="outline"
                            class="cursor-pointer"
                            :disabled="testingConnection"
                            @click="testConnection"
                        >
                            {{
                                testingConnection
                                    ? 'Testing...'
                                    : 'Test Connection'
                            }}
                        </Button>
                    </div>

                    <p v-if="testError" class="text-sm text-destructive">
                        {{ testError }}
                    </p>

                    <div
                        v-if="testResult"
                        class="space-y-2 rounded-md bg-muted/30 p-3 text-sm"
                    >
                        <p>
                            <span class="font-medium">Base URL:</span>
                            {{ testResult.base_url }}
                        </p>
                        <p>
                            <span class="font-medium">DNS resolution:</span>
                            <span
                                v-if="testResult.dns.resolved"
                                class="text-green-600"
                            >
                                Resolved {{ testResult.dns.host }} →
                                {{ testResult.dns.ip }}
                            </span>
                            <span v-else class="text-destructive">
                                Could not resolve "{{ testResult.dns.host }}" —
                                this server's DNS doesn't know this host. If
                                it's only reachable via VPN/private network,
                                this server likely isn't on it.
                            </span>
                        </p>
                        <p>
                            <span class="font-medium">Network reachable:</span>
                            <span
                                v-if="testResult.connection.reachable"
                                class="text-green-600"
                            >
                                {{ testResult.connection.message }}
                            </span>
                            <span v-else class="text-destructive">
                                {{
                                    testResult.connection.message ||
                                    'Connection failed.'
                                }}
                            </span>
                        </p>
                        <p v-if="testResult.ip_connection">
                            <span class="font-medium"
                                >Reachable by IP ({{
                                    testResult.ip_connection.ip
                                }}, bypassing DNS):</span
                            >
                            <span
                                v-if="testResult.ip_connection.reachable"
                                class="text-green-600"
                            >
                                {{ testResult.ip_connection.message }} — DNS is
                                the only problem; adding this host to /etc/hosts
                                on the server would fix it.
                            </span>
                            <span v-else class="text-destructive">
                                {{
                                    testResult.ip_connection.message ||
                                    'Connection failed.'
                                }}
                                — the server has no network route to this host
                                at all, not just a DNS problem. A VPN client or
                                firewall rule is needed on the server itself.
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
