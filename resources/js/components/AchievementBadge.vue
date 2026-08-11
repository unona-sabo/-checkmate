<script setup lang="ts">
import { computed } from 'vue';
import {
    ACHIEVEMENT_BADGES,
    type BadgeAnimationType,
    type BadgeId,
} from '@/lib/achievement-badges';

const props = withDefaults(
    defineProps<{
        id: BadgeId;
        unlocked: boolean;
        size?: number;
        animated?: boolean;
        className?: string;
        /** Shows this asset instead of the badge's own — used by the toast to pick a random Marathon variant. */
        srcOverride?: string;
    }>(),
    {
        size: 80,
        animated: true,
        className: '',
    },
);

const badge = computed(() => ACHIEVEMENT_BADGES[props.id]);

const src = computed(
    () =>
        props.srcOverride ??
        (props.unlocked ? badge.value.unlockedAsset : badge.value.lockedAsset),
);

const label = computed(() =>
    props.unlocked
        ? `${badge.value.name} achievement`
        : `${badge.value.name} achievement, locked`,
);

// Maps each badge's single `animation` type to the concrete CSS classes that
// realize it: an optional class on the <img> itself (movement/filter on the
// artwork), an optional class on the outer root (for glow, which must sit
// outside the circular frame's overflow:hidden clip), and an optional named
// decorative overlay rendered only for animation types that need one.
const ANIMATION_PRESETS: Record<
    BadgeAnimationType,
    { img?: string; root?: string; decoration?: string }
> = {
    blink: { img: 'anim-blink' },
    'pulse-glow': { img: 'anim-pulse', root: 'anim-glow-green' },
    shake: { root: 'anim-shake' },
    sparkles: { decoration: 'sparkles' },
    scan: { decoration: 'scan' },
    'connect-glow': { img: 'anim-connect', root: 'anim-glow-pink' },
    trail: { img: 'anim-trail-img', decoration: 'trail' },
    bounce: { img: 'anim-bounce' },
    grow: { img: 'anim-grow' },
    launch: { img: 'anim-launch', decoration: 'smoke' },
    'rotate-shine': { img: 'anim-rotate', decoration: 'shine' },
    bob: { img: 'anim-bob' },
    flicker: { img: 'anim-flicker' },
    'legend-shimmer': { img: 'anim-legend-hue', decoration: 'legend-ring' },
    hourglass: { root: 'anim-glow-amber' },
};

const isActive = computed(() => props.unlocked && props.animated);

const preset = computed(() => ANIMATION_PRESETS[badge.value.animation]);

const rootClass = computed(() =>
    isActive.value && preset.value.root ? preset.value.root : '',
);

const imgClass = computed(() =>
    isActive.value && preset.value.img ? preset.value.img : '',
);

const decoration = computed(() =>
    isActive.value ? preset.value.decoration : undefined,
);

const rootStyle = computed(() => ({
    width: `${props.size}px`,
    height: `${props.size}px`,
    '--badge-duration': `${badge.value.duration}s`,
}));
</script>

<template>
    <span
        class="achievement-badge"
        :class="[
            rootClass,
            className,
            { 'achievement-badge--locked': !unlocked },
        ]"
        :style="rootStyle"
        :title="badge.description"
    >
        <span class="achievement-badge__frame">
            <img
                :src="src"
                :alt="label"
                class="achievement-badge__img"
                :class="imgClass"
                draggable="false"
                loading="lazy"
            />

            <template v-if="decoration === 'sparkles'">
                <span class="sparkle sparkle-1" aria-hidden="true" />
                <span class="sparkle sparkle-2" aria-hidden="true" />
                <span class="sparkle sparkle-3" aria-hidden="true" />
            </template>

            <span
                v-else-if="decoration === 'scan'"
                class="scan-line"
                aria-hidden="true"
            />

            <span
                v-else-if="decoration === 'trail'"
                class="trail-streak"
                aria-hidden="true"
            />

            <span
                v-else-if="decoration === 'smoke'"
                class="smoke-puff"
                aria-hidden="true"
            />

            <span
                v-else-if="decoration === 'shine'"
                class="shine-sweep"
                aria-hidden="true"
            />

            <span
                v-else-if="decoration === 'legend-ring'"
                class="legend-ring"
                aria-hidden="true"
            >
                <span class="legend-dot legend-dot-1" />
                <span class="legend-dot legend-dot-2" />
                <span class="legend-dot legend-dot-3" />
            </span>
        </span>
    </span>
</template>

<style scoped>
.achievement-badge {
    position: relative;
    display: inline-block;
    flex-shrink: 0;
    line-height: 0;
}

.achievement-badge--locked {
    opacity: 0.9;
}

.achievement-badge__frame {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    border-radius: 50%;
}

.achievement-badge__img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    user-select: none;
    pointer-events: none;
}

/* ---- Image-level animations (transform/filter only) ---- */

.anim-blink {
    animation: badge-blink var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-blink {
    0%,
    92%,
    100% {
        transform: scaleY(1);
    }
    96% {
        transform: scaleY(0.92);
    }
}

.anim-pulse {
    animation: badge-pulse var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-pulse {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

.anim-connect {
    animation: badge-connect var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-connect {
    0%,
    70%,
    100% {
        transform: scale(1);
    }
    85% {
        transform: scale(1.04);
    }
}

.anim-bounce {
    animation: badge-bounce var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-bounce {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}

.anim-bob {
    animation: badge-bob var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-bob {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}

.anim-grow {
    animation: badge-grow var(--badge-duration) ease-in-out infinite;
    transform-origin: bottom center;
}
@keyframes badge-grow {
    0%,
    100% {
        transform: scaleY(0.96);
        filter: brightness(1);
    }
    60% {
        transform: scaleY(1.02);
        filter: brightness(1.06);
    }
}

.anim-launch {
    animation: badge-launch var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-launch {
    0%,
    20%,
    100% {
        transform: translateY(0);
    }
    55% {
        transform: translateY(-4px);
    }
}

.anim-rotate {
    animation: badge-rotate var(--badge-duration) linear infinite;
}
@keyframes badge-rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.anim-flicker {
    animation: badge-flicker var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-flicker {
    0%,
    100% {
        transform: scale(1, 1);
        filter: brightness(1);
    }
    30% {
        transform: scale(0.98, 1.03);
        filter: brightness(1.05);
    }
    60% {
        transform: scale(1.02, 0.98);
        filter: brightness(0.97);
    }
    80% {
        transform: scale(0.99, 1.01);
        filter: brightness(1.02);
    }
}

.anim-trail-img {
    animation: badge-trail-img var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-trail-img {
    0%,
    75%,
    100% {
        transform: translateX(0);
    }
    88% {
        transform: translateX(2px);
    }
}

.anim-legend-hue {
    animation: badge-legend-hue var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-legend-hue {
    0%,
    100% {
        filter: hue-rotate(0deg);
    }
    50% {
        filter: hue-rotate(14deg);
    }
}

/* ---- Root-level glow (must live outside the frame's overflow:hidden) ---- */

.anim-glow-amber {
    animation: badge-glow-amber var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-glow-amber {
    0%,
    85%,
    100% {
        filter: drop-shadow(0 0 0 rgba(245, 158, 11, 0));
    }
    92% {
        filter: drop-shadow(0 0 5px rgba(245, 158, 11, 0.7));
    }
}

.anim-glow-green {
    animation: badge-glow-green var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-glow-green {
    0%,
    100% {
        filter: drop-shadow(0 0 0 rgba(34, 197, 94, 0));
    }
    50% {
        filter: drop-shadow(0 0 6px rgba(34, 197, 94, 0.6));
    }
}

.anim-glow-pink {
    animation: badge-glow-pink var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-glow-pink {
    0%,
    70%,
    100% {
        filter: drop-shadow(0 0 0 rgba(236, 72, 153, 0));
    }
    85% {
        filter: drop-shadow(0 0 5px rgba(236, 72, 153, 0.6));
    }
}

.anim-shake {
    animation: badge-shake var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-shake {
    0%,
    40%,
    100% {
        transform: translateX(0);
    }
    10% {
        transform: translateX(-2px);
    }
    20% {
        transform: translateX(2px);
    }
    30% {
        transform: translateX(-1px);
    }
}

/* ---- Decorative overlays (positioned as static %, animate opacity/transform only) ---- */

.sparkle {
    position: absolute;
    width: 6px;
    height: 6px;
    background: #fde047;
    box-shadow: 0 0 4px 1px rgba(253, 224, 71, 0.9);
    transform: scale(0.8) rotate(45deg);
    opacity: 0;
    animation: badge-sparkle var(--badge-duration) ease-in-out infinite;
}
.sparkle-1 {
    top: 12%;
    right: 16%;
    animation-delay: 0s;
}
.sparkle-2 {
    bottom: 18%;
    left: 14%;
    animation-delay: calc(var(--badge-duration) / 3);
}
.sparkle-3 {
    top: 20%;
    left: 20%;
    animation-delay: calc(var(--badge-duration) / 3 * 2);
}
@keyframes badge-sparkle {
    0%,
    100% {
        opacity: 0;
        transform: scale(0.8) rotate(45deg);
    }
    30% {
        opacity: 1;
        transform: scale(1) rotate(45deg);
    }
    60% {
        opacity: 0;
        transform: scale(0.8) rotate(45deg);
    }
}

.scan-line {
    position: absolute;
    inset: 0 0 0 0;
    width: 18%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(125, 211, 252, 0.85),
        transparent
    );
    transform: translateX(-120%);
    animation: badge-scan var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-scan {
    0% {
        transform: translateX(-120%);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translateX(650%);
        opacity: 0;
    }
}

.trail-streak {
    position: absolute;
    top: 50%;
    left: 0;
    width: 40%;
    height: 3px;
    margin-top: -1.5px;
    background: linear-gradient(90deg, transparent, rgba(251, 191, 36, 0.8));
    opacity: 0;
    animation: badge-trail-streak var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-trail-streak {
    0%,
    75%,
    100% {
        opacity: 0;
        transform: translateX(0);
    }
    88% {
        opacity: 1;
        transform: translateX(6px);
    }
}

.smoke-puff {
    position: absolute;
    bottom: 8%;
    left: 50%;
    width: 26%;
    height: 26%;
    margin-left: -13%;
    border-radius: 50%;
    background: radial-gradient(
        circle,
        rgba(226, 232, 240, 0.85),
        transparent 70%
    );
    opacity: 0;
    transform: scale(0.6);
    animation: badge-smoke var(--badge-duration) ease-in-out infinite;
}
@keyframes badge-smoke {
    0%,
    20%,
    100% {
        opacity: 0;
        transform: scale(0.6);
    }
    55% {
        opacity: 0.8;
        transform: scale(1.3);
    }
    80% {
        opacity: 0;
        transform: scale(1.6);
    }
}

.shine-sweep {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        115deg,
        transparent 35%,
        rgba(255, 255, 255, 0.55) 50%,
        transparent 65%
    );
    transform: translateX(-100%);
    animation: badge-shine var(--badge-duration) linear infinite;
}
@keyframes badge-shine {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.legend-ring {
    position: absolute;
    inset: 0;
    animation: badge-legend-ring var(--badge-duration) linear infinite;
}
@keyframes badge-legend-ring {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
.legend-dot {
    position: absolute;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #fde047;
    box-shadow: 0 0 4px 1px rgba(253, 224, 71, 0.8);
}
.legend-dot-1 {
    top: 4%;
    left: 50%;
}
.legend-dot-2 {
    top: 50%;
    right: 4%;
}
.legend-dot-3 {
    bottom: 4%;
    left: 20%;
}

/* ---- Reduced motion: freeze everything, keep the badge itself visible ---- */
@media (prefers-reduced-motion: reduce) {
    .achievement-badge *,
    .achievement-badge {
        animation: none !important;
        transition: none !important;
    }
    .sparkle,
    .scan-line,
    .trail-streak,
    .smoke-puff,
    .shine-sweep,
    .legend-ring {
        display: none;
    }
}
</style>
