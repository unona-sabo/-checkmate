<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayoutMonitor\ParseLogRequest;
use App\Models\GrafanaSetting;
use App\Models\Project;
use App\Services\GrafanaService;
use App\Services\PayoutLogParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutMonitorController extends Controller
{
    public function index(Project $project): Response
    {
        $settings = GrafanaSetting::current();

        return Inertia::render('PayoutMonitor/Index', [
            'project' => $project,
            'isConfigured' => $settings->isConfigured(),
            'logPath' => $settings->log_path,
        ]);
    }

    public function fetchLatest(Project $project, Request $request): JsonResponse
    {
        $settings = GrafanaSetting::current();

        if (! $settings->isConfigured()) {
            return response()->json(['error' => 'Grafana is not configured. Go to Settings > Grafana to set up your API token.'], 422);
        }

        $minutesBack = (int) $request->input('minutes_back', 60);
        $minutesBack = min(max($minutesBack, 5), 1440); // 5min to 24h

        $logPath = $request->input('log_path', $settings->log_path);
        if (empty($logPath)) {
            return response()->json(['error' => 'Log path is not configured. Go to Settings > Grafana to set the log file path.'], 422);
        }

        // Replace date placeholder
        $logPath = str_replace('{YYYY-MM-DD}', now()->format('Y-m-d'), $logPath);

        try {
            $service = GrafanaService::fromSettings();
            $lines = $service->fetchRecentLogs($logPath, 'terrapay', $minutesBack);

            $rawLog = implode("\n", $lines);
            $parser = new PayoutLogParser;

            return response()->json($parser->parse($rawLog));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch logs: '.$e->getMessage()], 422);
        }
    }

    public function parseLog(Project $project, ParseLogRequest $request): JsonResponse
    {
        $parser = new PayoutLogParser;

        return response()->json($parser->parse($request->validated('raw_log')));
    }
}
