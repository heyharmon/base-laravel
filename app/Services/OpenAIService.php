<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Article;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class OpenAIService
{
    protected $tools = [
        [
            'type' => 'function',
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
        ],
        [
            'type' => 'function',
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
        ],
        [
            'type' => 'function',
            'name' => 'create_article',
            'description' => 'Create a new article',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                        'description' => 'The title of the article'
                    ],
                ],
                'required' => ['title']
            ]
        ],
        [
            'type' => 'function',
            'name' => 'edit_article_title',
            'description' => 'Edit the title of an existing article',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'article_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the article to edit'
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => 'The new title for the article'
                    ]
                ],
                'required' => ['article_id', 'title']
            ]
        ],
        [
            'type' => 'function',
            'name' => 'edit_article_content',
            'description' => 'Edit the content of an existing article. For long content, use multiple calls with append mode to write in chunks of ~200 words.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'article_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the article to edit'
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'The new content to add or use for replacement'
                    ],
                    'mode' => [
                        'type' => 'string',
                        'enum' => ['replace', 'append', 'prepend', 'insert_at_marker'],
                        'description' => 'How to apply the content: "replace" replaces specific text, "append" adds to the end, "prepend" adds to the beginning, "insert_at_marker" inserts at a position marker. Default is "append" for articles with content, "prepend" for empty articles.',
                        'default' => 'auto'
                    ],
                    'search_text' => [
                        'type' => 'string',
                        'description' => 'For replace mode: the exact text to replace. If not provided with replace mode, will append to existing content.'
                    ],
                    'position_marker' => [
                        'type' => 'string',
                        'description' => 'For insert_at_marker mode: a marker/phrase indicating where to insert the content (e.g., "after the introduction", "before the conclusion")'
                    ],
                    'replace_all_occurrences' => [
                        'type' => 'boolean',
                        'description' => 'For replace mode: whether to replace all occurrences of search_text or just the first one. Default is false.',
                        'default' => false
                    ]
                ],
                'required' => ['article_id', 'content']
            ]
        ],
        [
            'type' => 'function',
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
    ];

    public function processMessage(Conversation $conversation, string $userMessage)
    {
        Log::info('OpenAI Service: Processing message', [
            'conversation_id' => $conversation->id,
            'message_length' => strlen($userMessage)
        ]);

        // Save user message
        $conversation->chats()->create([
            'type' => 'user',
            'content' => $userMessage
        ]);

        // Build and send request
        $request = $this->buildRequest($conversation, $userMessage);
        $response = OpenAI::responses()->create($request);

        Log::info('OpenAI Service: Received response', [
            'response' => $response,
        ]);

        // Process the response
        return $this->processResponse($conversation, $response);
    }

    public function processMessageAsync(Conversation $conversation, string $userMessage)
    {
        Log::info('OpenAI Service: Processing message asynchronously', [
            'conversation_id' => $conversation->id,
            'message_length' => strlen($userMessage)
        ]);

        // Process in background
        dispatch(function () use ($conversation, $userMessage) {
            try {
                // Build and send request
                $request = $this->buildRequest($conversation, $userMessage);
                $response = OpenAI::responses()->create($request);

                Log::info('OpenAI Service: Received async response', [
                    'conversation_id' => $conversation->id,
                    'response' => $response,
                ]);

                // Process the response
                $this->processResponse($conversation, $response);
            } catch (\Exception $e) {
                Log::error('OpenAI Service: Async processing failed', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage()
                ]);
                
                // Create error message in chat
                $conversation->chats()->create([
                    'type' => 'assistant',
                    'content' => 'Sorry, I encountered an error processing your message. Please try again.'
                ]);
            }
        });
    }

    protected function buildRequest(Conversation $conversation, string $userMessage): array
    {
        $instructions = $this->composeSystemPrompt($conversation);
        $previous = $conversation->openai_response_id;

        return array_filter([
            'model' => 'o4-mini-2025-04-16',
            'instructions' => $instructions,
            'input' => $userMessage,
            'store' => true,
            'tools' => $this->tools,
            'tool_choice' => 'auto',
            'parallel_tool_calls' => true,
            // 'reasoning' => [
            //     'effort' => 'medium', // default
            //     'summary' => 'auto'
            // ],
            'previous_response_id' => $previous,
        ]);
    }

    protected function composeSystemPrompt(Conversation $conversation): string
    {
        $systemMessage = "You are a helpful assistant with access to articles in a database. \n";
        $systemMessage .= "When writing or editing long article content, write in chunks of approximately 200 words at a time ";
        $systemMessage .= "using multiple edit_article_content calls with mode=\"append\". This provides faster feedback to the user. \n";
        $systemMessage .= "Use edit_article_title to change titles and edit_article_content to modify content. \n";
        $systemMessage .= "Always write article content in markdown.";

        if ($conversation->context) {
            $systemMessage .= "\n\nCurrent frontend context:\n";
            foreach ($conversation->context as $key => $value) {
                $systemMessage .= "- {$key}: {$value}\n";
            }
        }

        return $systemMessage;
    }

    protected function handleAssistantMessage(Conversation $conversation, $item): void
    {
        // Log::info('OpenAI Service: Assistant message', [
        //     'item' => $item,
        // ]);

        $conversation->chats()->create([
            'type' => 'assistant',
            'content' => $item->content[0]->text ?? '',
        ]);
    }

    protected function handleReasoningMessage(Conversation $conversation, $item): void
    {
        // Extract reasoning content - adjust based on the actual structure of $item
        $reasoningContent = $item->content ?? $item->text ?? $item->reasoning ?? '';

        // Log the resoning content
        // Log::info('OpenAI Service: Reasoning message', [
        //     'item' => $item,
        // ]);

        // You might want to format or summarize the reasoning here
        $conversation->chats()->create([
            'type' => 'reasoning',
            'content' => '',
        ]);
    }

    protected function handleFunctionCall(Conversation $conversation, $item): array
    {
        $functionName = $item->name;
        $argumentsJson = is_string($item->arguments)
            ? $item->arguments
            : json_encode($item->arguments);
        $callId = $item->callId;

        // Execute the function
        $result = $this->executeTool($functionName, $argumentsJson);

        // Save to conversation history
        $conversation->chats()->create([
            'type' => 'tool_call',
            'content' => $this->getToolCallDescription($functionName, $argumentsJson),
            'metadata' => [
                'tool_call' => [
                    'id' => $callId,
                    'type' => 'function',
                    'function' => [
                        'name' => $functionName,
                        'arguments' => $argumentsJson,
                    ],
                ],
                'result' => $result,
            ],
        ]);

        return [
            'type' => 'function_call_output',
            'call_id' => $callId,
            'output' => $result
        ];
    }

    protected function processResponse(Conversation $conversation, $response)
    {
        $toolOutputs = [];
        $functionCalls = [];

        // Process all output items
        foreach ($response->output as $item) {
            switch ($item->type) {
                case 'message':
                    $this->handleAssistantMessage($conversation, $item);
                    break;
                case 'reasoning':
                    $this->handleReasoningMessage($conversation, $item);
                    break;
                case 'function_call':
                    $functionCalls[] = $item;
                    break;
                case 'web_search_call':
                    // Handled automatically by OpenAI
                    break;
            }
        }

        // Execute function calls
        foreach ($functionCalls as $functionCall) {
            try {
                $toolOutputs[] = $this->handleFunctionCall($conversation, $functionCall);
            } catch (\Exception $e) {
                Log::error('OpenAI Service: Function call failed', [
                    'conversation_id' => $conversation->id,
                    'function_name' => $functionCall->name ?? 'unknown',
                    'error' => $e->getMessage()
                ]);

                // Return error output for this call
                $toolOutputs[] = [
                    'type' => 'function_call_output',
                    'call_id' => $functionCall->callId ?? 'unknown',
                    'output' => json_encode(['error' => 'Function execution failed'])
                ];
            }
        }

        // Save response ID
        $conversation->openai_response_id = $response->id;
        $conversation->save();

        // Send tool outputs back if any
        if (!empty($toolOutputs)) {
            try {
                $followUp = OpenAI::responses()->create([
                    'model' => 'o4-mini-2025-04-16',
                    'previous_response_id' => $response->id,
                    'tools' => $this->tools,
                    'input' => $toolOutputs,
                ]);

                return $this->processResponse($conversation, $followUp);
            } catch (\Exception $e) {
                Log::error('OpenAI Service: Follow-up request failed', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage()
                ]);

                return $conversation->fresh()->chats;
            }
        }

        return $conversation->fresh()->chats;
    }

    protected function executeTool($functionName, $arguments)
    {
        $arguments = json_decode($arguments, true);

        switch ($functionName) {
            case 'list_articles':
                $page = $arguments['page'] ?? 1;
                $perPage = min($arguments['per_page'] ?? 20, 100);

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
                $article = Article::create(['title' => $arguments['title']]);
                return json_encode([
                    'success' => true,
                    'message' => 'Article created successfully',
                    'article' => $article->toArray()
                ]);

            case 'edit_article_title':
                $article = Article::find($arguments['article_id']);
                if (!$article) {
                    return json_encode(['error' => 'Article not found']);
                }

                $oldTitle = $article->title;
                $article->title = $arguments['title'];
                $article->save();

                return json_encode([
                    'success' => true,
                    'message' => 'Article title updated successfully',
                    'article_id' => $article->id,
                    'old_title' => $oldTitle,
                    'new_title' => $arguments['title']
                ]);

            case 'edit_article_content':
                $article = Article::find($arguments['article_id']);
                if (!$article) {
                    return json_encode(['error' => 'Article not found']);
                }

                $content = $arguments['content'];
                $mode = $arguments['mode'] ?? 'auto';
                $searchText = $arguments['search_text'] ?? null;
                $positionMarker = $arguments['position_marker'] ?? null;
                $replaceAll = $arguments['replace_all_occurrences'] ?? false;

                $originalLength = strlen($article->content);
                $originalWordCount = str_word_count($article->content);

                // Auto-determine mode
                if ($mode === 'auto') {
                    $mode = empty($article->content) ? 'prepend' : 'append';
                }

                // Apply content based on mode
                switch ($mode) {
                    case 'replace':
                        if ($searchText) {
                            if ($replaceAll) {
                                $article->content = str_replace($searchText, $content, $article->content);
                                $occurrences = substr_count($article->content, $searchText);
                            } else {
                                $pos = strpos($article->content, $searchText);
                                if ($pos !== false) {
                                    $article->content = substr_replace($article->content, $content, $pos, strlen($searchText));
                                    $occurrences = 1;
                                } else {
                                    $occurrences = 0;
                                }
                            }
                            if ($occurrences === 0) {
                                return json_encode(['error' => 'Search text not found in article']);
                            }
                        } else {
                            $article->content = $article->content . $content;
                            $mode = 'append';
                        }
                        break;

                    case 'insert_at_marker':
                        if (!$positionMarker) {
                            return json_encode(['error' => 'Position marker required for insert_at_marker mode']);
                        }

                        $inserted = false;
                        // Try common patterns
                        if (preg_match('/after\s+(the\s+)?(.+)/i', $positionMarker, $matches)) {
                            $searchPhrase = $matches[2];
                            $pos = stripos($article->content, $searchPhrase);
                            if ($pos !== false) {
                                $endPos = $pos + strlen($searchPhrase);
                                $nextPeriod = strpos($article->content, '.', $endPos);
                                $nextNewline = strpos($article->content, "\n", $endPos);
                                $insertPos = min(
                                    $nextPeriod !== false ? $nextPeriod + 1 : PHP_INT_MAX,
                                    $nextNewline !== false ? $nextNewline : PHP_INT_MAX
                                );
                                if ($insertPos === PHP_INT_MAX) $insertPos = strlen($article->content);

                                $article->content = substr($article->content, 0, $insertPos) . ' ' . $content . substr($article->content, $insertPos);
                                $inserted = true;
                            }
                        }

                        if (!$inserted && preg_match('/before\s+(the\s+)?(.+)/i', $positionMarker, $matches)) {
                            $searchPhrase = $matches[2];
                            $pos = stripos($article->content, $searchPhrase);
                            if ($pos !== false) {
                                $article->content = substr($article->content, 0, $pos) . $content . ' ' . substr($article->content, $pos);
                                $inserted = true;
                            }
                        }

                        // Try direct match
                        if (!$inserted) {
                            $pos = stripos($article->content, $positionMarker);
                            if ($pos !== false) {
                                $endPos = $pos + strlen($positionMarker);
                                $article->content = substr($article->content, 0, $endPos) . ' ' . $content . substr($article->content, $endPos);
                                $inserted = true;
                            }
                        }

                        if (!$inserted) {
                            return json_encode(['error' => 'Could not find position marker in article']);
                        }
                        break;

                    case 'append':
                        $article->content = $article->content . $content;
                        break;

                    case 'prepend':
                        $article->content = $content . $article->content;
                        break;

                    default:
                        return json_encode(['error' => 'Invalid mode specified']);
                }

                $article->save();

                $newLength = strlen($article->content);
                $newWordCount = str_word_count($article->content);
                $addedWords = str_word_count($content);

                $response = [
                    'success' => true,
                    'message' => $this->getEditSuccessMessage($mode, $searchText, $positionMarker),
                    'article_id' => $article->id,
                    'mode' => $mode,
                    'progress' => [
                        'total_words' => $newWordCount,
                        'total_length' => $newLength,
                        'chunk_words' => $addedWords,
                        'chunk_length' => strlen($content)
                    ]
                ];

                if ($mode === 'replace' && isset($occurrences)) {
                    $response['replacements'] = $occurrences;
                }

                if ($mode !== 'replace' || !$searchText) {
                    $response['progress']['previous_words'] = $originalWordCount;
                    $response['progress']['previous_length'] = $originalLength;
                }

                return json_encode($response);

            case 'web_search':
                // Placeholder implementation
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

    protected function getEditSuccessMessage($mode, $searchText = null, $positionMarker = null)
    {
        switch ($mode) {
            case 'replace':
                return $searchText ? "Replaced text successfully" : "Content appended successfully (no search text provided for replacement)";
            case 'append':
                return "Content appended successfully";
            case 'prepend':
                return "Content prepended successfully";
            case 'insert_at_marker':
                return "Content inserted at specified position";
            default:
                return "Article content updated successfully";
        }
    }

    protected function getToolCallDescription($functionName, $arguments)
    {
        $arguments = json_decode($arguments, true);

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

            case 'edit_article_content':
                $article = Article::find($arguments['article_id']);
                $title = $article ? $article->title : 'Unknown';
                $mode = $arguments['mode'] ?? 'auto';
                $wordCount = str_word_count($arguments['content']);

                if ($mode === 'auto') {
                    $mode = ($article && empty($article->content)) ? 'prepend' : 'append';
                }

                if ($mode === 'replace' && isset($arguments['search_text'])) {
                    $searchPreview = strlen($arguments['search_text']) > 30 ?
                        substr($arguments['search_text'], 0, 30) . '...' :
                        $arguments['search_text'];
                    return "Replacing \"{$searchPreview}\" in article \"{$title}\" ({$wordCount} words)...";
                } elseif ($mode === 'insert_at_marker' && isset($arguments['position_marker'])) {
                    return "Inserting {$wordCount} words at \"{$arguments['position_marker']}\" in article \"{$title}\"...";
                } elseif ($mode === 'append') {
                    return "Appending {$wordCount} words to article \"{$title}\"...";
                } elseif ($mode === 'prepend') {
                    return "Prepending {$wordCount} words to article \"{$title}\"...";
                } else {
                    return "Editing content of article \"{$title}\" ({$wordCount} words)...";
                }

            case 'web_search':
                return "Searching for: \"{$arguments['query']}\"";

            default:
                return 'Executing tool...';
        }
    }
}
