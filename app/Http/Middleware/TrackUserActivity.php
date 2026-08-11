<?php

namespace App\Http\Middleware;

use App\Services\AchievementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function __construct(private AchievementService $achievements) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->achievements->trackDailyActivity($request->user());
        }

        return $next($request);
    }
}
