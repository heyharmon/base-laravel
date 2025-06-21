<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\Chat;
use App\Models\Conversation;

class ReviewArticleJob extends BaseAgentJob
{
    private int $articleId;

    public function __construct(Conversation $conversation, Chat $chat, int $articleId)
    {
        parent::__construct($conversation, $chat);
        $this->articleId = $articleId;
    }

    public function handle(): void
    {
        $this->markJobStarted();

        try {
            $article = Article::findOrFail($this->articleId);
            $this->markJobCompleted(['article_id' => $article->id, 'status' => $article->status]);
            $this->continueConversation("Review completed for article '{$article->title}'.");
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }
}
