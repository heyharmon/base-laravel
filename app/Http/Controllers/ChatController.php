<?php

namespace App\Http\Controllers;

use App\Jobs\ChatResponseJob;
use App\Models\Chat;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        return Chat::orderBy('created_at')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        Chat::create([
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $reply = dispatch_sync(new ChatResponseJob($data['message']));

        return ['reply' => $reply];
    }
}
