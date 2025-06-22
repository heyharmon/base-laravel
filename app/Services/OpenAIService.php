<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use App\Services\AI\FunctionRegistry;

/**
 * OpenAI Service - Core AI Research and Writing Agent
 * 
 * This service orchestrates AI-powered research and article writing workflows.
 * It integrates with OpenAI's GPT-4 model using function calling to enable:
 * - Web research and content fetching
 * - Structured article creation and management
 * - Plan-driven task execution
 * - Asynchronous job processing for long-running tasks
 * 
 * Architecture:
 * 1. Receives user messages through sendMessage()
 * 2. Builds context from conversation history and recent function results
 * 3. Calls OpenAI with available functions (tools)
 * 4. Dispatches background jobs for function execution
 * 5. Continues conversation when jobs complete
 */
class OpenAIService
{
    /**
     * Registry of available function handlers
     */
    private FunctionRegistry $functionRegistry;

    public function __construct()
    {
        $this->functionRegistry = new FunctionRegistry();
    }

    /**
     * Main entry point for sending messages to the AI agent
     * 
     * This method orchestrates the entire AI interaction flow:
     * 1. Prepares message context from conversation history
     * 2. Sends request to OpenAI with function calling enabled
     * 3. Processes the response and handles any function calls
     * 4. Returns the created chat record and follow-up information
     * 
     * @param Conversation $conversation The conversation context
     * @param string $message The user's message
     * @param array $context Additional context (e.g., 'job_completed' from background jobs)
     * @return array ['chat' => Chat, 'needsFollowUp' => bool]
     */
    public function sendMessage(Conversation $conversation, string $message, array $context = []): array
    {
        // Build the message array with system prompt, history, and current message
        $messages = $this->prepareMessages($conversation, $message, $context);

        try {
            // Call OpenAI with function calling enabled
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',                    // Use GPT-4 Omni model
                'messages' => $messages,                // Conversation context
                'functions' => $this->functionRegistry->getAllDefinitions(), // Available tools
                'function_call' => 'auto',              // Let AI decide when to use functions
                'temperature' => 0.7,                   // Balanced creativity/consistency
            ]);

            // Process the response, save to database, handle function calls
            return $this->processResponse($conversation, $response);
        } catch (\Exception $e) {
            Log::error('OpenAI API Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Prepares the message array for OpenAI API call
     * 
     * Builds a structured conversation context including:
     * 1. System prompt with capabilities and recent function results
     * 2. Recent conversation history (last 10 messages)
     * 3. Current user message
     * 
     * @param Conversation $conversation The conversation to build context from
     * @param string $message The current user message
     * @param array $context Additional context flags (e.g., job completion signals)
     * @return array Formatted messages array for OpenAI API
     */
    private function prepareMessages(Conversation $conversation, string $message, array $context): array
    {
        $messages = [];

        // Start with system prompt that defines the AI's role and capabilities
        $messages[] = [
            'role' => 'system',
            'content' => $this->getSystemPrompt($conversation, $context),
        ];

        // Add recent conversation history (last 10 user/assistant messages)
        // This provides context for the AI's response
        $recentChats = $conversation->chats()
            ->whereIn('role', ['user', 'assistant'])
            ->latest()
            ->limit(10)
            ->get()
            ->reverse(); // Reverse to get chronological order

        foreach ($recentChats as $chat) {
            $messages[] = [
                'role' => $chat->role,
                'content' => $chat->content,
            ];
        }

        // Add the current user message
        $messages[] = [
            'role' => 'user',
            'content' => $message ?: ' ', // Ensure content is never null
        ];

        return $messages;
    }

    /**
     * Generates the system prompt that defines the AI's role and capabilities
     * 
     * This prompt is crucial - it defines:
     * 1. The AI's identity as a research and writing agent
     * 2. Current conversation plan and progress
     * 3. Recent function call results (critical for seeing previous work)
     * 4. Guidelines and workflow instructions
     * 
     * The dynamic inclusion of recent results solves the "invisible function results" problem
     * by explicitly showing the AI what was accomplished in previous function calls.
     * 
     * @param Conversation $conversation The conversation context
     * @param array $context Context flags indicating when to include function results
     * @return string The complete system prompt
     */
    private function getSystemPrompt(Conversation $conversation, array $context): string
    {
        // Include the current research/writing plan
        $plan = $conversation->agent_plan ? json_encode($conversation->agent_plan) : 'No plan yet';

        // Build recent function results section
        // This is CRITICAL for function call visibility - without this, the AI can't see
        // the results of web searches, webpage fetches, or article creations
        $recentResults = '';
        if (isset($context['context']) && $context['context'] === 'job_completed') {
            // Get the last 3 completed function calls
            $recentCompletedChats = $conversation->chats()
                ->whereNotNull('function_response')
                ->where('job_status', 'completed')
                ->latest()
                ->limit(3)
                ->get();

            if ($recentCompletedChats->isNotEmpty()) {
                $recentResults = "\n\nRecent function call results:\n";
                foreach ($recentCompletedChats as $chat) {
                    // Show web search results with previews
                    if ($chat->function_name === 'web_search' && $chat->web_search_results) {
                        $recentResults .= "- Web search for '{$chat->function_arguments['query']}' returned:\n";
                        foreach (array_slice($chat->web_search_results, 0, 3) as $result) {
                            $recentResults .= "  * {$result['title']} ({$result['url']})\n";
                            if (!empty($result['content'])) {
                                $recentResults .= "    Content preview: " . substr($result['content'], 0, 200) . "...\n";
                            }
                        }
                    } 
                    // Show fetched webpage content
                    elseif ($chat->function_name === 'fetch_webpage' && $chat->web_search_results) {
                        $recentResults .= "- Fetched webpage content:\n";
                        $recentResults .= "  Content: " . substr($chat->web_search_results, 0, 1000) . "...\n";
                    } 
                    // Show created articles with their IDs (critical for subsequent section writing)
                    elseif ($chat->function_name === 'create_article' && $chat->function_response) {
                        $response = $chat->function_response;
                        $recentResults .= "- Created article '{$response['title']}' with ID {$response['article_id']}\n";
                        $recentResults .= "  use this article id ({$response['article_id']}) for writing sections\n";
                    } 
                    // Show article section updates
                    elseif ($chat->function_name === 'write_article_section' && $chat->function_response) {
                        $response = $chat->function_response;
                        $recentResults .= "- Updated article section '{$response['section']}' for article ID {$response['article_id']}\n";
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

    /**
     * Processes OpenAI API response and handles function calls
     * 
     * This method:
     * 1. Extracts the AI's response message and usage statistics
     * 2. Creates a Chat record to persist the conversation
     * 3. Calculates and tracks token costs
     * 4. Dispatches background jobs for any function calls
     * 5. Returns the chat record and follow-up information
     * 
     * @param Conversation $conversation The conversation context
     * @param mixed $response OpenAI API response object
     * @return array ['chat' => Chat, 'needsFollowUp' => bool]
     */
    private function processResponse(Conversation $conversation, $response): array
    {
        $message = $response->choices[0]->message;
        $usage = $response->usage;

        // Create chat record to persist this interaction
        $chat = $conversation->chats()->create([
            'role' => 'assistant',
            'content' => $message->content ?? '', // AI's text response
            'function_name' => $message->functionCall->name ?? null, // Function to call (if any)
            'function_arguments' => $message->functionCall->arguments ?? null, // Function parameters
            'prompt_tokens' => $usage->promptTokens, // Input tokens used
            'completion_tokens' => $usage->completionTokens, // Output tokens generated
        ]);

        // Calculate and track costs for this interaction
        $chat->calculateCost();
        $conversation->addTokenUsage(
            $usage->promptTokens + $usage->completionTokens,
            $chat->cost
        );

        // If the AI wants to call a function, dispatch the appropriate job
        if (isset($message->functionCall)) {
            $this->handleFunctionCall($conversation, $chat, $message->functionCall);
        }

        return [
            'chat' => $chat,
            'needsFollowUp' => isset($message->functionCall), // True if a job was dispatched
        ];
    }

    /**
     * Dispatches background jobs for AI function calls
     * 
     * When the AI decides to use a tool (function), this method:
     * 1. Extracts and validates the function parameters
     * 2. Updates the chat record with function details
     * 3. Dispatches the appropriate job to handle the function execution
     * 4. Provides error logging for missing or invalid parameters
     * 
     * The jobs run asynchronously and use ContinueConversationJob to resume
     * the conversation when they complete.
     * 
     * @param Conversation $conversation The conversation context
     * @param Chat $chat The chat record for this function call
     * @param mixed $functionCall OpenAI function call object
     */
    private function handleFunctionCall(Conversation $conversation, Chat $chat, $functionCall): void
    {
        $functionName = $functionCall->name;
        $arguments = json_decode($functionCall->arguments, true);

        // Update the chat record with the parsed function details
        $chat->update([
            'function_name' => $functionName,
            'function_arguments' => $arguments,
        ]);

        $handler = $this->functionRegistry->getHandler($functionName);

        if (!$handler) {
            Log::error('Unknown function called', ['function' => $functionName]);
            return;
        }

        if (!$handler->validate($arguments)) {
            Log::warning($handler->getValidationError(), ['arguments' => $arguments]);
            return;
        }

        $handler->handle($conversation, $chat, $arguments);
    }

}
