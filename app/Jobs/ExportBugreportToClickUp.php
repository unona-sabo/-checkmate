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
use Illuminate\Support\Facades\Storage;

class ExportBugreportToClickUp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Bugreport $bugreport,
    ) {}

    public function handle(): void
    {
        if ($this->bugreport->clickup_task_id) {
            return;
        }

        $settings = ClickupSetting::current();

        if (! $settings->isConfigured()) {
            return;
        }

        $service = ClickupService::fromSettings();

        $statusMapping = $settings->status_mapping ?? [];
        $clickupStatus = $statusMapping[$this->bugreport->status] ?? null;

        $payload = [
            'name' => $this->bugreport->title,
            'description' => $this->buildDescription(),
        ];

        if ($clickupStatus) {
            $payload['status'] = $clickupStatus;
        }

        $priorityMap = ['high' => 2, 'medium' => 3, 'low' => 4];
        if (isset($priorityMap[$this->bugreport->priority])) {
            $payload['priority'] = $priorityMap[$this->bugreport->priority];
        }

        $result = $service->createTask($settings->list_id, $payload);
        $taskId = $result['id'];

        $this->bugreport->update(['clickup_task_id' => $taskId]);

        $this->uploadAttachments($service, $taskId);
    }

    private function uploadAttachments(ClickupService $service, string $taskId): void
    {
        $this->bugreport->loadMissing('attachments');

        foreach ($this->bugreport->attachments as $attachment) {
            if (! Storage::disk('public')->exists($attachment->stored_path)) {
                continue;
            }

            $fullPath = Storage::disk('public')->path($attachment->stored_path);
            $service->attachToTask($taskId, $fullPath, $attachment->original_filename);
        }
    }

    private function buildDescription(): string
    {
        $parts = [];

        if ($this->bugreport->description) {
            $parts[] = $this->bugreport->description;
        }

        if ($this->bugreport->steps_to_reproduce) {
            $parts[] = "**Steps to Reproduce:**\n".$this->bugreport->steps_to_reproduce;
        }

        if ($this->bugreport->expected_result) {
            $parts[] = "**Expected Result:**\n".$this->bugreport->expected_result;
        }

        if ($this->bugreport->actual_result) {
            $parts[] = "**Actual Result:**\n".$this->bugreport->actual_result;
        }

        if ($this->bugreport->environment) {
            $parts[] = '**Environment:** '.$this->bugreport->environment;
        }

        $parts[] = '**Severity:** '.$this->bugreport->severity;
        $parts[] = '**Priority:** '.$this->bugreport->priority;

        return implode("\n\n", $parts);
    }
}
