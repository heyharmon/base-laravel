<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Services\YouTubeService;
use Illuminate\Http\Request;

class FeedController extends Controller
{
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
