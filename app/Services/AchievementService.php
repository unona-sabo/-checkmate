<?php

namespace App\Services;

use App\Models\Bugreport;
use App\Models\Checklist;
use App\Models\Project;
use App\Models\TestCaseNote;
use App\Models\User;
use App\Models\UserAchievement;

class AchievementService
{
    /**
     * Human-readable names for the flash-message toast, keyed by achievement key.
     *
     * @var array<string, string>
     */
    private const NAMES = [
        'first-blood' => 'First Bug',
        'bug-hunter' => 'Bug Hunter',
        'exterminator' => 'Exterminator',
        'checklist-champion' => 'Checklist Champion',
        'detail-oriented' => 'Detail Oriented',
        'clickup-connector' => 'ClickUp Connector',
        'speed-demon' => 'Speed Demon',
        'team-player' => 'Team Player',
        'grafana-guru' => 'Grafana Guru',
        'project-starter' => 'Project Starter',
        'perfectionist' => 'Perfectionist',
        'night-owl' => 'Night Owl',
        'early-bird' => 'Early Bird',
        'streak-master' => 'Streak Master',
        'legend' => 'Legend',
        'marathon' => 'Marathon',
    ];

    /**
     * All achievement keys except "legend" itself.
     *
     * @var list<string>
     */
    private const NON_LEGEND_KEYS = [
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
        'marathon',
    ];

    public function unlock(User $user, string $key): void
    {
        $achievement = UserAchievement::firstOrCreate(
            ['user_id' => $user->id, 'achievement_key' => $key],
            ['unlocked_at' => now()],
        );

        if (! $achievement->wasRecentlyCreated) {
            return;
        }

        if (request()?->hasSession()) {
            $queue = session('achievement', []);
            $queue[] = ['key' => $key, 'name' => $this->name($key)];
            session()->flash('achievement', $queue);
        }

        if ($key !== 'legend') {
            $this->maybeUnlockLegend($user);
        }
    }

    private function name(string $key): string
    {
        return self::NAMES[$key] ?? $key;
    }

    public function maybeUnlockLegend(User $user): void
    {
        $unlockedCount = $user->achievements()
            ->whereIn('achievement_key', self::NON_LEGEND_KEYS)
            ->count();

        if ($unlockedCount === count(self::NON_LEGEND_KEYS)) {
            $this->unlock($user, 'legend');
        }
    }

    public function checkBugAchievements(User $user): void
    {
        $count = Bugreport::where('reported_by', $user->id)->count();

        if ($count >= 1) {
            $this->unlock($user, 'first-blood');
        }

        if ($count >= 10) {
            $this->unlock($user, 'bug-hunter');
        }

        if ($count >= 50) {
            $this->unlock($user, 'exterminator');
        }
    }

    public function checkSpeedDemon(Bugreport $bugreport): void
    {
        if (! $bugreport->resolved_at || ! $bugreport->reporter) {
            return;
        }

        if ($bugreport->created_at->diffInMinutes($bugreport->resolved_at) <= 60) {
            $this->unlock($bugreport->reporter, 'speed-demon');
        }
    }

    public function checkChecklistChampion(User $user): void
    {
        $count = Checklist::where('completed_by', $user->id)->count();

        if ($count >= 25) {
            $this->unlock($user, 'checklist-champion');
        }
    }

    public function checkDetailOriented(User $user): void
    {
        $count = TestCaseNote::where('updated_by', $user->id)
            ->whereRaw('LENGTH(content) >= 20')
            ->distinct('test_case_id')
            ->count('test_case_id');

        if ($count >= 20) {
            $this->unlock($user, 'detail-oriented');
        }
    }

    public function checkClickupConnector(User $user): void
    {
        $this->unlock($user, 'clickup-connector');
    }

    /**
     * Unlocked the first time the frontend reports an hour of continuous
     * activity in the app (see `resources/js/components/FocusSessionTracker.vue`).
     * Later hourly milestones in the same session are celebrated client-side
     * only, since the achievement itself only needs to be recorded once.
     */
    public function checkMarathon(User $user): void
    {
        $this->unlock($user, 'marathon');
    }

    public function checkGrafanaGuru(User $user): void
    {
        $this->unlock($user, 'grafana-guru');
    }

    public function checkProjectStarter(User $user): void
    {
        if (Project::where('user_id', $user->id)->count() === 1) {
            $this->unlock($user, 'project-starter');
        }
    }

    public function checkTeamPlayer(User $user): void
    {
        $count = Project::where('user_id', $user->id)
            ->orWhereHas('workspace.members', fn ($query) => $query->where('users.id', $user->id))
            ->count();

        if ($count >= 5) {
            $this->unlock($user, 'team-player');
        }
    }

    public function checkPerfectionist(User $user, Project $project): void
    {
        $analysis = $project->latestCoverageAnalysis;

        if ($analysis && (float) $analysis->overall_coverage === 100.0 && $project->releases()->exists()) {
            $this->unlock($user, 'perfectionist');
        }
    }

    public function trackDailyActivity(User $user): void
    {
        $today = now()->toDateString();

        if ($user->last_active_date?->toDateString() === $today) {
            return;
        }

        $previousDate = $user->last_active_date;
        $wasYesterday = $previousDate && $previousDate->toDateString() === now()->subDay()->toDateString();

        $user->current_streak_days = $wasYesterday ? $user->current_streak_days + 1 : 1;

        $hour = (int) now()->format('G');

        if ($hour >= 0 && $hour < 5) {
            $user->night_owl_days++;
        } elseif ($hour >= 5 && $hour < 7) {
            $user->early_bird_days++;
        }

        $user->last_active_date = $today;
        $user->save();

        if ($user->current_streak_days >= 7) {
            $this->unlock($user, 'streak-master');
        }

        if ($user->night_owl_days >= 10) {
            $this->unlock($user, 'night-owl');
        }

        if ($user->early_bird_days >= 10) {
            $this->unlock($user, 'early-bird');
        }
    }
}
