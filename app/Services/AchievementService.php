<?php

namespace App\Services;

use App\Models\Bugreport;
use App\Models\Checklist;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestCaseNote;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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
        'first-test-suite' => 'Suite Starter',
        'first-checklist' => 'Checklist Creator',
        'first-document' => 'Documentarian',
        'first-note' => 'Note Taker',
        'first-test-run' => 'Test Runner',
        'first-release' => 'Release Manager',
        'first-ai-generation' => 'AI Pioneer',
        'first-5-checklists' => 'Checklist Squad',
        'first-5-test-cases' => 'Case Builder',
        'completed-1-test-run' => 'Run Closer',
        'first-design' => 'Design Debut',
        'first-test-data' => 'Data Ready',
        'first-5-documents' => 'Doc Squad',
        'first-5-notes' => 'Note Squad',
        'good-work-day' => 'Good Work Day',
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
        'first-test-suite',
        'first-checklist',
        'first-document',
        'first-note',
        'first-test-run',
        'first-release',
        'first-ai-generation',
        'first-5-checklists',
        'first-5-test-cases',
        'completed-1-test-run',
        'first-design',
        'first-test-data',
        'first-5-documents',
        'first-5-notes',
        'good-work-day',
    ];

    public function unlock(User $user, string $key): void
    {
        // firstOrCreate() is select-then-insert, not atomic — two concurrent
        // requests unlocking the same achievement can both pass the select
        // and race on the insert, with the loser hitting the table's unique
        // (user_id, achievement_key) constraint. Treat that as "someone else
        // just unlocked it" instead of letting it surface as a 500.
        try {
            $achievement = UserAchievement::firstOrCreate(
                ['user_id' => $user->id, 'achievement_key' => $key],
                ['unlocked_at' => now()],
            );
        } catch (UniqueConstraintViolationException) {
            return;
        }

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
        // Streak/night-owl/early-bird boundaries are meaningless in server
        // time — a user's "today" and "3am" only make sense in their own
        // timezone, reported via the cookie TrackUserActivity syncs.
        $timezone = $user->timezone ?: config('app.timezone');
        $today = now($timezone)->toDateString();

        if ($user->last_active_date?->toDateString() === $today) {
            return;
        }

        DB::transaction(function () use ($user, $today, $timezone) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if ($locked->last_active_date?->toDateString() !== $today) {
                $previousDate = $locked->last_active_date;
                $wasYesterday = $previousDate && $previousDate->toDateString() === now($timezone)->subDay()->toDateString();

                $locked->current_streak_days = $wasYesterday ? $locked->current_streak_days + 1 : 1;

                $hour = (int) now($timezone)->format('G');

                if ($hour >= 0 && $hour < 5) {
                    $locked->night_owl_days++;
                } elseif ($hour >= 5 && $hour < 7) {
                    $locked->early_bird_days++;
                }

                $locked->last_active_date = $today;
                $locked->save();
            }

            $user->current_streak_days = $locked->current_streak_days;
            $user->night_owl_days = $locked->night_owl_days;
            $user->early_bird_days = $locked->early_bird_days;
            $user->last_active_date = $locked->last_active_date;
        });

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

    /**
     * Unlocked the first time this fires (a permanent badge on the
     * Achievements page), but the flash notification itself repeats on
     * every login — see the `Illuminate\Auth\Events\Login` listener
     * registered in `AppServiceProvider::boot()`, which calls this on every
     * successful login (fresh credentials or a remember-me cookie), not just
     * once per calendar day. The frontend picks a random image from the
     * "Good day" folder for each toast (see GOOD_DAY_TOAST_VARIANTS).
     */
    public function checkGoodWorkDay(User $user): void
    {
        UserAchievement::firstOrCreate(
            ['user_id' => $user->id, 'achievement_key' => 'good-work-day'],
            ['unlocked_at' => now()],
        );

        if (request()?->hasSession()) {
            $queue = session('achievement', []);
            $queue[] = ['key' => 'good-work-day', 'name' => $this->name('good-work-day')];
            session()->flash('achievement', $queue);
        }

        $this->maybeUnlockLegend($user);
    }

    /**
     * The following "first X" checks are unconditional — `unlock()` is
     * idempotent, so calling it on every creation only actually unlocks
     * and notifies the first time for each user.
     */
    public function checkFirstTestSuite(User $user): void
    {
        $this->unlock($user, 'first-test-suite');
    }

    public function checkFirstChecklist(User $user): void
    {
        $this->unlock($user, 'first-checklist');
    }

    public function checkFirstDocument(User $user): void
    {
        $this->unlock($user, 'first-document');
    }

    public function checkFirstNote(User $user): void
    {
        $this->unlock($user, 'first-note');
    }

    public function checkFirstTestRun(User $user): void
    {
        $this->unlock($user, 'first-test-run');
    }

    public function checkFirstRelease(User $user): void
    {
        $this->unlock($user, 'first-release');
    }

    public function checkFirstAiGeneration(User $user): void
    {
        $this->unlock($user, 'first-ai-generation');
    }

    public function checkFirstFiveChecklists(User $user): void
    {
        $user->increment('checklists_created_count');

        if ($user->checklists_created_count >= 5) {
            $this->unlock($user, 'first-5-checklists');
        }
    }

    public function checkFirstFiveTestCases(User $user): void
    {
        if (TestCase::where('created_by', $user->id)->count() >= 5) {
            $this->unlock($user, 'first-5-test-cases');
        }
    }

    public function checkFirstCompletedTestRun(User $user): void
    {
        $this->unlock($user, 'completed-1-test-run');
    }

    public function checkFirstDesign(User $user): void
    {
        $this->unlock($user, 'first-design');
    }

    public function checkFirstTestData(User $user): void
    {
        $this->unlock($user, 'first-test-data');
    }

    public function checkFirstFiveDocuments(User $user): void
    {
        $user->increment('documents_created_count');

        if ($user->documents_created_count >= 5) {
            $this->unlock($user, 'first-5-documents');
        }
    }

    public function checkFirstFiveNotes(User $user): void
    {
        $user->increment('notes_created_count');

        if ($user->notes_created_count >= 5) {
            $this->unlock($user, 'first-5-notes');
        }
    }
}
