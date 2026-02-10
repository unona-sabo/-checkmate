<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col items-center justify-center p-8">
            <div class="flex flex-col items-center gap-6">
                <img src="/images/logo.svg" alt="CheckMate" class="h-48 w-auto" />
                <p class="text-2xl font-medium text-muted-foreground italic">
                    Checkmate your bugs
                </p>
            </div>
        </div>
    </AppLayout>
</template>
