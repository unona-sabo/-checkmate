<?php

namespace App\Observers;

use App\Models\Bugreport;
use App\Services\AchievementService;

class BugreportObserver
{
    public function __construct(private AchievementService $achievements) {}

    public function created(Bugreport $bugreport): void
    {
        if ($bugreport->reporter) {
            $this->achievements->checkBugAchievements($bugreport->reporter);
        }
    }

    public function updated(Bugreport $bugreport): void
    {
        if ($bugreport->wasChanged('status') && $bugreport->status === 'done' && ! $bugreport->resolved_at) {
            $bugreport->resolved_at = now();
            $bugreport->saveQuietly();
            $this->achievements->checkSpeedDemon($bugreport);
        }
    }
}
