<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { ArrowLeft } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const goBack = () => {
    // If there are breadcrumbs, go to the previous breadcrumb
    if (props.breadcrumbs && props.breadcrumbs.length > 1) {
        const previousBreadcrumb = props.breadcrumbs[props.breadcrumbs.length - 2];
        if (previousBreadcrumb.href) {
            router.visit(previousBreadcrumb.href);
            return;
        }
    }
    // Otherwise use browser history
    window.history.back();
};
</script>

<template>
    <header class="border-b border-sidebar-border/70">
        <div
            class="flex h-16 shrink-0 items-center gap-2 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
        >
            <div class="flex items-center gap-2">
                <SidebarTrigger class="-ml-1" />
                <template v-if="breadcrumbs && breadcrumbs.length > 0">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </template>
            </div>
        </div>
        <div v-if="breadcrumbs && breadcrumbs.length > 1" class="px-6 pb-3 md:px-4">
            <button
                @click="goBack"
                class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
            >
                <ArrowLeft class="h-4 w-4" />
                Back
            </button>
        </div>
    </header>
</template>
