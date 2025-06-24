<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Chat;
use App\Models\Article;

class ConversationContextManager
{
    private Conversation $conversation;
    private array $workspace = [];
    private array $functionResults = [];
    private ?Article $activeArticle = null;

    public function __construct(Conversation $conversation)
    {
        $this->conversation = $conversation;
        $this->loadWorkspace();
    }

    private function loadWorkspace(): void
    {
        $this->loadRecentFunctionResults();
        $this->loadActiveArticle();
        $this->workspace['plan'] = $this->conversation->agent_plan ?? [];
    }

    private function loadRecentFunctionResults(): void
    {
        $recentChats = $this->conversation->chats()
            ->whereNotNull('function_response')
            ->where('job_status', 'completed')
            ->latest()
            ->limit(5)
            ->get();

        foreach ($recentChats as $chat) {
            $this->addFunctionResult(
                $chat->function_name,
                $chat->function_arguments,
                $chat->function_response,
                $chat->web_search_results
            );
        }
    }

    public function addFunctionResult(string $functionName, array $arguments, array $response, $additionalData = null): void
    {
        $this->functionResults[] = [
            'function' => $functionName,
            'arguments' => $arguments,
            'response' => $response,
            'additional_data' => $additionalData,
            'timestamp' => now(),
        ];

        if (count($this->functionResults) > 10) {
            array_shift($this->functionResults);
        }

        if (in_array($functionName, ['create_article', 'write_article_section']) && isset($response['article_id'])) {
            $this->setActiveArticle($response['article_id']);
        }
    }

    public function setActiveArticle(int $articleId): void
    {
        $this->activeArticle = Article::find($articleId);
        $this->workspace['active_article_id'] = $articleId;
    }

    private function loadActiveArticle(): void
    {
        if (isset($this->workspace['active_article_id'])) {
            $this->activeArticle = Article::find($this->workspace['active_article_id']);
        }
    }

    public function getActiveArticleContext(): array
    {
        if (!$this->activeArticle) {
            return [];
        }

        return [
            'article_id' => $this->activeArticle->id,
            'article_title' => $this->activeArticle->title,
            'article_status' => $this->activeArticle->status,
            'word_count' => $this->activeArticle->getWordCount(),
            'outline' => $this->activeArticle->outline,
        ];
    }

    public function updatePlan(array $plan): void
    {
        $this->workspace['plan'] = $plan;
        $this->conversation->updatePlan($plan);
    }

    public function buildSystemPrompt(): string
    {
        $sections = [];

        $sections[] = $this->getCorePrompt();
        $sections[] = $this->getPlanSection();

        if ($this->activeArticle) {
            $sections[] = $this->getActiveArticleSection();
        }

        $sections[] = $this->getFunctionResultsSection();
        $sections[] = $this->getGuidelinesSection();

        return implode("\n\n", array_filter($sections));
    }

    private function getCorePrompt(): string
    {
        return "You are an advanced research and writing agent. Your primary goal is to help users create comprehensive, well-researched articles.";
    }

    private function getPlanSection(): string
    {
        $plan = $this->workspace['plan'] ?? [];
        $planJson = empty($plan) ? 'No plan yet' : json_encode($plan, JSON_PRETTY_PRINT);
        return "Current conversation plan:\n{$planJson}";
    }

    private function getActiveArticleSection(): string
    {
        $context = $this->getActiveArticleContext();
        if (empty($context)) {
            return '';
        }

        $section = "Current Article Context:";
        $section .= "\n- Article ID: {$context['article_id']} (IMPORTANT: Use this ID for all article operations)";
        $section .= "\n- Title: {$context['article_title']}";
        $section .= "\n- Status: {$context['article_status']}";
        $section .= "\n- Word Count: {$context['word_count']}";
        $section .= "\n- You are currently helping edit this specific article";
        $section .= "\n- When using write_article_section or view_article functions, ALWAYS use article_id: {$context['article_id']}";

        return $section;
    }

    private function getFunctionResultsSection(): string
    {
        if (empty($this->functionResults)) {
            return '';
        }

        $section = "Recent function call results:";

        foreach (array_slice($this->functionResults, -5) as $result) {
            $section .= $this->formatFunctionResult($result);
        }

        return $section;
    }

    private function formatFunctionResult(array $result): string
    {
        $output = "\n- ";

        switch ($result['function']) {
            case 'web_search':
                $query = $result['arguments']['query'] ?? 'unknown';
                $output .= "Web search for '{$query}' returned:";
                if ($result['additional_data'] && is_array($result['additional_data'])) {
                    foreach (array_slice($result['additional_data'], 0, 3) as $searchResult) {
                        $title = $searchResult['title'] ?? 'Unknown';
                        $url = $searchResult['url'] ?? '';
                        $output .= "\n  * {$title} ({$url})";
                        if (!empty($searchResult['content'])) {
                            $preview = substr($searchResult['content'], 0, 200);
                            $output .= "\n    Preview: {$preview}...";
                        }
                    }
                }
                break;

            case 'create_article':
                $title = $result['response']['title'] ?? 'Unknown';
                $articleId = $result['response']['article_id'] ?? 'Unknown';
                $output .= "Created article '{$title}' with ID {$articleId}";
                $output .= "\n  Use this article ID ({$articleId}) for writing sections";
                break;

            case 'write_article_section':
                $sectionName = $result['response']['section'] ?? 'Unknown';
                $articleId = $result['response']['article_id'] ?? 'Unknown';
                $wordCount = $result['response']['word_count'] ?? 0;
                $output .= "Updated article section '{$sectionName}' for article ID {$articleId}";
                $output .= "\n  Total word count: {$wordCount}";
                break;

            case 'fetch_webpage':
                $url = $result['arguments']['url'] ?? 'Unknown';
                $length = $result['response']['content_length'] ?? 0;
                $output .= "Fetched webpage content from {$url}";
                $output .= "\n  Content length: {$length} characters";
                if ($result['additional_data']) {
                    $preview = substr($result['additional_data'], 0, 300);
                    $output .= "\n  Content preview: {$preview}...";
                }
                break;

            case 'view_article':
                $article = $result['response']['article'] ?? [];
                $title = $article['title'] ?? 'Unknown';
                $id = $article['id'] ?? 'Unknown';
                $output .= "Viewed article ID {$id}: '{$title}'";
                if (!empty($article['content'])) {
                    $preview = substr($article['content'], 0, 200);
                    $output .= "\n  Content preview: {$preview}...";
                }
                break;

            default:
                $output .= "Executed {$result['function']}";
                break;
        }

        return $output;
    }

    private function getGuidelinesSection(): string
    {
        return "Your capabilities:
1. Create and update research plans
2. Conduct web searches and fetch web pages  
3. Write and revise articles in sections
4. Manage multiple research tasks simultaneously
5. Self-evaluate your work and iterate

Guidelines:
- Always maintain and update your plan as you work
- Break down article writing into manageable sections
- Conduct thorough research before writing each section
- Include accurate citations in your articles
- Regularly review and improve your work
- Be transparent about your reasoning and progress
- Use the recent function call results shown above when they are available";
    }

    public function buildConversationHistory(int $limit = 10): array
    {
        return $this->conversation->chats()
            ->whereIn('role', ['user', 'assistant'])
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(function ($chat) {
                return [
                    'role' => $chat->role,
                    'content' => $chat->content ?: ' '
                ];
            })
            ->toArray();
    }

    public function getAdditionalContext(): array
    {
        $context = [];

        if ($this->activeArticle) {
            $context = array_merge($context, $this->getActiveArticleContext());
        }

        return $context;
    }
}
