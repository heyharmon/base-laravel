<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    public function index(): JsonResponse
    {
        $articles = Article::all();

        return response()->json($articles);
    }

    public function show(Article $article): JsonResponse
    {
        return response()->json($article);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'outline' => 'nullable|array',
            'status' => 'sometimes|in:planning,researching,writing,reviewing,completed',
        ]);

        $article = Article::create($validated);

        return response()->json([
            'message' => 'Article created successfully',
            'article' => $article,
        ], 201);
    }

    public function update(Request $request, Article $article): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'outline' => 'nullable|array',
            'status' => 'sometimes|in:planning,researching,writing,reviewing,completed',
        ]);

        $article->update($validated);

        return response()->json([
            'message' => 'Article updated successfully',
            'article' => $article->fresh(),
        ]);
    }

    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return response()->json([
            'message' => 'Article deleted successfully',
        ]);
    }

    public function export(Article $article): JsonResponse
    {
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
