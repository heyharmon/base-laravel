<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Conversation;
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
            $page = $firecrawl->fetchPage($this->url);
            $this->markJobCompleted(['url' => $this->url, 'page' => $page]);
            $this->continueConversation("Fetched webpage {$this->url}.");
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }
}
