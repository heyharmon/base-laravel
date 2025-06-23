<?php

namespace App\Tools\Jobs;

use App\Models\Article;
use App\Models\Conversation;
use App\Models\Chat;

class WriteArticleSectionJob extends ToolJob
{
    private int $articleId;
    private string $section;
    private string $content;

    public function __construct(
        Conversation $conversation,
        Chat $chat,
        int $articleId,
        string $section,
        string $content
    ) {
        parent::__construct($conversation, $chat);
        $this->articleId = $articleId;
        $this->section = $section;
        $this->content = $content;
    }

    public function handle(): void
    {
        $this->markJobStarted();

        try {
            $article = Article::findOrFail($this->articleId);
            $currentContent = $article->content ?? '';
            $updatedContent = $this->mergeSectionContent($currentContent, $this->section, $this->content);

            $article->updateContent($updatedContent);

            if ($article->status === 'researching') {
                $article->update(['status' => 'writing']);
            }

            $this->markJobCompleted([
                'article_id' => $this->articleId,
                'section' => $this->section,
                'word_count' => $article->getWordCount(),
            ]);

            $this->continueConversation(
                "Completed writing section '{$this->section}' for article '{$article->title}'."
            );
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }

    private function mergeSectionContent(string $current, string $section, string $newContent): string
    {
        if (empty($current)) {
            return "## {$section}\n\n{$newContent}";
        }

        $pattern = "/## {$section}.*?(?=## |$)/s";
        if (preg_match($pattern, $current)) {
            return preg_replace($pattern, "## {$section}\n\n{$newContent}\n\n", $current);
        }

        return $current . "\n\n## {$section}\n\n{$newContent}";
    }
}
