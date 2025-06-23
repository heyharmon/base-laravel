<?php

namespace App\Tools\Jobs;

use App\Models\Article;
use App\Services\OpenAIService;
use App\Models\Conversation;
use App\Models\Chat;

class ReviewArticleJob extends ToolJob
{
    private int $articleId;

    public function __construct(Conversation $conversation, Chat $chat, int $articleId)
    {
        parent::__construct($conversation, $chat);
        $this->articleId = $articleId;
    }

    public function handle(OpenAIService $openai): void
    {
        $this->markJobStarted();

        try {
            $article = Article::findOrFail($this->articleId);

            $articleData = [
                'id' => $article->id,
                'title' => $article->title,
                'content' => $article->content,
                'outline' => $article->outline,
                'status' => $article->status,
                'created_at' => $article->created_at,
                'updated_at' => $article->updated_at,
            ];

            $this->markJobCompleted([
                'article' => $articleData,
            ]);

            $this->continueConversation(
                "Successfully viewed article of id {$article->id}: Title '{$article->title}'. Full article content available in last system message."
            );
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }
}
