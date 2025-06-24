<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use App\Tools\ToolRegistry;
use App\Services\ConversationContextManager;

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
     * Context manager for building prompts and history
     */
    private ConversationContextManager $contextManager;

    /**
     * Registry of available tool handlers
     */
    private ToolRegistry $toolRegistry;

    public function __construct()
    {
        $this->toolRegistry = new ToolRegistry();
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
        $this->contextManager = new ConversationContextManager($conversation);

        if (isset($context['completed_function'])) {
            $this->contextManager->addFunctionResult(
                $context['function_name'],
                $context['function_arguments'],
                $context['function_response'],
                $context['additional_data'] ?? null
            );
        }

        $messages = $this->prepareMessages($message);
        Log::info('OpenAI sendMessage', ['messages' => json_encode($messages)]);

        try {
            // Call OpenAI with function calling enabled
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',                    // Use GPT-4 Omni model
                'messages' => $messages,                // Conversation context
                'functions' => $this->toolRegistry->getAllDefinitions(), // Available tools
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
     * @param string $message The current user message
     * @return array Formatted messages array for OpenAI API
     */
    private function prepareMessages(string $message): array
    {
        $messages = [];

        $messages[] = [
            'role' => 'system',
            'content' => $this->contextManager->buildSystemPrompt(),
        ];

        $history = $this->contextManager->buildConversationHistory();
        $messages = array_merge($messages, $history);

        // Add the current user message
        $messages[] = [
            'role' => 'user',
            'content' => $message ?: ' ', // Ensure content is never null
        ];

        return $messages;
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

        $handler = $this->toolRegistry->getHandler($functionName);

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
