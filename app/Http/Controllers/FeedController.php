<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Channel;
use App\Services\YouTubeService;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function index()
    {
        return Feed::with('channels')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'channels' => 'array',
            'channels.*.name' => 'required|string',
            'channels.*.youtube_channel_id' => 'required|string',
        ]);

        $feed = Feed::create(['name' => $data['name']]);

        if (!empty($data['channels'])) {
            $ids = collect($data['channels'])->map(function ($channel) {
                return Channel::firstOrCreate(
                    ['youtube_channel_id' => $channel['youtube_channel_id']],
                    ['name' => $channel['name']]
                )->id;
            });
            $feed->channels()->sync($ids);
        }

        return response()->json($feed->load('channels'), 201);
    }

    public function update(Request $request, Feed $feed)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string',
            'channels' => 'array',
            'channels.*.name' => 'required_with:channels|string',
            'channels.*.youtube_channel_id' => 'required_with:channels|string',
        ]);

        if (isset($data['name'])) {
            $feed->update(['name' => $data['name']]);
        }

        if (array_key_exists('channels', $data)) {
            $ids = collect($data['channels'])->map(function ($channel) {
                return Channel::firstOrCreate(
                    ['youtube_channel_id' => $channel['youtube_channel_id']],
                    ['name' => $channel['name']]
                )->id;
            });
            $feed->channels()->sync($ids);
        }

        return response()->json($feed->load('channels'));
    }

    public function destroy(Feed $feed)
    {
        $feed->delete();

        return response()->noContent();
    }
    public function show(Feed $feed, YouTubeService $youtube)
    {
        $videos = $feed->channels->flatMap(function ($channel) use ($youtube) {
            return $youtube->fetchLatestVideos($channel->youtube_channel_id);
        })->sortByDesc('publishedAt')->values();

        return response()->json([
            'feed' => $feed,
            'videos' => $videos,
        ]);
    }
}
