<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    /**
     * Display a listing of the articles.
     */
    public function index(): JsonResponse
    {
        $articles = Article::select('id', 'title', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($articles);
    }

    /**
     * Store a newly created article.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string'
        ]);

        $article = Article::create($validated);

        return response()->json($article, 201);
    }

    /**
     * Display the specified article.
     */
    public function show(Article $article): JsonResponse
    {
        return response()->json($article);
    }

    /**
     * Update the specified article.
     */
    public function update(Request $request, Article $article): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string'
        ]);

        $article->update($validated);

        return response()->json($article);
    }

    /**
     * Remove the specified article.
     */
    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return response()->json($article);
    }
}
