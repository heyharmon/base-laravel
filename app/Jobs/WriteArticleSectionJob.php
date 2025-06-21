<?php

namespace App\Jobs;

use App\Models\Article;

class WriteArticleSectionJob extends BaseAgentJob
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
            $currentVersion = $article->getCurrentVersion();
            $currentContent = $currentVersion ? $currentVersion->content : '';
            $updatedContent = $this->mergeSectionContent($currentContent, $this->section, $this->content);
            $article->createNewVersion(
                $updatedContent,
                "Updated section: {$this->section}"
            );

            if ($article->status === 'researching') {
                $article->update(['status' => 'writing']);
            }

            $this->markJobCompleted([
                'article_id' => $this->articleId,
                'section' => $this->section,
                'version' => $article->current_version,
                'word_count' => str_word_count($this->content),
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
