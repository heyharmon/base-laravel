<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirecrawlService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.firecrawl.api_key');
        $this->baseUrl = config('services.firecrawl.base_url', 'https://api.firecrawl.dev/v1');
    }

    public function search(string $query, int $limit = 10): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/search', [
                'query' => $query,
                'limit' => $limit,
                // 'scrapeOptions' => [
                //     'formats' => ['markdown', 'html']
                // ]
            ]);

            if ($response->successful()) {
                $data = $response->json()['data'] ?? [];
                return $data;
            }

            Log::error('Firecrawl search failed', [
                'query' => $query,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Firecrawl search error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function fetchPage(string $url): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/scrape', [
                'url' => $url,
                'formats' => [
                    'markdown',
                    // 'html'
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json()['data'] ?? null;

                return [
                    'url' => $url,
                    'title' => $data['metadata']['title'] ?? '',
                    'description' => $data['metadata']['description'] ?? '',
                    'content' => $data['markdown'] ?? $data['content'] ?? '',
                    'html' => $data['html'] ?? '',
                    'metadata' => $data['metadata'] ?? [],
                ];
            }

            Log::error('Firecrawl fetch failed', [
                'url' => $url,
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Firecrawl fetch error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
