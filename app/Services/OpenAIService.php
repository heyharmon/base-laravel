<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Article;
use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Support\Facades\Http;

class OpenAIService
{
    protected $tools = [
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_articles',
                'description' => 'List articles in the database with pagination support',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'page' => [
                            'type' => 'integer',
                            'description' => 'Page number (default: 1)',
                            'minimum' => 1
                        ],
                        'per_page' => [
                            'type' => 'integer',
                            'description' => 'Number of articles per page (default: 20, max: 100)',
                            'minimum' => 1,
                            'maximum' => 100
                        ]
                    ],
                    'required' => []
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'view_article',
                'description' => 'View the content of a specific article',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'article_id' => [
                            'type' => 'integer',
                            'description' => 'The ID of the article to view'
                        ]
                    ],
                    'required' => ['article_id']
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'create_article',
                'description' => 'Create a new article',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'The title of the article'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The content of the article'
                        ]
                    ],
                    'required' => ['title', 'content']
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'edit_article',
                'description' => 'Edit an existing article',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'article_id' => [
                            'type' => 'integer',
                            'description' => 'The ID of the article to edit'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'The new title (optional)'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The new content (optional)'
                        ]
                    ],
                    'required' => ['article_id']
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'web_search',
                'description' => 'Search the web for information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query'
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ]
    ];

    public function processMessage(Conversation $conversation, string $userMessage)
    {
        // Save user message
        $conversation->chats()->create([
            'type' => 'user',
            'content' => $userMessage
        ]);

        // Build messages array with context
        $messages = $this->buildMessages($conversation);

        // Call OpenAI
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4-turbo-preview',
            'messages' => $messages,
            'tools' => $this->tools,
            'tool_choice' => 'auto'
        ]);

        // Process the response
        return $this->processResponse($conversation, $response);
    }

    protected function buildMessages(Conversation $conversation)
    {
        $messages = [];

        // Add system message with context
        $systemMessage = "You are a helpful assistant with access to articles in a database.";

        if ($conversation->context) {
            $systemMessage .= "\n\nCurrent frontend context:\n";
            foreach ($conversation->context as $key => $value) {
                $systemMessage .= "- {$key}: {$value}\n";
            }
        }

        $messages[] = [
            'role' => 'system',
            'content' => $systemMessage
        ];

        // Add conversation history
        foreach ($conversation->chats as $chat) {
            if ($chat->type === 'user') {
                $messages[] = [
                    'role' => 'user',
                    'content' => $chat->content
                ];
            } elseif ($chat->type === 'assistant') {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $chat->content
                ];
            } elseif ($chat->type === 'tool_call' && $chat->metadata) {
                // Include tool calls in the message history
                $messages[] = [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [$chat->metadata['tool_call']]
                ];

                $messages[] = [
                    'role' => 'tool',
                    'content' => $chat->metadata['result'],
                    'tool_call_id' => $chat->metadata['tool_call']['id']
                ];
            }
        }

        return $messages;
    }

    protected function processResponse(Conversation $conversation, $response)
    {
        $choice = $response->choices[0];
        $message = $choice->message;

        // Handle tool calls
        if (isset($message->toolCalls) && !empty($message->toolCalls)) {
            foreach ($message->toolCalls as $toolCall) {
                $functionName = $toolCall->function->name;
                $arguments = json_decode($toolCall->function->arguments, true);

                // Execute the tool
                $result = $this->executeTool($functionName, $arguments);

                // Save tool call as chat
                $conversation->chats()->create([
                    'type' => 'tool_call',
                    'content' => $this->getToolCallDescription($functionName, $arguments),
                    'metadata' => [
                        'tool_call' => $toolCall->toArray(),
                        'result' => $result
                    ]
                ]);
            }

            // Continue the conversation with tool results
            return $this->continueWithToolResults($conversation);
        }

        // Save assistant message
        if ($message->content) {
            $conversation->chats()->create([
                'type' => 'assistant',
                'content' => $message->content
            ]);
        }

        return $conversation->fresh()->chats;
    }

    protected function executeTool($functionName, $arguments)
    {
        switch ($functionName) {
            case 'list_articles':
                $page = $arguments['page'] ?? 1;
                $perPage = $arguments['per_page'] ?? 20;

                // Ensure per_page doesn't exceed maximum
                $perPage = min($perPage, 100);

                $paginatedArticles = Article::select(['id', 'title', 'created_at'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage, ['*'], 'page', $page);

                return json_encode([
                    'current_page' => $paginatedArticles->currentPage(),
                    'per_page' => $paginatedArticles->perPage(),
                    'total' => $paginatedArticles->total(),
                    'last_page' => $paginatedArticles->lastPage(),
                    'from' => $paginatedArticles->firstItem(),
                    'to' => $paginatedArticles->lastItem(),
                    'has_more_pages' => $paginatedArticles->hasMorePages(),
                    'articles' => $paginatedArticles->items()
                ]);

            case 'view_article':
                $article = Article::find($arguments['article_id']);
                if (!$article) {
                    return json_encode(['error' => 'Article not found']);
                }
                return json_encode($article->toArray());

            case 'create_article':
                $article = Article::create([
                    'title' => $arguments['title'],
                    'content' => $arguments['content']
                ]);
                return json_encode([
                    'success' => true,
                    'article' => $article->toArray()
                ]);

            case 'edit_article':
                $article = Article::find($arguments['article_id']);
                if (!$article) {
                    return json_encode(['error' => 'Article not found']);
                }

                if (isset($arguments['title'])) {
                    $article->title = $arguments['title'];
                }
                if (isset($arguments['content'])) {
                    $article->content = $arguments['content'];
                }
                $article->save();

                return json_encode([
                    'success' => true,
                    'article' => $article->toArray()
                ]);

            case 'web_search':
                // Implement web search (you'll need to set up your preferred search API)
                // This is a placeholder implementation
                return json_encode([
                    'results' => [
                        ['title' => 'Search result 1', 'snippet' => 'Content...'],
                        ['title' => 'Search result 2', 'snippet' => 'Content...']
                    ]
                ]);

            default:
                return json_encode(['error' => 'Unknown tool']);
        }
    }

    protected function getToolCallDescription($functionName, $arguments)
    {
        switch ($functionName) {
            case 'list_articles':
                $page = $arguments['page'] ?? 1;
                $perPage = $arguments['per_page'] ?? 20;
                return "Listing articles (page {$page}, {$perPage} per page)...";
            case 'view_article':
                $article = Article::find($arguments['article_id']);
                $title = $article ? $article->title : 'Unknown';
                return "Viewing article: \"{$title}\"...";
            case 'create_article':
                return "Creating article: \"{$arguments['title']}\"...";
            case 'edit_article':
                $article = Article::find($arguments['article_id']);
                $title = $article ? $article->title : 'Unknown';
                return "Editing article: \"{$title}\"...";
            case 'web_search':
                return "Searching for: \"{$arguments['query']}\"";
            default:
                return 'Executing tool...';
        }
    }

    protected function continueWithToolResults(Conversation $conversation)
    {
        $messages = $this->buildMessages($conversation);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4-turbo-preview',
            'messages' => $messages,
            'tools' => $this->tools,
            'tool_choice' => 'auto'
        ]);

        return $this->processResponse($conversation, $response);
    }
}
