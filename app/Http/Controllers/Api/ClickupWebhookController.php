<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bugreport;
use App\Models\ClickupSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClickupWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $settings = ClickupSetting::current();

        if (! $this->verifySignature($request, $settings->webhook_secret ?? '')) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');

        if ($event === 'taskStatusUpdated') {
            $this->handleStatusUpdate($request, $settings);
        }

        return response()->json(['ok' => true]);
    }

    private function verifySignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Signature');

        if (! $signature || ! $secret) {
            return false;
        }

        $computed = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }

    private function handleStatusUpdate(Request $request, ClickupSetting $settings): void
    {
        $taskId = $request->input('task_id');
        $newStatus = $request->input('history_items.0.after.status');

        if (! $taskId || ! $newStatus) {
            return;
        }

        $bugreport = Bugreport::where('clickup_task_id', $taskId)->first();

        if (! $bugreport) {
            return;
        }

        $statusMapping = $settings->status_mapping ?? [];
        $reverseMapping = array_flip($statusMapping);
        $appStatus = $reverseMapping[strtolower($newStatus)] ?? null;

        if ($appStatus) {
            $bugreport->update(['status' => $appStatus]);
        }
    }
}
