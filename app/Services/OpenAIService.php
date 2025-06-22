<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdatePlanJob;
use App\Jobs\WebSearchJob;
use App\Jobs\FetchWebpageJob;
use App\Jobs\WriteArticleSectionJob;
use App\Jobs\CreateArticleJob;
use App\Jobs\ReviewArticleJob;
use App\Jobs\ContinueConversationJob;

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
            'content' => $message ?: ' ', // Ensure content is never null
        ];

        return $messages;
    }

    private function getSystemPrompt(Conversation $conversation, array $context): string
    {
        $plan = $conversation->agent_plan ? json_encode($conversation->agent_plan) : 'No plan yet';

        $recentResults = '';
        if (isset($context['context']) && $context['context'] === 'job_completed') {
            $recentCompletedChats = $conversation->chats()
                ->whereNotNull('function_response')
                ->where('job_status', 'completed')
                ->latest()
                ->limit(3)
                ->get();

            if ($recentCompletedChats->isNotEmpty()) {
                $recentResults = "\n\nRecent function call results:\n";
                foreach ($recentCompletedChats as $chat) {
                    if ($chat->function_name === 'web_search' && $chat->web_search_results) {
                        $recentResults .= "- Web search for '{$chat->function_arguments['query']}' returned:\n";
                        foreach (array_slice($chat->web_search_results, 0, 3) as $result) {
                            $recentResults .= "  * {$result['title']} ({$result['url']})\n";
                            if (!empty($result['content'])) {
                                $recentResults .= "    Content preview: " . substr($result['content'], 0, 200) . "...\n";
                            }
                        }
                    } elseif ($chat->function_name === 'fetch_webpage' && $chat->web_search_results) {
                        $recentResults .= "- Fetched webpage content:\n";
                        $recentResults .= "  Content: " . substr($chat->web_search_results, 0, 1000) . "...\n";
                    } elseif ($chat->function_name === 'create_article' && $chat->function_response) {
                        $response = $chat->function_response;
                        $recentResults .= "- Created article '{$response['title']}' with ID {$response['article_id']}\n";
                        $recentResults .= "  use this article id ({$response['article_id']}) for writing sections\n";
                    } elseif ($chat->function_name === 'write_article_section' && $chat->function_response) {
                        $response = $chat->function_response;
                        $recentResults .= "- Updated article section '{$response['section']}' for article ID {$response['article_id']}\n";
                        $recentResults .= "  Article version: {$response['version']}\n";
                        $recentResults .= "  Word count: {$response['word_count']}\n";
                    }
                }
            }
        }

        return <<<PROMPT
You are an advanced research and writing agent. Your primary goal is to help users create comprehensive, well-researched articles.

Current conversation plan: {$plan}{$recentResults}

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
- USE THE RECENT FUNCTION CALL RESULTS SHOWN ABOVE when they are available

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
            'content' => $message->content ?? '', // Ensure content is never null
            'function_name' => $message->functionCall->name ?? null,
            'function_arguments' => $message->functionCall->arguments ?? null,
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
        ]);

        $chat->calculateCost();
        $conversation->addTokenUsage(
            $usage->promptTokens + $usage->completionTokens,
            $chat->cost
        );

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
                if (isset($arguments['plan'])) {
                    UpdatePlanJob::dispatch($conversation, $arguments['plan']);
                } else {
                    Log::warning('Missing or empty plan in update_plan function call', ['arguments' => $arguments]);
                }
                break;
            case 'web_search':
                if (isset($arguments['query'])) {
                    WebSearchJob::dispatch($conversation, $chat, $arguments['query']);
                } else {
                    Log::warning('Missing query in web_search function call', ['arguments' => $arguments]);
                }
                break;
            case 'fetch_webpage':
                if (isset($arguments['url'])) {
                    FetchWebpageJob::dispatch($conversation, $chat, $arguments['url']);
                } else {
                    Log::warning('Missing url in fetch_webpage function call', ['arguments' => $arguments]);
                }
                break;
            case 'write_article_section':
                if (isset($arguments['article_id']) && isset($arguments['section']) && isset($arguments['content'])) {
                    WriteArticleSectionJob::dispatch(
                        $conversation,
                        $chat,
                        $arguments['article_id'],
                        $arguments['section'],
                        $arguments['content']
                    );
                } else {
                    Log::warning('Missing required parameters in write_article_section function call', ['arguments' => $arguments]);
                }
                break;
            case 'create_article':
                if (isset($arguments['title']) && isset($arguments['outline'])) {
                    CreateArticleJob::dispatch(
                        $conversation,
                        $chat,
                        $arguments['title'],
                        $arguments['outline']
                    );
                } else {
                    Log::warning('Missing required parameters in create_article function call', ['arguments' => $arguments]);
                }
                break;
            case 'review_article':
                if (isset($arguments['article_id'])) {
                    ReviewArticleJob::dispatch(
                        $conversation,
                        $chat,
                        $arguments['article_id']
                    );
                } else {
                    Log::warning('Missing article_id in review_article function call', ['arguments' => $arguments]);
                }
                break;
        }
    }

    private function getAvailableFunctions(): array
    {
        return [
            [
                'name' => 'update_plan',
                'description' => 'Update the current research and writing plan with specific steps and progress',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'plan' => [
                            'type' => 'object',
                            'description' => 'The updated plan object containing steps, goals, or progress. Must not be empty.',
                            'properties' => [
                                'steps' => [
                                    'type' => 'array',
                                    'description' => 'List of steps or tasks in the plan',
                                    'items' => ['type' => 'string']
                                ],
                                'goal' => [
                                    'type' => 'string',
                                    'description' => 'The main goal or objective'
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'description' => 'Current status of the plan'
                                ],
                                'notes' => [
                                    'type' => 'string',
                                    'description' => 'Additional notes or context'
                                ]
                            ],
                            'minProperties' => 1
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
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => [
                                        'type' => 'string',
                                        'description' => 'Section title'
                                    ],
                                    'subsections' => [
                                        'type' => 'array',
                                        'description' => 'Optional subsections',
                                        'items' => [
                                            'type' => 'string'
                                        ]
                                    ]
                                ]
                            ]
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
