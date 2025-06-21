<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirecrawlService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebSearchController extends Controller
{
    protected FirecrawlService $firecrawlService;

    public function __construct(FirecrawlService $firecrawlService)
    {
        $this->firecrawlService = $firecrawlService;
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:1|max:500',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        $query = $request->input('query');
        $limit = $request->input('limit', 10);

        $results = $this->firecrawlService->search($query, $limit);

        return response()->json([
            'success' => true,
            'data' => $results,
            'query' => $query,
            'count' => count($results)
        ]);
    }

    public function fetchPage(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url|max:2000'
        ]);

        $url = $request->input('url');
        $result = $this->firecrawlService->fetchPage($url);

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch webpage content',
                'url' => $url
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'url' => $url
        ]);
    }
}