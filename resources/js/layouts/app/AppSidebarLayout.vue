<script setup lang="ts">
import AchievementToast from '@/components/AchievementToast.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import FocusSessionTracker from '@/components/FocusSessionTracker.vue';
import ScrollToTop from '@/components/ScrollToTop.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <ScrollToTop />
        <FlashMessages />
        <AchievementToast />
        <FocusSessionTracker />
    </AppShell>
</template>
