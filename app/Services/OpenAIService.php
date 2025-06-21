<?php

namespace App\Services;

use App\Jobs\CreateArticleJob;
use App\Jobs\FetchWebpageJob;
use App\Jobs\ReviewArticleJob;
use App\Jobs\UpdatePlanJob;
use App\Jobs\WebSearchJob;
use App\Jobs\WriteArticleSectionJob;
use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIService
{
    private array $functions;

    public function __construct()
    {
        $this->functions = $this->getAvailableFunctions();
    }

    public function sendMessage(Conversation $conversation, string $message, array $context = []): array
    {
        $messages = $this->prepareMessages($conversation, $message, $context);

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => $messages,
                'functions' => $this->functions,
                'function_call' => 'auto',
                'temperature' => 0.7,
            ]);

            return $this->processResponse($conversation, $response);
        } catch (\Exception $e) {
            Log::error('OpenAI API Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function prepareMessages(Conversation $conversation, string $message, array $context): array
    {
        $messages = [];

        $messages[] = [
            'role' => 'system',
            'content' => $this->getSystemPrompt($conversation, $context),
        ];

        $recentChats = $conversation->chats()
            ->whereIn('role', ['user', 'assistant'])
            ->latest()
            ->limit(10)
            ->get()
            ->reverse();

        foreach ($recentChats as $chat) {
            $messages[] = [
                'role' => $chat->role,
                'content' => $chat->content,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return $messages;
    }

    private function getSystemPrompt(Conversation $conversation, array $context): string
    {
        $plan = $conversation->agent_plan ? json_encode($conversation->agent_plan) : 'No plan yet';

        return <<<PROMPT
You are an advanced research and writing agent. Your primary goal is to help users create comprehensive, well-researched articles.

Current conversation plan: {$plan}

Your capabilities:
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

When writing articles:
- Follow the outline structure
- Write one section at a time
- Research thoroughly before writing each section
- Review and revise as needed
- Ensure coherence across sections
PROMPT;
    }

    private function processResponse(Conversation $conversation, $response): array
    {
        $message = $response->choices[0]->message;
        $usage = $response->usage;

        $chat = $conversation->chats()->create([
            'role' => 'assistant',
            'content' => $message->content ?? null,
            'function_name' => $message->functionCall->name ?? null,
            'function_arguments' => $message->functionCall->arguments ?? null,
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
        ]);

        $chat->calculateCost();
        $conversation->addTokenUsage($usage->promptTokens + $usage->completionTokens, $chat->cost);

        if (isset($message->functionCall)) {
            $this->handleFunctionCall($conversation, $chat, $message->functionCall);
        }

        return [
            'chat' => $chat,
            'needsFollowUp' => isset($message->functionCall),
        ];
    }

    private function handleFunctionCall(Conversation $conversation, Chat $chat, $functionCall): void
    {
        $functionName = $functionCall->name;
        $arguments = json_decode($functionCall->arguments, true);

        $chat->update([
            'function_name' => $functionName,
            'function_arguments' => $arguments,
        ]);

        switch ($functionName) {
            case 'update_plan':
                UpdatePlanJob::dispatch($conversation, $chat, $arguments['plan']);
                break;
            case 'web_search':
                WebSearchJob::dispatch($conversation, $chat, $arguments['query']);
                break;
            case 'fetch_webpage':
                FetchWebpageJob::dispatch($conversation, $chat, $arguments['url']);
                break;
            case 'write_article_section':
                WriteArticleSectionJob::dispatch($conversation, $chat, $arguments['article_id'], $arguments['section'], $arguments['content']);
                break;
            case 'create_article':
                CreateArticleJob::dispatch($conversation, $chat, $arguments['title'], $arguments['outline']);
                break;
            case 'review_article':
                ReviewArticleJob::dispatch($conversation, $chat, $arguments['article_id']);
                break;
        }
    }

    private function getAvailableFunctions(): array
    {
        return [
            [
                'name' => 'update_plan',
                'description' => 'Update the current research and writing plan',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'plan' => [
                            'type' => 'object',
                            'description' => 'The updated plan with steps, status, and notes',
                        ],
                    ],
                    'required' => ['plan'],
                ],
            ],
            [
                'name' => 'web_search',
                'description' => 'Search the web for information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'fetch_webpage',
                'description' => 'Fetch and extract content from a webpage',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'The URL to fetch',
                        ],
                    ],
                    'required' => ['url'],
                ],
            ],
            [
                'name' => 'create_article',
                'description' => 'Create a new article with title and outline',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'The article title',
                        ],
                        'outline' => [
                            'type' => 'array',
                            'description' => 'The article outline structure',
                        ],
                    ],
                    'required' => ['title', 'outline'],
                ],
            ],
            [
                'name' => 'write_article_section',
                'description' => 'Write or update a section of an article',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'article_id' => [
                            'type' => 'integer',
                            'description' => 'The article ID',
                        ],
                        'section' => [
                            'type' => 'string',
                            'description' => 'The section identifier',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The content for this section',
                        ],
                    ],
                    'required' => ['article_id', 'section', 'content'],
                ],
            ],
            [
                'name' => 'review_article',
                'description' => 'Review an article for coherence, accuracy, and completeness',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'article_id' => [
                            'type' => 'integer',
                            'description' => 'The article ID to review',
                        ],
                    ],
                    'required' => ['article_id'],
                ],
            ],
        ];
    }
}
