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

            $article->update(['status' => 'reviewing']);

            $reviewAnalysis = [
                'word_count' => str_word_count($article->content),
                'sections_completed' => $this->countCompletedSections($article->content, $article->outline),
                'has_citations' => $this->checkForCitations($article->content),
                'coherence_check' => 'Pending AI review',
            ];

            $this->markJobCompleted([
                'article_id' => $this->articleId,
                'review_analysis' => $reviewAnalysis,
            ]);

            $this->continueConversation(
                "Article review completed. The article has {$reviewAnalysis['word_count']} words and {$reviewAnalysis['sections_completed']} completed sections."
            );
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }

    private function countCompletedSections(string $content, array $outline): int
    {
        $completed = 0;
        foreach ($outline as $section) {
            if (strpos($content, "## {$section}") !== false) {
                $completed++;
            }
        }
        return $completed;
    }

    private function checkForCitations(string $content): bool
    {
        return preg_match('/\[\d+\]|\([A-Za-z]+,?\s*\d{4}\)/', $content) > 0;
    }
}
