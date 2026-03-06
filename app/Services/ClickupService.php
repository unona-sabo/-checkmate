<?php

namespace App\Services;

use App\Models\ClickupSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ClickupService
{
    private PendingRequest $http;

    public function __construct(
        private string $apiToken,
    ) {
        $this->http = Http::baseUrl('https://api.clickup.com/api/v2')
            ->withHeaders(['Authorization' => $this->apiToken])
            ->acceptJson();
    }

    /**
     * Create a service instance from the stored settings.
     */
    public static function fromSettings(): self
    {
        $settings = ClickupSetting::current();

        return new self($settings->api_token ?? '');
    }

    /**
     * Create a task in the given ClickUp list.
     *
     * @param  array{name: string, description?: string, status?: string, priority?: int}  $data
     * @return array<string, mixed>
     */
    public function createTask(string $listId, array $data): array
    {
        $response = $this->http->post("/list/{$listId}/task", $data);

        $response->throw();

        return $response->json();
    }

    /**
     * Get a task by ID.
     *
     * @return array<string, mixed>
     */
    public function getTask(string $taskId): array
    {
        $response = $this->http->get("/task/{$taskId}");

        $response->throw();

        return $response->json();
    }

    /**
     * Attach a file to a ClickUp task.
     *
     * @return array<string, mixed>
     */
    public function attachToTask(string $taskId, string $filePath, string $fileName): array
    {
        $response = Http::baseUrl('https://api.clickup.com/api/v2')
            ->withHeaders(['Authorization' => $this->apiToken])
            ->attach('attachment', file_get_contents($filePath), $fileName)
            ->post("/task/{$taskId}/attachment");

        $response->throw();

        return $response->json();
    }

    /**
     * Get the statuses configured for a ClickUp list.
     *
     * @return array<int, array{status: string, color: string, type: string}>
     */
    public function getListStatuses(string $listId): array
    {
        $response = $this->http->get("/list/{$listId}");

        $response->throw();

        return $response->json('statuses', []);
    }

    /**
     * Get the authenticated user's teams (workspaces).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTeams(): array
    {
        $response = $this->http->get('/team');

        $response->throw();

        return $response->json('teams', []);
    }

    /**
     * Register a webhook for task status updates.
     *
     * @return array{id: string, webhook: array<string, mixed>}
     */
    public function registerWebhook(string $teamId, string $endpoint, string $secret): array
    {
        $response = $this->http->post("/team/{$teamId}/webhook", [
            'endpoint' => $endpoint,
            'events' => ['taskStatusUpdated'],
            'secret' => $secret,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Delete a webhook.
     */
    public function deleteWebhook(string $webhookId): void
    {
        $this->http->delete("/webhook/{$webhookId}")->throw();
    }
}
