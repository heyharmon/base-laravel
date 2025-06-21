<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\Chat;
use App\Models\Conversation;

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
                'status' => 'researching',
            ]);

            $article->versions()->create([
                'version_number' => 1,
                'content' => '',
                'metadata' => ['created_at' => now()],
            ]);

            $this->markJobCompleted(['article_id' => $article->id]);
            $this->continueConversation("Created article '{$this->title}'.");
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }
}
