<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { home } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: home().url,
    },
];
</script>

<template>
    <Head title="Home" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col items-center justify-center p-8">
            <div class="flex flex-col items-center gap-8">
                <img src="/images/logo2.png" alt="CheckMate" class="h-72 w-auto" />
                <p class="text-3xl font-light tracking-widest uppercase text-muted-foreground/80">
                    Checkmate your bugs
                </p>
            </div>
        </div>
    </AppLayout>
</template>
