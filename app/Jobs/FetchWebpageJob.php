<?php

namespace App\Jobs;

use App\Services\FirecrawlService;

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

            if (!$pageData) {
                throw new \Exception("Failed to fetch webpage: {$this->url}");
            }

            $this->chat->update([
                'web_search_results' => [$pageData],
            ]);

            $this->markJobCompleted([
                'url' => $this->url,
                'title' => $pageData['title'],
                'content_length' => strlen($pageData['content']),
            ]);

            $this->continueConversation(
                "Successfully fetched webpage '{$pageData['title']}' from {$this->url}"
            );
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }
}
