<?php

namespace App\Http\Controllers;

use App\Services\YouTubeService;
use Illuminate\Http\Request;

class YouTubeController extends Controller
{
    public function subscriptions(YouTubeService $youtube)
    {
        $token = config('services.youtube.access_token');
        if (!$token) {
            return response()->json([], 200);
        }

        $channels = $youtube->fetchSubscriptions($token);
        return response()->json($channels);
    }
}
