<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4"
                >
                    <div
                        v-for="badge in ACHIEVEMENT_BADGE_LIST"
                        :key="badge.id"
                        class="flex flex-col items-center gap-2 rounded-xl border bg-card p-4 text-center"
                    >
                        <AchievementBadge
                            :id="badge.id"
                            :unlocked="props.achievements[badge.id].unlocked"
                            :size="80"
                        />
                        <div>
                            <p class="text-sm font-medium">
                                {{ badge.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ badge.description }}
                            </p>
                            <p
                                v-if="props.achievements[badge.id].unlocked"
                                class="mt-1 text-xs text-muted-foreground"
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
