/**
 * Playful QA-themed "fortunes" shown in the Dashboard's Good Day banner.
 * One category and one phrase from it are picked at random on every
 * Dashboard load — purely decorative, no backend/API involved.
 */
export interface QaPredictionCategory {
    key: string;
    label: string;
    emoji: string;
    phrases: string[];
}

export const QA_PREDICTION_CATEGORIES: QaPredictionCategory[] = [
    {
        key: 'fortune',
        label: 'QA Fortune',
        emoji: '🔮',
        phrases: [
            'A mysterious bug is waiting for you. Trust your instincts. 🔍',
            'Your next test run will reveal something interesting. ✨',
            'A flaky test may finally decide to behave today. 🤞',
            'You are one test away from discovering something important. 🚀',
            'Your test coverage is about to get a little smarter. ✨',
        ],
    },
    {
        key: 'bug',
        label: 'Bug of the Day',
        emoji: '🐞',
        phrases: [
            'Today you will find the bug everyone else missed. 🐞',
            'The bug you are looking for is hiding in plain sight. 👀',
            'Your next bug report will be a masterpiece. 🐞',
            'Something suspicious is hiding behind that green status. 👀',
            'A tiny edge case is waiting for its moment. 🎯',
        ],
    },
    {
        key: 'tip',
        label: "Today's QA Tip",
        emoji: '🎯',
        phrases: [
            'One carefully chosen test case will save you hours today. 🎯',
            'Today your attention to detail will pay off. 🕵️',
            'The logs know the truth. Go check them. 🧪',
            'Today is a good day to challenge assumptions. 🔬',
            'Trust the test. Then test the test. 🧪',
        ],
    },
    {
        key: 'energy',
        label: 'QA Energy',
        emoji: '⚡',
        phrases: [
            'Today is a perfect day to break something — professionally. 😎',
            'Your regression suite is stronger than you think. 💪',
            "Today, 'works on my machine' will not survive your testing. 😄",
            'Today you have excellent bug-finding energy. ⚡',
            'One click can reveal more than a thousand assumptions. 🖱️',
        ],
    },
];
