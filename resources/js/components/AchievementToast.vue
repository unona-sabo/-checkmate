<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import AchievementBadge from '@/components/AchievementBadge.vue';
import type { BadgeId } from '@/lib/achievement-badges';
import {
    achievementToastQueue,
    type AchievementToastItem,
} from '@/lib/achievement-toast-bus';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();

const queue = ref<AchievementToastItem[]>([]);
const current = ref<AchievementToastItem | null>(null);
const visible = ref(false);

watch(
    () => page.props.flash?.achievement,
    (items) => {
        if (items && items.length > 0) {
            queue.value.push(...items);
            showNext();
        }
    },
    { immediate: true },
);

watch(
    () => achievementToastQueue.length,
    () => {
        if (achievementToastQueue.length > 0) {
            queue.value.push(...achievementToastQueue.splice(0));
            showNext();
        }
    },
    { immediate: true },
);

function showNext() {
    if (current.value || queue.value.length === 0) return;

    current.value = queue.value.shift() ?? null;
    visible.value = false;

    nextTick(() => {
        visible.value = true;
    });

    setTimeout(() => {
        visible.value = false;
        setTimeout(() => {
            current.value = null;
            showNext();
        }, 300);
    }, 4000);
}
</script>

<template>
    <div
        class="pointer-events-none fixed right-4 bottom-4 z-50"
        aria-live="polite"
    >
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-3 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-300 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-3 opacity-0 scale-95"
        >
            <div
                v-if="current && visible"
                class="achievement-toast-card pointer-events-auto relative flex items-center gap-3 overflow-hidden rounded-xl border border-amber-400/40 p-3 pr-5 shadow-lg shadow-amber-900/20"
            >
                <AchievementBadge
                    :id="current.key as BadgeId"
                    :unlocked="true"
                    :size="48"
                    :src-override="current.srcOverride"
                />
                <div class="relative z-10">
                    <p
                        class="text-xs font-medium tracking-wide text-amber-100/80 uppercase"
                    >
                        Achievement unlocked
                    </p>
                    <p class="text-sm font-semibold text-white">
                        {{ current.name }}
                    </p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.achievement-toast-card {
    background: linear-gradient(
        -45deg,
        #78350f,
        #b45309,
        #f59e0b,
        #fde68a,
        #b45309
    );
    background-size: 300% 300%;
    animation: achievement-toast-shimmer 3s ease infinite;
}

.achievement-toast-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        115deg,
        transparent 20%,
        rgba(255, 255, 255, 0.35) 35%,
        transparent 50%
    );
    background-size: 250% 100%;
    animation: achievement-toast-sweep 2.5s ease-in-out infinite;
}

@keyframes achievement-toast-shimmer {
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

@keyframes achievement-toast-sweep {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -50% 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .achievement-toast-card,
    .achievement-toast-card::before {
        animation: none;
    }
}
</style>
