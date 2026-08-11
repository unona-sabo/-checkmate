<?php

namespace App\Http\Controllers;

use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FocusSessionController extends Controller
{
    /**
     * Record that the user has been continuously active in the app for
     * over an hour (client-tracked, see FocusSessionTracker.vue). Called
     * once per session, the first time the hour threshold is crossed.
     */
    public function ping(Request $request, AchievementService $achievements): JsonResponse
    {
        $achievements->checkMarathon($request->user());

        return response()->json(['ok' => true]);
    }
}
