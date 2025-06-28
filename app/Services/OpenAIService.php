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
            'name' => 'append_content',
            'description' => 'Add content to the end of an article. Use this for building articles in chunks of ~200 words.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'article_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the article to edit'
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Content to add to the end of the article. Always format content in vanilla HTML using tags h1-h6, p, strong, em, br, ul, li and a.'
                    ]
                ],
                'required' => ['article_id', 'content']
            ]
        ],
        [
            'type' => 'function',
            'name' => 'prepend_content',
            'description' => 'Add content to the beginning of an article',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'article_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the article to edit'
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Content to add to the beginning of the article. Always format content in vanilla HTML using tags h1-h6, p, strong, em, br, ul, li and a.'
                    ]
                ],
                'required' => ['article_id', 'content']
            ]
        ],
        [
            'type' => 'function',
            'name' => 'replace_content',
            'description' => 'Replace specific text in an article with new content',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'article_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the article to edit'
                    ],
                    'search_text' => [
                        'type' => 'string',
                        'description' => 'Exact text to find and replace'
                    ],
                    'replacement_text' => [
                        'type' => 'string',
                        'description' => 'New text to replace the found text with. Always format content in vanilla HTML using tags h1-h6, p, strong, em, br, ul, li and a.'
                    ],
                    'replace_all' => [
                        'type' => 'boolean',
                        'description' => 'Whether to replace all occurrences or just the first one',
                        'default' => false
                    ]
                ],
                'required' => ['article_id', 'search_text', 'replacement_text']
            ]
        ],
        [
            'type' => 'function',
            'name' => 'insert_content',
            'description' => 'Insert new content after a specific piece of text in an article',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'article_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the article to edit'
                    ],
                    'after_text' => [
                        'type' => 'string',
                        'description' => 'Exact text to find - new content will be inserted after this text'
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Content to insert after the found text'
                    ]
                ],
                'required' => ['article_id', 'after_text', 'content']
            ]
        ],
        ['type' => 'web_search']
    ];

    public function processMessage(Conversation $conversation, string $userMessage, array $context = [])
    {
        Log::info('OpenAI Service: Processing message', [
            'conversation_id' => $conversation->id,
            'message_length' => strlen($userMessage),
            'context' => $context
        ]);

        // Save user message
        $conversation->chats()->create([
            'type' => 'user',
            'content' => $userMessage
        ]);

        // Build and send request
        $request = $this->buildRequest($conversation, $userMessage, $context);
        $response = OpenAI::responses()->create($request);

        Log::info('OpenAI Service: Received response', [
            'response' => $response,
        ]);

        // Process the response
        return $this->processResponse($conversation, $response);
    }

    public function processMessageAsync(Conversation $conversation, string $userMessage, array $context = [])
    {
        Log::info('OpenAI Service: Processing message asynchronously', [
            'conversation_id' => $conversation->id,
            'message_length' => strlen($userMessage),
            'context' => $context
        ]);

        // Process in background
        dispatch(function () use ($conversation, $userMessage, $context) {
            try {
                // Build and send request
                $request = $this->buildRequest($conversation, $userMessage, $context);
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

    protected function buildRequest(Conversation $conversation, string $userMessage, array $context = []): array
    {
        // Instructions are a system message that are swappable, not carried over to next response.
        $instructions = $this->composeSystemPrompt($conversation, $context);

        // Previous response is maintains the conversation state in OpenAI.
        $previous = $conversation->openai_response_id;

        return array_filter([
            'model' => 'gpt-4.1',
            'instructions' => $instructions,
            'input' => $userMessage,
            'parallel_tool_calls' => true,
            'previous_response_id' => $previous,
            'store' => true,
            'tools' => $this->tools,
            'tool_choice' => 'auto',
            // 'reasoning' => [
            //     'effort' => 'medium', // low, medium, high, defaults to medium. o-series models only
            //     'summary' => 'auto' // null, auto, concise, detailed
            // ],
        ]);
    }

    protected function composeSystemPrompt(Conversation $conversation, array $context = []): string
    {
        $systemMessage = "You are a helpful assistant with access to articles in a database. You work both independently and collaboratively with a USER to write articles and complete related tasks. A task may require creating a new article, writing or editing an article, researching a topic, or simple answering a question. \n";
        $systemMessage .= "The USER will send you requests. We will attach context about their current state, such as which article they are viewing. This information may or may not be relevant to the USER's request, it is up to you to decide. \n";
        $systemMessage .= "Before calling each tool, first explain why you are calling it. Some tools run asynchronously, so you may not see their output immediately. After completing a task, do not over-explain what you did, provide a short synopsis. Here are examples of good tool call behavior: \n";
        $systemMessage .= "USER: How many articles do we have? ASSISTANT: Let me check. [Call list_articles] TOOL: [tool message] ASSISTANT: You have 12 articles. USER: Create a new article about checking accounts. ASSISTANT: Let me create the article. [Call create_article with title=\"Checking Accounts\"] USER: Research the top checking account in Utah and start an article about it. ASSISTANT: Let me do some research. [Call web_search with query=\"top checking account in utah\"] [Call web_search with query=\"lowest rate checking account in utah\"] ASSISTANT: Digging deeper into the topic. [Call web_search with query=\"free checking account in utah\"] ASSISTANT: I found some great information. Now let me draft the article. [Call edit_article_title with article_id=\"1\" and title=\"Top Checking Accounts in Utah\"] TOOL: [tool message] [Call append_content with article_id=\"1\" and content=\"...approximately 200 words...\" ] TOOL: [tool message] [Call append_content with article_id=\"1\" and content=\"...approximately 200 words...\" ] TOOL: [tool message] ASSISTANT: I have finished writing the article. It is a draft that covers... \n";
        $systemMessage .= "When writing article content, use the append_content function to write in chunks of approximately 200 words at a time. Use multiple append_content calls to write more than approximately 200 words.";
        $systemMessage .= "This provides faster feedback to the USER. \n";
        $systemMessage .= "Use edit_article_title to change titles, append_content to add content to the end of articles, prepend_content to add content to the beginning, insert_content to add content after specific text, and replace_content to replace specific text. \n";

        // Use the passed context
        if (!empty($context)) {
            $systemMessage .= "\n\nCurrent frontend context:\n";
            foreach ($context as $key => $value) {
                $systemMessage .= "- {$key}: {$value}\n";
            }
        }

        return $systemMessage;
    }

    protected function handleAssistantMessage(Conversation $conversation, $item): void
    {
        $chatData = [
            'type' => 'assistant',
            'content' => $item->content[0]->text ?? '',
        ];

        // Check for annotations in the response
        if (isset($item->content[0]->annotations) && !empty($item->content[0]->annotations)) {
            Log::info('OpenAI Service: Assistant message with annotations', [
                'annotations' => $item->content[0]->annotations,
            ]);

            $chatData['annotations'] = $item->content[0]->annotations;
        }

        $conversation->chats()->create($chatData);
    }

    protected function handleReasoningMessage(Conversation $conversation, $item): void
    {
        // Extract reasoning content - adjust based on the actual structure of $item
        // $reasoningContent = $item->content ?? $item->text ?? $item->reasoning ?? '';

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
                'result' => json_decode($result, true),
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
                    'model' => 'gpt-4.1',
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

            case 'append_content':
                $article = Article::find($arguments['article_id']);
                if (!$article) {
                    return json_encode(['error' => 'Article not found']);
                }

                $previousWordCount = str_word_count($article->content);
                $previousLength = strlen($article->content);

                $article->content .= $arguments['content'];
                $article->save();

                $newWordCount = str_word_count($article->content);
                $newLength = strlen($article->content);
                $addedWords = str_word_count($arguments['content']);

                return json_encode([
                    'success' => true,
                    'message' => 'Content appended successfully',
                    'article_id' => $article->id,
                    'progress' => [
                        'total_words' => $newWordCount,
                        'total_length' => $newLength,
                        'previous_words' => $previousWordCount,
                        'previous_length' => $previousLength,
                        'chunk_words' => $addedWords,
                        'chunk_length' => strlen($arguments['content'])
                    ]
                ]);

            case 'prepend_content':
                $article = Article::find($arguments['article_id']);
                if (!$article) {
                    return json_encode(['error' => 'Article not found']);
                }

                $previousWordCount = str_word_count($article->content);
                $previousLength = strlen($article->content);

                $article->content = $arguments['content'] . $article->content;
                $article->save();

                $newWordCount = str_word_count($article->content);
                $newLength = strlen($article->content);
                $addedWords = str_word_count($arguments['content']);

                return json_encode([
                    'success' => true,
                    'message' => 'Content prepended successfully',
                    'article_id' => $article->id,
                    'progress' => [
                        'total_words' => $newWordCount,
                        'total_length' => $newLength,
                        'previous_words' => $previousWordCount,
                        'previous_length' => $previousLength,
                        'chunk_words' => $addedWords,
                        'chunk_length' => strlen($arguments['content'])
                    ]
                ]);

            case 'replace_content':
                $article = Article::find($arguments['article_id']);
                if (!$article) {
                    return json_encode(['error' => 'Article not found']);
                }

                $searchText = $arguments['search_text'];
                $replacementText = $arguments['replacement_text'];
                $replaceAll = $arguments['replace_all'] ?? false;

                // Check if search text exists
                if (strpos($article->content, $searchText) === false) {
                    return json_encode(['error' => 'Search text not found in article']);
                }

                $previousWordCount = str_word_count($article->content);
                $previousLength = strlen($article->content);

                if ($replaceAll) {
                    $occurrences = substr_count($article->content, $searchText);
                    $article->content = str_replace($searchText, $replacementText, $article->content);
                } else {
                    $pos = strpos($article->content, $searchText);
                    $article->content = substr_replace($article->content, $replacementText, $pos, strlen($searchText));
                    $occurrences = 1;
                }

                $article->save();

                $newWordCount = str_word_count($article->content);
                $newLength = strlen($article->content);

                return json_encode([
                    'success' => true,
                    'message' => 'Content replaced successfully',
                    'article_id' => $article->id,
                    'replacements' => $occurrences,
                    'progress' => [
                        'total_words' => $newWordCount,
                        'total_length' => $newLength,
                        'previous_words' => $previousWordCount,
                        'previous_length' => $previousLength
                    ]
                ]);

            case 'insert_content':
                $article = Article::find($arguments['article_id']);
                if (!$article) {
                    return json_encode(['error' => 'Article not found']);
                }

                $afterText = $arguments['after_text'];
                $content = $arguments['content'];

                // Check if the text to insert after exists
                $pos = strpos($article->content, $afterText);
                if ($pos === false) {
                    return json_encode(['error' => 'Target text not found in article']);
                }

                $previousWordCount = str_word_count($article->content);
                $previousLength = strlen($article->content);

                // Insert content after the found text
                $insertPos = $pos + strlen($afterText);
                $article->content = substr($article->content, 0, $insertPos) . $content . substr($article->content, $insertPos);
                $article->save();

                $newWordCount = str_word_count($article->content);
                $newLength = strlen($article->content);
                $addedWords = str_word_count($content);

                return json_encode([
                    'success' => true,
                    'message' => 'Content inserted successfully',
                    'article_id' => $article->id,
                    'progress' => [
                        'total_words' => $newWordCount,
                        'total_length' => $newLength,
                        'previous_words' => $previousWordCount,
                        'previous_length' => $previousLength,
                        'chunk_words' => $addedWords,
                        'chunk_length' => strlen($content)
                    ]
                ]);

            case 'web_search':
                return; // Handled by OpenAI

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

            case 'edit_article_title':
                $article = Article::find($arguments['article_id']);
                $title = $article ? $article->title : 'Unknown';
                return "Editing title of article: \"{$title}\"...";

            case 'append_content':
                $article = Article::find($arguments['article_id']);
                $title = $article ? $article->title : 'Unknown';
                $wordCount = str_word_count($arguments['content']);
                return "Appending {$wordCount} words to article \"{$title}\"...";

            case 'prepend_content':
                $article = Article::find($arguments['article_id']);
                $title = $article ? $article->title : 'Unknown';
                $wordCount = str_word_count($arguments['content']);
                return "Prepending {$wordCount} words to article \"{$title}\"...";

            case 'replace_content':
                $article = Article::find($arguments['article_id']);
                $title = $article ? $article->title : 'Unknown';
                $searchPreview = strlen($arguments['search_text']) > 30 ?
                    substr($arguments['search_text'], 0, 30) . '...' :
                    $arguments['search_text'];
                $wordCount = str_word_count($arguments['replacement_text']);
                return "Replacing \"{$searchPreview}\" with {$wordCount} words in article \"{$title}\"...";

            case 'insert_content':
                $article = Article::find($arguments['article_id']);
                $title = $article ? $article->title : 'Unknown';
                $afterTextPreview = strlen($arguments['after_text']) > 30 ?
                    substr($arguments['after_text'], 0, 30) . '...' :
                    $arguments['after_text'];
                $wordCount = str_word_count($arguments['content']);
                return "Inserting {$wordCount} words after \"{$afterTextPreview}\" in article \"{$title}\"...";

            case 'web_search':
                return "Searching for: \"{$arguments['query']}\"";

            default:
                return 'Executing tool...';
        }
    }
}
