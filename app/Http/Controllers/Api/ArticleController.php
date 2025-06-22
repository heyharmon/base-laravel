<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    public function index(Conversation $conversation): JsonResponse
    {
        $articles = $conversation->articles()->get();

        return response()->json($articles);
    }

    public function show(Conversation $conversation, Article $article): JsonResponse
    {
        if ($article->conversation_id !== $conversation->id) {
            abort(404);
        }

        return response()->json($article);
    }

    public function update(Request $request, Conversation $conversation, Article $article): JsonResponse
    {
        if ($article->conversation_id !== $conversation->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'outline' => 'sometimes|array',
            'status' => 'sometimes|in:planning,researching,writing,reviewing,completed',
        ]);

        $article->update($validated);

        return response()->json([
            'message' => 'Article updated successfully',
            'article' => $article->fresh(),
        ]);
    }

    public function export(Conversation $conversation, Article $article): JsonResponse
    {
        if ($article->conversation_id !== $conversation->id) {
            abort(404);
        }

        return response()->json([
            'title' => $article->title,
            'content' => $article->content ?? '',
            'metadata' => [
                'created_at' => $article->created_at,
                'updated_at' => $article->updated_at,
                'word_count' => $article->getWordCount(),
                'status' => $article->status,
            ],
        ]);
    }
}
