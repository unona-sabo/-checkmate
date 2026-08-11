<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Award } from 'lucide-vue-next';
import { ref } from 'vue';
import AchievementBadge from '@/components/AchievementBadge.vue';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/AppLayout.vue';
import { ACHIEVEMENT_BADGE_LIST } from '@/lib/achievement-badges';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Achievements Demo', href: '/achievements-demo' },
];

const animated = ref(true);
</script>

<template>
    <Head title="Achievements Demo" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="flex items-start gap-2 text-2xl font-bold tracking-tight"
                    >
                        <Award class="mt-1 h-6 w-6 shrink-0 text-primary" />
                        Achievements Demo
                    </h1>
                    <p class="text-muted-foreground">
                        Dev-only preview of every achievement badge, locked and
                        unlocked, for verifying artwork and animations.
                    </p>
                </div>
                <label
                    class="flex cursor-pointer items-center gap-2 text-sm font-medium"
                >
                    <Checkbox
                        :model-value="animated"
                        @update:model-value="(v) => (animated = v === true)"
                    />
                    Animations enabled
                </label>
            </div>

            <div class="space-y-3">
                <h2 class="text-lg font-semibold">Unlocked</h2>
                <div
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
                >
                    <div
                        v-for="badge in ACHIEVEMENT_BADGE_LIST"
                        :key="`unlocked-${badge.id}`"
                        class="flex flex-col items-center gap-2 rounded-xl border bg-card p-4 text-center"
                    >
                        <AchievementBadge
                            :id="badge.id"
                            :unlocked="true"
                            :size="80"
                            :animated="animated"
                        />
                        <div>
                            <p class="text-sm font-medium">
                                {{ badge.name }}
                            </p>
                            <p
                                class="text-xs text-muted-foreground"
                                :title="badge.description"
                            >
                                {{ badge.animation }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <h2 class="text-lg font-semibold">Locked</h2>
                <div
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
                >
                    <div
                        v-for="badge in ACHIEVEMENT_BADGE_LIST"
                        :key="`locked-${badge.id}`"
                        class="flex flex-col items-center gap-2 rounded-xl border bg-card p-4 text-center"
                    >
                        <AchievementBadge
                            :id="badge.id"
                            :unlocked="false"
                            :size="80"
                            :animated="animated"
                        />
                        <p class="text-sm font-medium">{{ badge.name }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
