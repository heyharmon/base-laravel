<?php

namespace App\Agents;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use App\Models\AgentMessage;

/**
 * Executes the Search Agent.
 *
 * The agent logs a user request and system prompt, calls the LLM, stores the
 * assistant's response, and can be cancelled when the batch is stopped.
 */
class SearchAgent implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 30;

    private static string $systemPrompt = <<<TXT
You are a search agent, expert in searching the web for information.
When given a search task you will search the web for information and return a summary of your findings.
TXT;

    /**
     * @param string $sessionId  The session identifier
     * @param string $task  Prompt passed to the agent
     */
    public function __construct(
        public string $sessionId,
        public string $task
    ) {}

    /**
     * Run the agent and store its response.
     */
    public function handle(): void
    {
        // Exit early if the user cancelled the batch
        if ($this->batch()?->cancelled()) {
            return;
        }

        $agentName = 'Search Agent';

        // Store messages to database
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => null,
            'role' => 'system',
            'content' => self::$systemPrompt,
        ]);
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => null,
            'role' => 'user',
            'content' => 'Search for: ' . $this->task,
        ]);

        // Prepare messages for API call (supported values for role are 'assistant', 'system', 'developer', and 'user')
        $messages = [
            ['role' => 'system', 'content' => self::$systemPrompt],
            ['role' => 'user', 'content' => $this->task],
        ];

        $agentResponse = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => $messages,
            'tools' => [['type' => 'web_search']], // TODO: I don't know if this working or when it is being used
            'temperature' => 0.7,
        ]);
        // Log::info('Search Agent - Full Response: ' . json_encode($agentResponse));

        $items = $agentResponse->toArray()['output'] ?? [];
        Log::info('Search Agent - Items: ' . json_encode($items));

        // Loop over the items in the agents' response
        // If the item is a message, store it
        foreach ($items as $item) {
            if ($item['type'] === 'message') {
                // Model produced a direct message (potential final answer)
                $content = isset($item['content'][0]['text']) ? $item['content'][0]['text'] : 'Search Agent did not return text';

                // Store the message
                AgentMessage::create([
                    'session_id' => $this->sessionId,
                    'agent_name' => $agentName,
                    'role' => 'sub-agent', // or 'sub-agent', 'agent' or 'assistant'
                    'content' => $content,
                ]);
            }
        }
    }
}
