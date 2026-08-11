<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AchievementBadge from '@/components/AchievementBadge.vue';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { ACHIEVEMENT_BADGE_LIST, type BadgeId } from '@/lib/achievement-badges';
import { type BreadcrumbItem } from '@/types';

interface AchievementState {
    unlocked: boolean;
    unlocked_at: string | null;
}

const props = defineProps<{
    achievements: Record<BadgeId, AchievementState>;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Achievements', href: '/settings/achievements' },
];

const unlockedCount = computed(
    () => Object.values(props.achievements).filter((a) => a.unlocked).length,
);
const totalCount = computed(() => ACHIEVEMENT_BADGE_LIST.length);
const progressPercent = computed(() =>
    totalCount.value
        ? Math.round((unlockedCount.value / totalCount.value) * 100)
        : 0,
);

function formatDate(iso: string | null): string {
    if (!iso) return '';

    return new Date(iso).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Achievements" />
        <SettingsLayout>
            <div class="flex flex-col gap-6">
                <Heading
                    title="Achievements"
                    description="Badges you've earned by using CheckMate."
                />

                <div
                    class="achievements-progress relative overflow-hidden rounded-xl border border-amber-400/30 p-4"
                >
                    <div
                        class="relative z-10 flex items-center justify-between gap-4"
                    >
                        <div>
                            <p
                                class="text-xs font-medium tracking-wide text-amber-800 uppercase dark:text-amber-300"
                            >
                                Your progress
                            </p>
                            <p class="text-lg font-semibold">
                                {{ unlockedCount }} / {{ totalCount }} unlocked
                            </p>
                        </div>
                        <span
                            class="text-2xl font-bold text-amber-600 dark:text-amber-400"
                        >
                            {{ progressPercent }}%
                        </span>
                    </div>
                    <div
                        class="relative z-10 mt-3 h-2 overflow-hidden rounded-full bg-amber-950/10 dark:bg-black/30"
                    >
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-500 transition-all duration-500"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                </div>

                <div
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4"
                >
                    <div
                        v-for="badge in ACHIEVEMENT_BADGE_LIST"
                        :key="badge.id"
                        class="achievement-card relative flex flex-col items-center gap-2 overflow-hidden rounded-xl border p-4 text-center transition-shadow"
                        :class="
                            props.achievements[badge.id].unlocked
                                ? 'achievement-card--unlocked border-amber-400/30'
                                : 'border-border/60 bg-card'
                        "
                    >
                        <AchievementBadge
                            :id="badge.id"
                            :unlocked="props.achievements[badge.id].unlocked"
                            :size="80"
                            class="relative z-10"
                        />
                        <div class="relative z-10">
                            <p class="text-sm font-medium">
                                {{ badge.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ badge.description }}
                            </p>
                            <p
                                v-if="props.achievements[badge.id].unlocked"
                                class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-400"
                            >
                                Unlocked
                                {{
                                    formatDate(
                                        props.achievements[badge.id]
                                            .unlocked_at,
                                    )
                                }}
                            </p>
                            <p
                                v-else
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Locked
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

<style scoped>
.achievements-progress {
    background: linear-gradient(
        -45deg,
        rgba(253, 224, 71, 0.18),
        rgba(251, 191, 36, 0.12),
        rgba(254, 243, 199, 0.2),
        rgba(251, 191, 36, 0.12)
    );
    background-size: 300% 300%;
    animation: achievements-progress-shimmer 10s ease infinite;
}

:global(.dark) .achievements-progress {
    background: linear-gradient(
        -45deg,
        rgba(120, 53, 15, 0.35),
        rgba(146, 64, 14, 0.25),
        rgba(180, 83, 9, 0.3),
        rgba(146, 64, 14, 0.25)
    );
    background-size: 300% 300%;
    animation: achievements-progress-shimmer 10s ease infinite;
}

@keyframes achievements-progress-shimmer {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

.achievement-card--unlocked {
    background: radial-gradient(
        circle at 50% 0%,
        rgba(253, 224, 71, 0.16),
        transparent 70%
    );
}

:global(.dark) .achievement-card--unlocked {
    background: radial-gradient(
        circle at 50% 0%,
        rgba(251, 191, 36, 0.12),
        transparent 70%
    );
}

.achievement-card--unlocked:hover {
    box-shadow: 0 4px 16px -4px rgba(217, 119, 6, 0.25);
}

@media (prefers-reduced-motion: reduce) {
    .achievements-progress {
        animation: none;
    }
}
</style>
