<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OpenAIService;
use App\Models\Conversation;

class ChatController extends Controller
{
    protected $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    public function createConversation(Request $request)
    {
        $conversation = Conversation::create([
            'title' => $request->input('title', 'New Conversation')
        ]);

        return response()->json($conversation);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        $request->validate([
            'message' => 'required|string',
            'context' => 'sometimes|array'
        ]);

        // Store user message immediately
        $conversation->chats()->create([
            'type' => 'user',
            'content' => $request->input('message')
        ]);

        // Process message asynchronously with context
        $this->openAIService->processMessageAsync(
            $conversation,
            $request->input('message'),
            $request->input('context', [])
        );

        return response()->json([
            'conversation' => $conversation->fresh(),
            'chats' => $conversation->chats
        ]);
    }

    public function getConversation(Conversation $conversation)
    {
        return response()->json([
            'conversation' => $conversation,
            'chats' => $conversation->chats
        ]);
    }

    public function listConversations()
    {
        $conversations = Conversation::orderBy('updated_at', 'desc')->get();
        return response()->json($conversations);
    }
}
