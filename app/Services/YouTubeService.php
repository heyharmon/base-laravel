<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class YouTubeService
{
    public function fetchLatestVideos(string $channelId, int $maxResults = 5)
    {
        $apiKey = config('services.youtube.api_key');

        $response = Http::get('https://www.googleapis.com/youtube/v3/search', [
            'key' => $apiKey,
            'channelId' => $channelId,
            'part' => 'snippet',
            'order' => 'date',
            'maxResults' => $maxResults,
        ])->json();

        return collect($response['items'] ?? [])->map(function ($item) {
            if (!isset($item['id']['videoId'])) {
                return null;
            }

            return [
                'videoId' => $item['id']['videoId'],
                'title' => $item['snippet']['title'] ?? '',
                'description' => $item['snippet']['description'] ?? '',
                'publishedAt' => $item['snippet']['publishedAt'] ?? null,
                'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? null,
            ];
        })->filter()->values();
    }
}

