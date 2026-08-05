<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();

const dismissed = ref({ success: false, error: false });

watch(
    () => page.props.flash?.success,
    (message) => {
        if (!message) return;
        dismissed.value.success = false;
        setTimeout(() => {
            dismissed.value.success = true;
        }, 5000);
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (message) => {
        if (message) {
            dismissed.value.error = false;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div
        v-if="
            (page.props.flash?.success && !dismissed.success) ||
            (page.props.flash?.error && !dismissed.error)
        "
        class="pointer-events-none fixed top-4 right-4 z-50 flex w-full max-w-sm flex-col gap-2"
    >
        <Alert
            v-if="page.props.flash?.success && !dismissed.success"
            class="pointer-events-auto border-green-500/30 bg-green-50 pr-10 shadow-lg dark:bg-green-950/30"
        >
            <AlertDescription class="text-green-800 dark:text-green-300">
                {{ page.props.flash.success }}
            </AlertDescription>
            <button
                class="absolute top-3 right-3 cursor-pointer text-muted-foreground hover:text-foreground"
                @click="dismissed.success = true"
            >
                <X class="h-4 w-4" />
            </button>
        </Alert>
        <Alert
            v-if="page.props.flash?.error && !dismissed.error"
            variant="destructive"
            class="pointer-events-auto pr-10 shadow-lg"
        >
            <AlertDescription>
                {{ page.props.flash.error }}
            </AlertDescription>
            <button
                class="absolute top-3 right-3 cursor-pointer text-muted-foreground hover:text-foreground"
                @click="dismissed.error = true"
            >
                <X class="h-4 w-4" />
            </button>
        </Alert>
    </div>
</template>
