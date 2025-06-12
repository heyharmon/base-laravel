<?php

use App\Models\Channel;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;

it('returns aggregated videos from channels', function () {
    $feed = Feed::factory()->create();
    $channels = Channel::factory()->count(2)->create();
    $feed->channels()->attach($channels);

    Http::fake([
        'https://www.googleapis.com/youtube/v3/search*' => Http::response([
            'items' => [
                [
                    'id' => ['videoId' => 'video1'],
                    'snippet' => [
                        'title' => 'Video 1',
                        'description' => 'desc',
                        'publishedAt' => '2024-01-01T00:00:00Z',
                        'thumbnails' => ['medium' => ['url' => 'thumb1']],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->getJson("/api/feeds/{$feed->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'feed' => ['id', 'name'],
            'videos' => [[
                'videoId',
                'title',
                'description',
                'publishedAt',
                'thumbnail',
            ]],
        ]);
});
