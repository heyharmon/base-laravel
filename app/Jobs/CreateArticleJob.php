<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Chat;
use App\Models\Article;

class CreateArticleJob extends BaseAgentJob
{
    private string $title;
    private array $outline;

    public function __construct(Conversation $conversation, Chat $chat, string $title, array $outline)
    {
        parent::__construct($conversation, $chat);
        $this->title = $title;
        $this->outline = $outline;
    }

    public function handle(): void
    {
        $this->markJobStarted();

        try {
            $article = $this->conversation->articles()->create([
                'title' => $this->title,
                'outline' => $this->outline,
                'content' => '', // Initialize with empty content
                'status' => 'planning',
            ]);

            $this->markJobCompleted([
                'article_id' => $article->id,
                'title' => $article->title,
                'outline_sections' => count($this->outline),
            ]);

            $this->continueConversation(
                "Created new article '{$this->title}' with " . count($this->outline) . ' sections.'
            );
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }
}
