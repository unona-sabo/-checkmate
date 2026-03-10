<?php

namespace App\Services;

use App\Models\GrafanaSetting;
use Illuminate\Support\Facades\Http;

class GrafanaService
{
    public function __construct(
        private string $baseUrl,
        private string $apiToken,
        private string $datasourceId,
    ) {}

    public static function fromSettings(): self
    {
        $settings = GrafanaSetting::current();

        return new self(
            baseUrl: rtrim($settings->base_url ?? '', '/'),
            apiToken: $settings->api_token ?? '',
            datasourceId: $settings->datasource_id ?? '',
        );
    }

    /**
     * Query Loki via Grafana proxy for log lines.
     *
     * @return list<string>
     */
    public function queryLoki(string $logQuery, int $startNano, int $endNano, int $limit = 5000): array
    {
        $proxyPath = is_numeric($this->datasourceId)
            ? "api/datasources/proxy/{$this->datasourceId}"
            : "api/datasources/uid/{$this->datasourceId}/resources";

        $url = "{$this->baseUrl}/{$proxyPath}/loki/api/v1/query_range";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
        ])->timeout(30)->get($url, [
            'query' => $logQuery,
            'start' => $startNano,
            'end' => $endNano,
            'limit' => $limit,
            'direction' => 'forward',
        ]);

        $response->throw();

        $data = $response->json('data.result', []);

        $lines = [];
        foreach ($data as $stream) {
            foreach ($stream['values'] ?? [] as $value) {
                $lines[] = $value[1] ?? '';
            }
        }

        return $lines;
    }

    /**
     * Fetch recent terrapay logs from Loki.
     *
     * @return list<string>
     */
    public function fetchRecentLogs(string $logPath, string $keyword = 'terrapay', int $minutesBack = 60): array
    {
        $end = (int) (microtime(true) * 1e9);
        $start = $end - ($minutesBack * 60 * (int) 1e9);

        $query = sprintf('{filename="%s"} |= "%s"', $logPath, $keyword);

        return $this->queryLoki($query, $start, $end);
    }
}
