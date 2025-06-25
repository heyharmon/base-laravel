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
                    ],
                    'required' => ['title']
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
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
            ]
        ],
        [
            'type' => 'function',
            'function' => [
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
            'model' => 'o4-mini-2025-04-16',
            'tools_count' => count($this->tools)
        ]);

        $response = OpenAI::chat()->create([
            'model' => 'o4-mini-2025-04-16',
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

        // Add system message with context and chunking instructions
        $systemMessage = "You are a helpful assistant with access to articles in a database. ";
        $systemMessage .= "When writing or editing long article content, write in chunks of approximately 200 words at a time ";
        $systemMessage .= "using multiple edit_article_content calls with mode=\"append\". This provides faster feedback to the user. ";
        $systemMessage .= "Use edit_article_title to change titles and edit_article_content to modify content.";

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

        // Group chats by their order and reconstruct proper conversation flow
        $chats = $conversation->chats()->orderBy('created_at')->get();
        $chatCount = 0;
        $toolCallCount = 0;
        $pendingToolCalls = [];

        foreach ($chats as $chat) {
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
                // Collect tool calls that should be grouped together
                $pendingToolCalls[] = $chat;
            }
        }

        // Process pending tool calls - group them properly
        if (!empty($pendingToolCalls)) {
            // Group tool calls that were made in the same assistant response
            $toolCallGroups = [];
            $currentGroup = [];
            $lastCreatedAt = null;

            foreach ($pendingToolCalls as $toolCall) {
                // If this is a new group (different timestamp or first call)
                if (
                    $lastCreatedAt === null ||
                    abs(strtotime($toolCall->created_at) - strtotime($lastCreatedAt)) > 5
                ) {

                    if (!empty($currentGroup)) {
                        $toolCallGroups[] = $currentGroup;
                    }
                    $currentGroup = [$toolCall];
                } else {
                    $currentGroup[] = $toolCall;
                }
                $lastCreatedAt = $toolCall->created_at;
            }

            if (!empty($currentGroup)) {
                $toolCallGroups[] = $currentGroup;
            }

            // Add each group as assistant message with tool calls + tool responses
            foreach ($toolCallGroups as $group) {
                // Assistant message with tool calls
                $toolCalls = [];
                foreach ($group as $toolCall) {
                    $toolCalls[] = $toolCall->metadata['tool_call'];
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => $toolCalls
                ];

                // Add tool responses
                foreach ($group as $toolCall) {
                    $messages[] = [
                        'role' => 'tool',
                        'content' => $toolCall->metadata['result'],
                        'tool_call_id' => $toolCall->metadata['tool_call']['id']
                    ];
                    $toolCallCount++;
                }
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

                // Save tool call as chat with proper metadata structure
                $conversation->chats()->create([
                    'type' => 'tool_call',
                    'content' => $this->getToolCallDescription($functionName, $arguments),
                    'metadata' => [
                        'tool_call' => [
                            'id' => $toolCall->id,
                            'type' => $toolCall->type,
                            'function' => [
                                'name' => $toolCall->function->name,
                                'arguments' => $toolCall->function->arguments
                            ]
                        ],
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

                Log::debug('OpenAI Service: Creating article', [
                    'title' => $title,
                ]);

                $article = Article::create([
                    'title' => $title,
                ]);

                Log::info('OpenAI Service: Article created', [
                    'article_id' => $article->id,
                    'title' => $title
                ]);

                return json_encode([
                    'success' => true,
                    'message' => 'Article created successfully',
                    'article' => $article->toArray()
                ]);

            case 'edit_article_title':
                $articleId = $arguments['article_id'];
                $newTitle = $arguments['title'];

                Log::debug('OpenAI Service: Editing article title', [
                    'article_id' => $articleId,
                    'new_title' => $newTitle
                ]);

                $article = Article::find($articleId);
                if (!$article) {
                    Log::warning('OpenAI Service: Article not found for title editing', [
                        'article_id' => $articleId
                    ]);
                    return json_encode(['error' => 'Article not found']);
                }

                $oldTitle = $article->title;
                $article->title = $newTitle;
                $article->save();

                Log::info('OpenAI Service: Article title updated', [
                    'article_id' => $articleId,
                    'old_title' => $oldTitle,
                    'new_title' => $newTitle
                ]);

                return json_encode([
                    'success' => true,
                    'message' => 'Article title updated successfully',
                    'article_id' => $article->id,
                    'old_title' => $oldTitle,
                    'new_title' => $newTitle
                ]);

            case 'edit_article_content':
                $articleId = $arguments['article_id'];
                $content = $arguments['content'];
                $mode = $arguments['mode'] ?? 'auto';
                $searchText = $arguments['search_text'] ?? null;
                $positionMarker = $arguments['position_marker'] ?? null;
                $replaceAll = $arguments['replace_all_occurrences'] ?? false;

                Log::debug('OpenAI Service: Editing article content', [
                    'article_id' => $articleId,
                    'mode' => $mode,
                    'content_length' => strlen($content),
                    'search_text' => $searchText ? substr($searchText, 0, 50) . '...' : null,
                    'position_marker' => $positionMarker
                ]);

                $article = Article::find($articleId);
                if (!$article) {
                    Log::warning('OpenAI Service: Article not found for content editing', [
                        'article_id' => $articleId
                    ]);
                    return json_encode(['error' => 'Article not found']);
                }

                $originalLength = strlen($article->content);
                $originalWordCount = str_word_count($article->content);
                $originalContent = $article->content;

                // Determine actual mode if 'auto' is specified
                if ($mode === 'auto') {
                    $mode = empty($article->content) ? 'prepend' : 'append';
                }

                // Apply content based on mode
                switch ($mode) {
                    case 'replace':
                        if ($searchText) {
                            // Replace specific text
                            if ($replaceAll) {
                                $article->content = str_replace($searchText, $content, $article->content);
                                $occurrences = substr_count($originalContent, $searchText);
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
                                return json_encode([
                                    'error' => 'Search text not found in article',
                                    'search_text' => $searchText
                                ]);
                            }
                        } else {
                            // No search text provided, append instead
                            $article->content = $article->content . $content;
                            $mode = 'append'; // Update mode for accurate reporting
                        }
                        break;

                    case 'insert_at_marker':
                        if (!$positionMarker) {
                            return json_encode([
                                'error' => 'Position marker required for insert_at_marker mode'
                            ]);
                        }

                        // Try to find a logical position based on the marker
                        $inserted = false;
                        $lowerContent = strtolower($article->content);
                        $lowerMarker = strtolower($positionMarker);

                        // Common position patterns
                        $patterns = [
                            '/after\s+(the\s+)?(.+)/i' => function ($matches) use (&$article, $content, $lowerContent) {
                                $searchPhrase = $matches[2];
                                $pos = stripos($article->content, $searchPhrase);
                                if ($pos !== false) {
                                    $endPos = $pos + strlen($searchPhrase);
                                    // Find the end of the sentence/paragraph
                                    $nextPeriod = strpos($article->content, '.', $endPos);
                                    $nextNewline = strpos($article->content, "\n", $endPos);

                                    if ($nextPeriod !== false || $nextNewline !== false) {
                                        $insertPos = min(
                                            $nextPeriod !== false ? $nextPeriod + 1 : PHP_INT_MAX,
                                            $nextNewline !== false ? $nextNewline : PHP_INT_MAX
                                        );
                                    } else {
                                        $insertPos = strlen($article->content);
                                    }

                                    $article->content = substr($article->content, 0, $insertPos) .
                                        ' ' . $content .
                                        substr($article->content, $insertPos);
                                    return true;
                                }
                                return false;
                            },
                            '/before\s+(the\s+)?(.+)/i' => function ($matches) use (&$article, $content) {
                                $searchPhrase = $matches[2];
                                $pos = stripos($article->content, $searchPhrase);
                                if ($pos !== false) {
                                    $article->content = substr($article->content, 0, $pos) .
                                        $content . ' ' .
                                        substr($article->content, $pos);
                                    return true;
                                }
                                return false;
                            }
                        ];

                        foreach ($patterns as $pattern => $handler) {
                            if (preg_match($pattern, $positionMarker, $matches)) {
                                $inserted = $handler($matches);
                                if ($inserted) break;
                            }
                        }

                        // If no pattern matched, try to find the marker text directly
                        if (!$inserted) {
                            $pos = stripos($article->content, $positionMarker);
                            if ($pos !== false) {
                                $endPos = $pos + strlen($positionMarker);
                                $article->content = substr($article->content, 0, $endPos) .
                                    ' ' . $content .
                                    substr($article->content, $endPos);
                                $inserted = true;
                            }
                        }

                        if (!$inserted) {
                            return json_encode([
                                'error' => 'Could not find position marker in article',
                                'position_marker' => $positionMarker
                            ]);
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

                Log::info('OpenAI Service: Article content updated', [
                    'article_id' => $articleId,
                    'mode' => $mode,
                    'original_length' => $originalLength,
                    'new_length' => $newLength,
                    'content_added' => strlen($content),
                    'words_added' => $addedWords
                ]);

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

    // Add this helper method to the OpenAIService class:
    protected function getEditSuccessMessage($mode, $searchText = null, $positionMarker = null)
    {
        switch ($mode) {
            case 'replace':
                return $searchText ?
                    "Replaced text successfully" :
                    "Content appended successfully (no search text provided for replacement)";
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

            case 'edit_article_content':
                $article = Article::find($arguments['article_id']);
                $title = $article ? $article->title : 'Unknown';
                $mode = $arguments['mode'] ?? 'replace';
                $wordCount = str_word_count($arguments['content']);

                if ($mode === 'replace') {
                    return "Replacing content of article \"{$title}\" ({$wordCount} words)...";
                } else {
                    $action = $mode === 'append' ? 'Appending' : 'Prepending';
                    return "{$action} {$wordCount} words to article \"{$title}\"...";
                }

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
            'model' => 'o4-mini-2025-04-16',
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
