<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bug,
    Search,
    ServerCrash,
    ShieldAlert,
    Wrench,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import AuthSimpleLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import { dashboard } from '@/routes';

const props = defineProps<{
    status: number;
}>();

const content = computed(() => {
    switch (props.status) {
        case 403:
            return {
                title: '403 — Access Denied',
                description:
                    "You don't have permission to view this page. If that seems wrong, check with your workspace owner.",
                icon: ShieldAlert,
            };
        case 419:
            return {
                title: '419 — Page Expired',
                description:
                    'Your session took a little too long. Refresh the page and give it another go.',
                icon: Wrench,
            };
        case 500:
            return {
                title: '500 — Something Went Wrong',
                description:
                    "That one's on us. We've logged it — please try again in a moment.",
                icon: ServerCrash,
            };
        case 503:
            return {
                title: '503 — Down for Maintenance',
                description:
                    "We're running some quick maintenance. Back shortly.",
                icon: Wrench,
            };
        default:
            return {
                title: '404 — Page Not Found',
                description:
                    'Looks like this page slipped through the cracks — kind of like an untested edge case.',
                icon: Bug,
            };
    }
});
</script>

<template>
    <Head :title="content.title" />

    <AuthSimpleLayout :title="content.title" :description="content.description">
        <div class="flex flex-col items-center gap-6">
            <div class="error-illustration relative h-32 w-32 shrink-0">
                <div
                    class="absolute inset-0 rounded-full bg-gradient-to-br from-amber-200 to-yellow-300 opacity-60 blur-xl dark:from-amber-900/40 dark:to-yellow-900/40"
                />
                <div
                    class="relative flex h-32 w-32 items-center justify-center rounded-full border border-amber-300/60 bg-gradient-to-br from-amber-50 to-yellow-50 shadow-lg dark:border-amber-700/40 dark:from-amber-950/30 dark:to-yellow-950/30"
                >
                    <component
                        :is="content.icon"
                        class="error-illustration-icon h-14 w-14 text-amber-600 dark:text-amber-400"
                    />
                    <Search
                        v-if="status === 404"
                        class="error-illustration-search absolute -right-1 -bottom-1 h-11 w-11 text-primary"
                    />
                </div>
            </div>

            <Button as-child class="cursor-pointer gap-2">
                <Link :href="dashboard()">
                    <ArrowLeft class="h-4 w-4" />
                    Back to Dashboard
                </Link>
            </Button>
        </div>
    </AuthSimpleLayout>
</template>

<style scoped>
.error-illustration-icon {
    animation: error-illustration-float 3s ease-in-out infinite;
}

.error-illustration-search {
    animation: error-illustration-scan 3s ease-in-out infinite;
}

@keyframes error-illustration-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}

@keyframes error-illustration-scan {
    0%,
    100% {
        transform: rotate(12deg) scale(1);
    }
    50% {
        transform: rotate(-6deg) scale(1.08);
    }
}

@media (prefers-reduced-motion: reduce) {
    .error-illustration-icon,
    .error-illustration-search {
        animation: none;
    }
}
</style>
