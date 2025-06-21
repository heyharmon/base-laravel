<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\OpenAIService;
use App\Http\Resources\ChatResource;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index()
    {
        return Conversation::orderByDesc('created_at')->get();
    }

    public function store(Request $request)
    {
        $conversation = Conversation::create([
            'title' => $request->input('title'),
        ]);

        return response()->json($conversation, 201);
    }

    public function chats(Conversation $conversation)
    {
        $chats = $conversation->chats()->orderBy('created_at')->get();
        return ChatResource::collection($chats);
    }

    public function sendMessage(Request $request, Conversation $conversation, OpenAIService $openai)
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        $userChat = $conversation->chats()->create([
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $result = $openai->sendMessage($conversation, $data['message']);

        return response()->json([
            'user_chat' => new ChatResource($userChat),
            'assistant_chat' => new ChatResource($result['chat']),
            'needs_follow_up' => $result['needsFollowUp'],
        ]);
    }
}
