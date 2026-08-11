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
    | 'marathon'
    | 'first-test-suite'
    | 'first-checklist'
    | 'first-document'
    | 'first-note'
    | 'first-test-run'
    | 'first-release'
    | 'first-ai-generation'
    | 'first-5-checklists'
    | 'first-5-test-cases'
    | 'completed-1-test-run'
    | 'first-design'
    | 'first-test-data'
    | 'first-5-documents'
    | 'first-5-notes'
    | 'good-work-day';

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
    // "First X" achievements first — they're the easiest to reach and the
    // most common ones new users unlock, so they lead the Achievements page.
    'first-blood': {
        id: 'first-blood',
        name: 'First Bug',
        description: 'Reported your first bug',
        unlockedAsset: asset('01_first_bug_unlocked_80.png'),
        lockedAsset: asset('01_first_bug_locked_80.png'),
        animation: 'blink',
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
    'first-test-suite': {
        id: 'first-test-suite',
        name: 'Suite Starter',
        description: 'Created your first test suite',
        unlockedAsset: asset('first_test_suite_unlocked.png'),
        lockedAsset: asset('first_test_suite_locked.png'),
        animation: 'grow',
        duration: 3,
    },
    'first-checklist': {
        id: 'first-checklist',
        name: 'Checklist Creator',
        description: 'Created your first checklist',
        unlockedAsset: asset('first_checklist_unlocked.png'),
        lockedAsset: asset('first_checklist_locked.png'),
        animation: 'sparkles',
        duration: 3,
    },
    'first-document': {
        id: 'first-document',
        name: 'Documentarian',
        description: 'Created your first document',
        unlockedAsset: asset('first_document_unlocked.png'),
        lockedAsset: asset('first_document_locked.png'),
        animation: 'scan',
        duration: 3,
    },
    'first-note': {
        id: 'first-note',
        name: 'Note Taker',
        description: 'Created your first note',
        unlockedAsset: asset('first_note_unlocked.png'),
        lockedAsset: asset('first_note_locked.png'),
        animation: 'blink',
        duration: 3,
    },
    'first-test-run': {
        id: 'first-test-run',
        name: 'Test Runner',
        description: 'Started your first test run',
        unlockedAsset: asset('first_test_run_unlocked.png'),
        lockedAsset: asset('first_test_run_locked.png'),
        animation: 'launch',
        duration: 3,
    },
    'first-release': {
        id: 'first-release',
        name: 'Release Manager',
        description: 'Created your first release',
        unlockedAsset: asset('first_release_unlocked.png'),
        lockedAsset: asset('first_release_locked.png'),
        animation: 'rotate-shine',
        duration: 4,
    },
    'first-ai-generation': {
        id: 'first-ai-generation',
        name: 'AI Pioneer',
        description: 'Generated test cases with AI for the first time',
        unlockedAsset: asset('first_ai_generation_unlocked.png'),
        lockedAsset: asset('first_ai_generation_locked.png'),
        animation: 'pulse-glow',
        duration: 2.5,
    },
    'first-design': {
        id: 'first-design',
        name: 'Design Debut',
        description: 'Added your first design link',
        unlockedAsset: asset('first_design_unlocked.png'),
        lockedAsset: asset('first_design_locked.png'),
        animation: 'scan',
        duration: 3,
    },
    'first-test-data': {
        id: 'first-test-data',
        name: 'Data Ready',
        description: 'Added your first test data',
        unlockedAsset: asset('first_test_data_unlocked.png'),
        lockedAsset: asset('first_test_data_locked.png'),
        animation: 'pulse-glow',
        duration: 3,
    },
    'completed-1-test-run': {
        id: 'completed-1-test-run',
        name: 'Run Closer',
        description: 'Completed your first test run',
        unlockedAsset: asset('completed_1_test_run_unlocked.png'),
        lockedAsset: asset('completed_1_test_run_locked.png'),
        animation: 'bounce',
        duration: 2.5,
    },

    // Progress/milestone achievements next.
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
    marathon: {
        id: 'marathon',
        name: 'Marathon',
        description: 'Worked continuously in CheckMate for over an hour',
        // The permanent Achievements page always shows this "Time Champion"
        // variant; MARATHON_TOAST_VARIANTS below picks randomly among all
        // six variants for the popup notification instead.
        unlockedAsset: asset('marathon/02_time_champion.png'),
        lockedAsset: asset('marathon/02_time_champion_locked.png'),
        animation: 'hourglass',
        duration: 3,
    },

    'first-5-checklists': {
        id: 'first-5-checklists',
        name: 'Checklist Squad',
        description: 'Created 5 checklists',
        unlockedAsset: asset('first_5_checklists_unlocked.png'),
        lockedAsset: asset('first_5_checklists_locked.png'),
        animation: 'sparkles',
        duration: 3,
    },
    'first-5-test-cases': {
        id: 'first-5-test-cases',
        name: 'Case Builder',
        description: 'Created 5 test cases',
        unlockedAsset: asset('first_5_test_cases_unlocked.png'),
        lockedAsset: asset('first_5_test_cases_locked.png'),
        animation: 'grow',
        duration: 3,
    },
    'first-5-documents': {
        id: 'first-5-documents',
        name: 'Doc Squad',
        description: 'Created 5 documents',
        unlockedAsset: asset('first_5_documents_unlocked.png'),
        lockedAsset: asset('first_5_documents_locked.png'),
        animation: 'scan',
        duration: 3,
    },
    'first-5-notes': {
        id: 'first-5-notes',
        name: 'Note Squad',
        description: 'Created 5 notes',
        unlockedAsset: asset('first_5_notes_unlocked.png'),
        lockedAsset: asset('first_5_notes_locked.png'),
        animation: 'blink',
        duration: 3,
    },
    'good-work-day': {
        id: 'good-work-day',
        name: 'Good Work Day',
        description: 'Stayed active in CheckMate on a good work day',
        // The Achievements page always shows this default variant; the toast
        // notification (fired on every active day, not just once — see
        // GOOD_DAY_TOAST_VARIANTS below) picks randomly among all variants.
        unlockedAsset: asset('Good%20day/good_work_day_unlocked.png'),
        lockedAsset: asset('good_work_day_locked.png'),
        animation: 'pulse-glow',
        duration: 3,
    },

    // Legend depends on every achievement above, so it comes last.
    legend: {
        id: 'legend',
        name: 'Legend',
        description: 'Unlocked every other achievement',
        unlockedAsset: asset('15_legend_unlocked_80.png'),
        lockedAsset: asset('15_legend_locked_80.png'),
        animation: 'legend-shimmer',
        duration: 4,
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
    asset('marathon/01_time_sprinter.png'),
    asset('marathon/02_time_champion.png'),
    asset('marathon/03_day_night_grinder.png'),
    asset('marathon/04_steady_builder.png'),
    asset('marathon/05_dedication_heart.png'),
    asset('marathon/06_productivity_rocket.png'),
];

/**
 * All artwork variants for the "Good Work Day" toast, which fires every
 * active day (not just once — see AchievementService::checkGoodWorkDay()).
 * A random variant is picked each time; add new files to
 * `public/images/badges/Good day/` and list them here.
 */
export const GOOD_DAY_TOAST_VARIANTS: string[] = [
    asset('Good%20day/good_work_day_unlocked.png'),
    asset('Good%20day/good_work_day_goal_unlocked.png'),
    asset('Good%20day/good_work_day_learning_unlocked.png'),
    asset('Good%20day/good_work_day_productivity_unlocked.png'),
    asset('Good%20day/good_work_day_sunrise_unlocked.png'),
    asset('Good%20day/good_work_day_workspace_unlocked.png'),
];
