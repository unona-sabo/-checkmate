<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AchievementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function __construct(private AchievementService $achievements) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $this->syncTimezone($request, $user);
            $this->achievements->trackDailyActivity($user);
        }

        return $next($request);
    }

    /**
     * The frontend reports the browser's IANA timezone via a cookie (set in
     * resources/js/composables/useTimezone.ts, mirroring how the
     * "appearance" cookie works). Without this, streak/night-owl/early-bird
     * day and hour boundaries would be computed in the server's timezone
     * instead of the user's own.
     */
    private function syncTimezone(Request $request, User $user): void
    {
        $timezone = $request->cookie('timezone');

        if (! $timezone || $timezone === $user->timezone || strlen($timezone) > 64) {
            return;
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        $user->update(['timezone' => $timezone]);
    }
}
