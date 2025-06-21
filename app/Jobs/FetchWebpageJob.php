<?php

namespace App\Jobs;

use App\Services\FirecrawlService;
use App\Models\Conversation;
use App\Models\Chat;

class FetchWebpageJob extends BaseAgentJob
{
    private string $url;

    public function __construct(Conversation $conversation, Chat $chat, string $url)
    {
        parent::__construct($conversation, $chat);
        $this->url = $url;
    }

    public function handle(FirecrawlService $firecrawl): void
    {
        $this->markJobStarted();

        try {
            $pageData = $firecrawl->fetchPage($this->url);

            if ($pageData === null) {
                throw new \Exception("Failed to fetch webpage content from {$this->url}");
            }

            // Store the full content in web_search_results for the agent to see
            $this->chat->update([
                'web_search_results' => $pageData['content'],
            ]);

            $this->markJobCompleted([
                'url' => $this->url,
                'title' => $pageData['title'] ?? 'Unknown Title',
                'content_length' => strlen($pageData['content'] ?? ''),
                'content_preview' => substr($pageData['content'] ?? '', 0, 500),
            ]);

            $this->continueConversation(
                "Successfully fetched webpage content from {$this->url}. The page contains " . strlen($pageData['content'] ?? '') . " characters of markdown content."
            );
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }
}
