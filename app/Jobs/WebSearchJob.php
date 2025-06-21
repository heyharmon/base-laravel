<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Chat;
use App\Services\FirecrawlService;

class WebSearchJob extends BaseAgentJob
{
    private string $query;

    public function __construct(Conversation $conversation, Chat $chat, string $query)
    {
        parent::__construct($conversation, $chat);
        $this->query = $query;
    }

    public function handle(FirecrawlService $firecrawl): void
    {
        $this->markJobStarted();

        try {
            $results = $firecrawl->search($this->query);

            $this->chat->update([
                'web_search_results' => $results,
            ]);

            $this->markJobCompleted([
                'query' => $this->query,
                'results_count' => count($results),
                'results' => array_map(function ($result) {
                    return [
                        'title' => $result['title'] ?? '',
                        'url' => $result['url'] ?? '',
                        'snippet' => $result['snippet'] ?? '',
                        'content_preview' => substr($result['content'] ?? '', 0, 500),
                    ];
                }, $results),
            ]);

            $this->continueConversation("Web search completed for '{$this->query}'. Found " . count($results) . ' results.');
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }
}
