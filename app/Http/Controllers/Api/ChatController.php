<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function index(Conversation $conversation): JsonResponse
    {
        $chats = $conversation->chats()
            ->orderBy('created_at')
            ->paginate(50);

        return response()->json($chats);
    }

    public function show(Conversation $conversation, Chat $chat): JsonResponse
    {
        if ($chat->conversation_id !== $conversation->id) {
            abort(404);
        }

        return response()->json($chat);
    }

    public function jobStatus(Conversation $conversation, Chat $chat): JsonResponse
    {
        if ($chat->conversation_id !== $conversation->id) {
            abort(404);
        }

        return response()->json([
            'job_id' => $chat->job_id,
            'status' => $chat->job_status,
            'is_running' => $chat->isJobRunning(),
        ]);
    }
}
