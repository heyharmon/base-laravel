<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Article;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

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
        Log::info('OpenAI Service: Starting message processing', [
            'conversation_id' => $conversation->id,
            'user_message_length' => strlen($userMessage),
            'user_message_preview' => substr($userMessage, 0, 100)
        ]);

        // Save user message
        $conversation->chats()->create([
            'type' => 'user',
            'content' => $userMessage
        ]);

        Log::info('OpenAI Service: User message saved to conversation', [
            'conversation_id' => $conversation->id
        ]);

        // Build messages array with context
        $messages = $this->buildMessages($conversation);

        // Call OpenAI
        Log::info('OpenAI Service: Calling OpenAI API', [
            'conversation_id' => $conversation->id,
            'model' => 'gpt-4-turbo-preview',
            'tools_count' => count($this->tools)
        ]);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4-turbo-preview',
            'messages' => $messages,
            'tools' => $this->tools,
            'tool_choice' => 'auto'
        ]);

        Log::info('OpenAI Service: Received response from OpenAI', [
            'conversation_id' => $conversation->id,
            'choices_count' => count($response->choices)
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

            Log::debug('OpenAI Service: Added context to system message', [
                'conversation_id' => $conversation->id,
                'context_keys' => array_keys($conversation->context)
            ]);
        }

        $messages[] = [
            'role' => 'system',
            'content' => $systemMessage
        ];

        // Add conversation history
        $chatCount = 0;
        $toolCallCount = 0;

        foreach ($conversation->chats as $chat) {
            if ($chat->type === 'user') {
                $messages[] = [
                    'role' => 'user',
                    'content' => $chat->content
                ];
                $chatCount++;
            } elseif ($chat->type === 'assistant') {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $chat->content
                ];
                $chatCount++;
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
                $toolCallCount++;
            }
        }

        Log::debug('OpenAI Service: Messages array built successfully', [
            'conversation_id' => $conversation->id,
            'total_messages' => count($messages),
            'chat_messages' => $chatCount,
            'tool_calls' => $toolCallCount
        ]);

        return $messages;
    }

    protected function processResponse(Conversation $conversation, $response)
    {
        Log::info('OpenAI Service: Processing OpenAI response', [
            'conversation_id' => $conversation->id
        ]);

        $choice = $response->choices[0];
        $message = $choice->message;

        // Handle tool calls
        if (isset($message->toolCalls) && !empty($message->toolCalls)) {
            Log::info('OpenAI Service: Tool calls detected', [
                'conversation_id' => $conversation->id,
                'tool_calls_count' => count($message->toolCalls)
            ]);

            foreach ($message->toolCalls as $index => $toolCall) {
                $functionName = $toolCall->function->name;
                $arguments = json_decode($toolCall->function->arguments, true);

                Log::info('OpenAI Service: Executing tool call', [
                    'conversation_id' => $conversation->id,
                    'tool_call_index' => $index + 1,
                    'function_name' => $functionName,
                    'tool_call_id' => $toolCall->id,
                    'arguments' => $arguments
                ]);

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

                Log::debug('OpenAI Service: Tool call saved to conversation', [
                    'conversation_id' => $conversation->id,
                    'function_name' => $functionName
                ]);
            }

            // Continue the conversation with tool results
            Log::info('OpenAI Service: Continuing conversation with tool results', [
                'conversation_id' => $conversation->id
            ]);

            return $this->continueWithToolResults($conversation);
        }

        // Save assistant message
        if ($message->content) {
            Log::info('OpenAI Service: Saving assistant message', [
                'conversation_id' => $conversation->id,
                'message_length' => strlen($message->content)
            ]);

            $conversation->chats()->create([
                'type' => 'assistant',
                'content' => $message->content
            ]);
        }

        Log::info('OpenAI Service: Response processing completed', [
            'conversation_id' => $conversation->id
        ]);

        return $conversation->fresh()->chats;
    }

    protected function executeTool($functionName, $arguments)
    {
        switch ($functionName) {
            case 'list_articles':
                $page = $arguments['page'] ?? 1;
                $perPage = $arguments['per_page'] ?? 20;

                Log::debug('OpenAI Service: Listing articles', [
                    'page' => $page,
                    'per_page' => $perPage
                ]);

                // Ensure per_page doesn't exceed maximum
                $perPage = min($perPage, 100);

                $paginatedArticles = Article::select(['id', 'title', 'created_at'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage, ['*'], 'page', $page);

                Log::info('OpenAI Service: Articles retrieved', [
                    'total_articles' => $paginatedArticles->total(),
                    'current_page' => $paginatedArticles->currentPage(),
                    'articles_on_page' => count($paginatedArticles->items())
                ]);

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
                $articleId = $arguments['article_id'];

                Log::debug('OpenAI Service: Viewing article', [
                    'article_id' => $articleId
                ]);

                $article = Article::find($articleId);
                if (!$article) {
                    Log::warning('OpenAI Service: Article not found', [
                        'article_id' => $articleId
                    ]);
                    return json_encode(['error' => 'Article not found']);
                }

                Log::info('OpenAI Service: Article retrieved', [
                    'article_id' => $articleId,
                    'article_title' => $article->title,
                    'content_length' => strlen($article->content)
                ]);

                return json_encode($article->toArray());

            case 'create_article':
                $title = $arguments['title'];
                $content = $arguments['content'];

                Log::debug('OpenAI Service: Creating article', [
                    'title' => $title,
                    'content_length' => strlen($content)
                ]);

                $article = Article::create([
                    'title' => $title,
                    'content' => $content
                ]);

                Log::info('OpenAI Service: Article created', [
                    'article_id' => $article->id,
                    'title' => $title
                ]);

                return json_encode([
                    'success' => true,
                    'article' => $article->toArray()
                ]);

            case 'edit_article':
                $articleId = $arguments['article_id'];

                Log::debug('OpenAI Service: Editing article', [
                    'article_id' => $articleId,
                    'has_title_update' => isset($arguments['title']),
                    'has_content_update' => isset($arguments['content'])
                ]);

                $article = Article::find($articleId);
                if (!$article) {
                    Log::warning('OpenAI Service: Article not found for editing', [
                        'article_id' => $articleId
                    ]);
                    return json_encode(['error' => 'Article not found']);
                }

                $changes = [];
                if (isset($arguments['title'])) {
                    $changes['title'] = ['from' => $article->title, 'to' => $arguments['title']];
                    $article->title = $arguments['title'];
                }
                if (isset($arguments['content'])) {
                    $changes['content_length'] = ['from' => strlen($article->content), 'to' => strlen($arguments['content'])];
                    $article->content = $arguments['content'];
                }
                $article->save();

                Log::info('OpenAI Service: Article updated', [
                    'article_id' => $articleId,
                    'changes' => $changes
                ]);

                return json_encode([
                    'success' => true,
                    'article' => $article->toArray()
                ]);

            case 'web_search':
                $query = $arguments['query'];

                Log::debug('OpenAI Service: Performing web search', [
                    'query' => $query
                ]);

                // Implement web search (you'll need to set up your preferred search API)
                // This is a placeholder implementation
                Log::info('OpenAI Service: Web search completed (placeholder)', [
                    'query' => $query
                ]);

                return json_encode([
                    'results' => [
                        ['title' => 'Search result 1', 'snippet' => 'Content...'],
                        ['title' => 'Search result 2', 'snippet' => 'Content...']
                    ]
                ]);

            default:
                Log::error('OpenAI Service: Unknown tool function', [
                    'function_name' => $functionName,
                    'arguments' => $arguments
                ]);
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
        Log::info('OpenAI Service: Continuing conversation with tool results', [
            'conversation_id' => $conversation->id
        ]);

        $messages = $this->buildMessages($conversation);

        Log::info('OpenAI Service: Making follow-up call to OpenAI with tool results', [
            'conversation_id' => $conversation->id,
            'message_count' => count($messages)
        ]);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4-turbo-preview',
            'messages' => $messages,
            'tools' => $this->tools,
            'tool_choice' => 'auto'
        ]);

        Log::info('OpenAI Service: Received follow-up response from OpenAI', [
            'conversation_id' => $conversation->id
        ]);

        return $this->processResponse($conversation, $response);
    }
}
