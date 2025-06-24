<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\OpenAIService;
use Illuminate\Http\Request;

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
            'title' => $request->input('title', 'New Conversation'),
            'context' => $request->input('context', [])
        ]);

        return response()->json($conversation);
    }

    public function updateContext(Request $request, Conversation $conversation)
    {
        $conversation->update([
            'context' => $request->input('context')
        ]);

        return response()->json($conversation);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $chats = $this->openAIService->processMessage(
            $conversation,
            $request->input('message')
        );

        return response()->json([
            'conversation' => $conversation->fresh(),
            'chats' => $chats
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
