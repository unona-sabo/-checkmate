<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { home } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: home().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-6">
            <div
                class="mb-6 flex flex-col items-center gap-4 rounded-xl border border-border bg-card p-6 text-center sm:flex-row sm:items-center sm:gap-6 sm:text-left"
            >
                <div class="shrink-0 text-center">
                    <img
                        src="/images/logo2.png"
                        alt="CheckMate"
                        class="h-20 w-auto rounded-2xl sm:h-32"
                    />
                    <p
                        class="mt-2 text-xs font-light tracking-widest text-muted-foreground/80 uppercase"
                    >
                        Checkmate your bugs
                    </p>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold">CheckMate</h1>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        An all-in-one QA management platform for teams of any
                        size. Organize checklists, build and execute test
                        suites, track bug reports, maintain living
                        documentation, and capture notes — all in a single
                        workspace. Designed to streamline your quality assurance
                        workflow from planning through release.
                    </p>
                </div>
            </div>

            <div
                class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border bg-card/50 px-6 py-16 text-center"
            >
                <Activity class="h-8 w-8 text-muted-foreground/60" />
                <p class="text-sm font-medium text-muted-foreground">
                    Workspace activity is coming soon
                </p>
                <p class="max-w-sm text-xs text-muted-foreground/70">
                    This is where you'll see what's happening across your
                    workspace.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
