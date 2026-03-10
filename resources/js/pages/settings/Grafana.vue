<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    settings: {
        has_token: boolean;
        base_url: string | null;
        datasource_id: string | null;
        log_path: string | null;
    };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Grafana settings', href: '/settings/grafana' },
];

const form = useForm({
    api_token: '',
    base_url: props.settings.base_url ?? '',
    datasource_id: props.settings.datasource_id ?? '',
    log_path: props.settings.log_path ?? '',
});

function save() {
    form.put('/settings/grafana', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('api_token');
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Grafana Settings" />
        <SettingsLayout>
            <div class="flex flex-col gap-6">
                <Heading title="Grafana / Loki" description="Configure Grafana connection for the Payout Monitor." />

                <form class="space-y-6" @submit.prevent="save">
                    <div class="grid gap-4 rounded-lg border p-4">
                        <div class="space-y-2">
                            <Label for="api_token">Service Account Token</Label>
                            <Input
                                id="api_token"
                                v-model="form.api_token"
                                type="password"
                                :placeholder="settings.has_token ? '••••••••••••••••' : 'glsa_...'"
                            />
                            <p v-if="form.errors.api_token" class="text-destructive text-sm">
                                {{ form.errors.api_token }}
                            </p>
                            <p class="text-xs text-muted-foreground">Bearer token for Grafana API. Will be stored encrypted. Leave empty to keep current token.</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="base_url">Grafana Base URL</Label>
                            <Input id="base_url" v-model="form.base_url" placeholder="https://logging.air.io" />
                            <p v-if="form.errors.base_url" class="text-destructive text-sm">
                                {{ form.errors.base_url }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="datasource_id">Loki Datasource ID or UID</Label>
                            <Input id="datasource_id" v-model="form.datasource_id" placeholder="1 or Q111_7" />
                            <p v-if="form.errors.datasource_id" class="text-destructive text-sm">
                                {{ form.errors.datasource_id }}
                            </p>
                            <p class="text-xs text-muted-foreground">Numeric ID or alphanumeric UID of the Loki datasource in Grafana.</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="log_path">Default Log File Path</Label>
                            <Input
                                id="log_path"
                                v-model="form.log_path"
                                placeholder="/home/accountant/app/storage/logs/payouts-{YYYY-MM-DD}.log"
                            />
                            <p v-if="form.errors.log_path" class="text-destructive text-sm">
                                {{ form.errors.log_path }}
                            </p>
                            <p class="text-xs text-muted-foreground">Use {YYYY-MM-DD} as a date placeholder. Will be auto-replaced.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="form.processing" class="cursor-pointer">
                            {{ form.processing ? 'Saving...' : 'Save Settings' }}
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
