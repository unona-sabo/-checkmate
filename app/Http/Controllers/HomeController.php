<?php

namespace App\Http\Controllers;

use App\Services\DashboardActivityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private readonly DashboardActivityService $dashboardActivityService) {}

    public function index(Request $request): Response
    {
        $workspace = $request->attributes->get('workspace');
        $user = $request->user();

        $activity = $workspace
            ? $this->dashboardActivityService->build($workspace, $user)
            : null;

        return Inertia::render('Dashboard', [
            'activity' => $activity,
        ]);
    }
}
