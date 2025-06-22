<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    private OpenAIService $openai;

    public function __construct(OpenAIService $openai)
    {
        $this->openai = $openai;
    }

    public function index(): JsonResponse
    {
        $conversations = Conversation::with(['articles', 'chats' => function ($query) {
            $query->latest()->limit(1);
        }])
            ->latest()
            ->paginate(20);

        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'initial_message' => 'required|string',
        ]);

        $conversation = Conversation::create([
            'title' => $validated['title'] ?? 'New Conversation',
        ]);

        $conversation->chats()->create([
            'role' => 'user',
            'content' => $validated['initial_message'],
        ]);

        $this->openai->sendMessage($conversation, $validated['initial_message']);

        return response()->json([
            'conversation' => $conversation->load('chats', 'articles'),
        ], 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        return response()->json([
            'conversation' => $conversation->load([
                'chats' => function ($query) {
                    $query->orderBy('created_at');
                },
                'articles.versions',
            ]),
            'active_jobs' => $conversation->getActiveJobs(),
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $conversation->chats()->create([
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $this->openai->sendMessage($conversation, $validated['message']);

        return response()->json([
            'success' => true,
            'conversation' => $conversation->load('chats'),
        ]);
    }

    public function stats(Conversation $conversation): JsonResponse
    {
        return response()->json([
            'total_tokens' => $conversation->total_tokens_used,
            'total_cost' => $conversation->total_cost,
            'chat_count' => $conversation->chats()->count(),
            'article_count' => $conversation->articles()->count(),
            'active_jobs' => $conversation->getActiveJobs()->count(),
            'plan' => $conversation->agent_plan,
        ]);
    }
}
