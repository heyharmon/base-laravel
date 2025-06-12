<?php

use Illuminate\Support\Facades\Http;

it('returns subscribed channels', function () {
    Http::fake([
        'https://www.googleapis.com/youtube/v3/subscriptions*' => Http::response([
            'items' => [
                [
                    'snippet' => [
                        'resourceId' => ['channelId' => 'chan1'],
                        'title' => 'Channel One',
                    ],
                ],
            ],
        ], 200),
    ]);

    config(['services.youtube.access_token' => 'test-token']);

    $response = $this->getJson('/api/youtube/subscriptions');

    $response->assertStatus(200)
        ->assertJson([
            ['youtube_channel_id' => 'chan1', 'name' => 'Channel One'],
        ]);
});
