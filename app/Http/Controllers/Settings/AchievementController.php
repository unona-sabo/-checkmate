<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AchievementController extends Controller
{
    /**
     * All achievement keys, matching `resources/js/lib/achievement-badges.ts`.
     *
     * @var list<string>
     */
    private const KEYS = [
        'first-blood',
        'bug-hunter',
        'exterminator',
        'checklist-champion',
        'detail-oriented',
        'clickup-connector',
        'speed-demon',
        'team-player',
        'grafana-guru',
        'project-starter',
        'perfectionist',
        'night-owl',
        'early-bird',
        'streak-master',
        'legend',
        'marathon',
        'first-test-suite',
        'first-checklist',
        'first-document',
        'first-note',
        'first-test-run',
        'first-release',
        'first-ai-generation',
    ];

    public function show(Request $request): Response
    {
        $unlocked = $request->user()->achievements()
            ->pluck('unlocked_at', 'achievement_key');

        $achievements = collect(self::KEYS)->mapWithKeys(fn (string $key) => [
            $key => [
                'unlocked' => $unlocked->has($key),
                'unlocked_at' => $unlocked->get($key)?->toIso8601String(),
            ],
        ]);

        return Inertia::render('settings/Achievements', [
            'achievements' => $achievements,
        ]);
    }
}
