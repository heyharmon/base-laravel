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
        $articles = $conversation->articles()
            ->with(['versions' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->get();

        return response()->json($articles);
    }

    public function show(Conversation $conversation, Article $article): JsonResponse
    {
        if ($article->conversation_id !== $conversation->id) {
            abort(404);
        }

        return response()->json([
            'article' => $article->load('versions'),
            'current_version' => $article->getCurrentVersion(),
        ]);
    }

    public function version(Conversation $conversation, Article $article, int $version): JsonResponse
    {
        if ($article->conversation_id !== $conversation->id) {
            abort(404);
        }

        $articleVersion = $article->versions()
            ->where('version_number', $version)
            ->firstOrFail();

        return response()->json($articleVersion);
    }

    public function export(Conversation $conversation, Article $article): JsonResponse
    {
        if ($article->conversation_id !== $conversation->id) {
            abort(404);
        }

        $currentVersion = $article->getCurrentVersion();

        return response()->json([
            'title' => $article->title,
            'content' => $currentVersion ? $currentVersion->content : '',
            'metadata' => [
                'created_at' => $article->created_at,
                'updated_at' => $article->updated_at,
                'version' => $article->current_version,
                'word_count' => $currentVersion ? str_word_count($currentVersion->content) : 0,
            ],
        ]);
    }
}
