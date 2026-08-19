<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';
import { MARATHON_TOAST_VARIANTS } from '@/lib/achievement-badges';
import { pushAchievementToast } from '@/lib/achievement-toast-bus';

const STORAGE_KEY = 'checkmate_focus_session';
const HOUR_MS = 60 * 60 * 1000;
const CHECK_INTERVAL_MS = 30_000;
// A gap this much bigger than the check interval means the tab was
// backgrounded/throttled or the machine slept — treat it as the user having
// stepped away, not continuous activity, and start a fresh session.
const IDLE_RESET_MS = 15 * 60 * 1000;

interface FocusSession {
    startedAt: number;
    lastFiredHour: number;
    lastTickAt: number;
}

let intervalId: number | undefined;

function loadSession(): FocusSession | null {
    const raw = sessionStorage.getItem(STORAGE_KEY);

    if (!raw) return null;

    try {
        return JSON.parse(raw) as FocusSession;
    } catch {
        return null;
    }
}

function saveSession(session: FocusSession): void {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session));
}

function tick(): void {
    const now = Date.now();
    const existing = loadSession();

    if (!existing || now - existing.lastTickAt > IDLE_RESET_MS) {
        saveSession({ startedAt: now, lastFiredHour: 0, lastTickAt: now });

        return;
    }

    const elapsedHours = Math.floor((now - existing.startedAt) / HOUR_MS);

    if (elapsedHours <= existing.lastFiredHour) {
        saveSession({ ...existing, lastTickAt: now });

        return;
    }

    saveSession({ ...existing, lastFiredHour: elapsedHours, lastTickAt: now });

    if (elapsedHours === 1) {
        fetch('/focus-sessions/ping', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
                Accept: 'application/json',
            },
        }).catch(() => {});
    }

    const variant =
        MARATHON_TOAST_VARIANTS[
            Math.floor(Math.random() * MARATHON_TOAST_VARIANTS.length)
        ];

    pushAchievementToast({
        key: 'marathon',
        name: `Marathon — ${elapsedHours} hour${elapsedHours > 1 ? 's' : ''} straight`,
        srcOverride: variant,
    });
}

function handleVisibilityChange(): void {
    if (document.visibilityState === 'visible') tick();
}

onMounted(() => {
    tick();
    intervalId = window.setInterval(tick, CHECK_INTERVAL_MS);
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    if (intervalId) window.clearInterval(intervalId);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
