<?php

namespace App\Jobs;

use App\Models\Bugreport;
use App\Models\ClickupSetting;
use App\Services\ClickupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncBugreportFromClickUp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Bugreport $bugreport,
    ) {}

    public function handle(): void
    {
        if (! $this->bugreport->clickup_task_id) {
            return;
        }

        $workspace = $this->bugreport->project->workspace;
        $settings = $workspace ? ClickupSetting::forWorkspace($workspace) : null;

        if (! $settings?->isConfigured()) {
            return;
        }

        $service = ClickupService::fromSettings($settings);
        $task = $service->getTask($this->bugreport->clickup_task_id);

        $clickupStatus = strtolower($task['status']['status'] ?? '');
        $statusMapping = $settings->status_mapping ?? [];
        $reverseMapping = array_flip($statusMapping);
        $appStatus = $reverseMapping[$clickupStatus] ?? null;

        if ($appStatus && $appStatus !== $this->bugreport->status) {
            $this->bugreport->update(['status' => $appStatus]);
        }
    }
}
