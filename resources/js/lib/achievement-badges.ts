/**
 * Central metadata for the achievement badge system. Every badge's assets,
 * copy, and animation type live here — nowhere else should duplicate this
 * information. Add new badges by extending `ACHIEVEMENT_BADGES` only.
 */

export type BadgeAnimationType =
    | 'blink'
    | 'pulse-glow'
    | 'shake'
    | 'sparkles'
    | 'scan'
    | 'connect-glow'
    | 'trail'
    | 'bounce'
    | 'grow'
    | 'launch'
    | 'rotate-shine'
    | 'bob'
    | 'flicker'
    | 'legend-shimmer'
    | 'hourglass';

export type BadgeId =
    | 'first-blood'
    | 'bug-hunter'
    | 'exterminator'
    | 'checklist-champion'
    | 'detail-oriented'
    | 'clickup-connector'
    | 'speed-demon'
    | 'team-player'
    | 'grafana-guru'
    | 'project-starter'
    | 'perfectionist'
    | 'night-owl'
    | 'early-bird'
    | 'streak-master'
    | 'legend'
    | 'marathon';

export interface AchievementBadgeConfig {
    id: BadgeId;
    name: string;
    description: string;
    unlockedAsset: string;
    lockedAsset: string;
    animation: BadgeAnimationType;
    /** Animation loop duration in seconds (2-4s per design spec). */
    duration: number;
}

const asset = (file: string): string => `/images/badges/${file}`;

export const ACHIEVEMENT_BADGES: Record<BadgeId, AchievementBadgeConfig> = {
    'first-blood': {
        id: 'first-blood',
        name: 'First Bug',
        description: 'Reported your first bug',
        unlockedAsset: asset('01_first_blood_unlocked_80.png'),
        lockedAsset: asset('01_first_blood_locked_80.png'),
        animation: 'blink',
        duration: 3,
    },
    'bug-hunter': {
        id: 'bug-hunter',
        name: 'Bug Hunter',
        description: 'Found and reported 10 bugs',
        unlockedAsset: asset('02_bug_hunter_unlocked_80.png'),
        lockedAsset: asset('02_bug_hunter_locked_80.png'),
        animation: 'pulse-glow',
        duration: 2.5,
    },
    exterminator: {
        id: 'exterminator',
        name: 'Exterminator',
        description: 'Squashed 50 bugs',
        unlockedAsset: asset('03_exterminator_unlocked_80.png'),
        lockedAsset: asset('03_exterminator_locked_80.png'),
        animation: 'shake',
        duration: 2.5,
    },
    'checklist-champion': {
        id: 'checklist-champion',
        name: 'Checklist Champion',
        description: 'Completed 25 checklists',
        unlockedAsset: asset('04_checklist_champion_unlocked_80.png'),
        lockedAsset: asset('04_checklist_champion_locked_80.png'),
        animation: 'sparkles',
        duration: 3,
    },
    'detail-oriented': {
        id: 'detail-oriented',
        name: 'Detail Oriented',
        description: 'Left detailed notes on 20 test cases',
        unlockedAsset: asset('05_detail_oriented_unlocked_80.png'),
        lockedAsset: asset('05_detail_oriented_locked_80.png'),
        animation: 'scan',
        duration: 3,
    },
    'clickup-connector': {
        id: 'clickup-connector',
        name: 'ClickUp Connector',
        description: 'Connected a project to ClickUp',
        unlockedAsset: asset('06_clickup_connector_unlocked_80.png'),
        lockedAsset: asset('06_clickup_connector_locked_80.png'),
        animation: 'connect-glow',
        duration: 3,
    },
    'speed-demon': {
        id: 'speed-demon',
        name: 'Speed Demon',
        description: 'Resolved a bug within an hour of reporting',
        unlockedAsset: asset('07_speed_demon_unlocked_80.png'),
        lockedAsset: asset('07_speed_demon_locked_80.png'),
        animation: 'trail',
        duration: 2.5,
    },
    'team-player': {
        id: 'team-player',
        name: 'Team Player',
        description: 'Collaborated with teammates across 5+ projects',
        unlockedAsset: asset('08_team_player_unlocked_80.png'),
        lockedAsset: asset('08_team_player_locked_80.png'),
        animation: 'bounce',
        duration: 2.5,
    },
    'grafana-guru': {
        id: 'grafana-guru',
        name: 'Grafana Guru',
        description: 'Connected Grafana for payout monitoring',
        unlockedAsset: asset('09_grafana_guru_unlocked_80.png'),
        lockedAsset: asset('09_grafana_guru_locked_80.png'),
        animation: 'grow',
        duration: 3,
    },
    'project-starter': {
        id: 'project-starter',
        name: 'Project Starter',
        description: 'Created your first project',
        unlockedAsset: asset('10_project_starter_unlocked_80.png'),
        lockedAsset: asset('10_project_starter_locked_80.png'),
        animation: 'launch',
        duration: 3,
    },
    perfectionist: {
        id: 'perfectionist',
        name: 'Perfectionist',
        description: 'Achieved 100% test coverage on a release',
        unlockedAsset: asset('11_perfectionist_unlocked_80.png'),
        lockedAsset: asset('11_perfectionist_locked_80.png'),
        animation: 'rotate-shine',
        duration: 4,
    },
    'night-owl': {
        id: 'night-owl',
        name: 'Night Owl',
        description: 'Logged activity after midnight 10 times',
        unlockedAsset: asset('12_night_owl_unlocked_80.png'),
        lockedAsset: asset('12_night_owl_locked_80.png'),
        animation: 'blink',
        duration: 3,
    },
    'early-bird': {
        id: 'early-bird',
        name: 'Early Bird',
        description: 'Logged activity before 7am 10 times',
        unlockedAsset: asset('13_early_bird_unlocked_80.png'),
        lockedAsset: asset('13_early_bird_locked_80.png'),
        animation: 'bob',
        duration: 4,
    },
    'streak-master': {
        id: 'streak-master',
        name: 'Streak Master',
        description: 'Maintained a 7-day activity streak',
        unlockedAsset: asset('14_streak_master_unlocked_80.png'),
        lockedAsset: asset('14_streak_master_locked_80.png'),
        animation: 'flicker',
        duration: 2.5,
    },
    legend: {
        id: 'legend',
        name: 'Legend',
        description: 'Unlocked every other achievement',
        unlockedAsset: asset('15_legend_unlocked_80.png'),
        lockedAsset: asset('15_legend_locked_80.png'),
        animation: 'legend-shimmer',
        duration: 4,
    },
    marathon: {
        id: 'marathon',
        name: 'Marathon',
        description: 'Worked continuously in CheckMate for over an hour',
        // The permanent Achievements page always shows this one variant;
        // MARATHON_TOAST_VARIANTS below picks randomly among all of them
        // for the popup notification instead.
        unlockedAsset: asset(
            'marathon/01_marathon_hourglass_sparkle_unlocked_80.png',
        ),
        lockedAsset: asset(
            'marathon/01_marathon_hourglass_sparkle_locked_80.png',
        ),
        animation: 'hourglass',
        duration: 3,
    },
};

export const ACHIEVEMENT_BADGE_LIST: AchievementBadgeConfig[] =
    Object.values(ACHIEVEMENT_BADGES);

/**
 * All artwork variants for the Marathon badge. The unlock toast picks one
 * at random each time it fires; the Settings page always uses the single
 * asset stored on `ACHIEVEMENT_BADGES.marathon` above.
 */
export const MARATHON_TOAST_VARIANTS: string[] = [
    asset('marathon/01_marathon_hourglass_sparkle_unlocked_80.png'),
    asset('marathon/02_marathon_hourglass_clock_unlocked_80.png'),
    asset('marathon/03_marathon_hourglass_ribbon_unlocked_80.png'),
    asset('marathon/04_marathon_stopwatch_unlocked_80.png'),
    asset('marathon/05_marathon_hourglass_crown_unlocked_80.png'),
    asset('marathon/06_marathon_progress_hourglass_unlocked_80.png'),
];
