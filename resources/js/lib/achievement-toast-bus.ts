import { reactive } from 'vue';

/**
 * Lets client-only code (e.g. FocusSessionTracker) trigger an achievement
 * toast without a server round trip, alongside the server-flashed queue
 * that AchievementToast.vue also watches.
 */
export interface AchievementToastItem {
    key: string;
    name: string;
    /** Overrides the badge's own artwork — used to show a random Marathon variant. */
    srcOverride?: string;
}

export const achievementToastQueue = reactive<AchievementToastItem[]>([]);

export function pushAchievementToast(item: AchievementToastItem): void {
    achievementToastQueue.push(item);
}
