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
 * Handles execution of the Citation Agent.
 *
 * The agent logs a user request and system prompt, calls the LLM, stores the
 * assistant's response, and can be cancelled when the batch is stopped.
 */
class CitationAgent implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 30;

    private static string $systemPrompt = <<<TXT
You are a citation agent, expert in citation.
When given a citation task you will locate every claim in the text and provide a citation for each claim in the form of a bibliography.
TXT;

    /**
     * @param string $sessionId  The associated session identifier
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
        $agentName = 'Citation Agent';

        // Exit early if the user cancelled the batch
        if ($this->batch()?->cancelled()) {
            return;
        }

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
            'content' => $this->task,
        ]);

        // Prepare messages for API call (supported values for role are 'assistant', 'system', 'developer', and 'user')
        $messages = [
            ['role' => 'system', 'content' => self::$systemPrompt],
            ['role' => 'user', 'content' => $this->task],
        ];

        $agentResponse = OpenAI::responses()->create([
            'model' => 'gpt-4.1-mini-2025-04-14',
            'input' => $messages,
            'temperature' => 0.7,
            // 'max_tokens' => 1000,
        ]);

        $items = $agentResponse->toArray()['output'] ?? [];
        Log::info('Citation Agent - Items: ' . json_encode($items));

        // Model produced a direct message (potential final answer)
        $content = isset($items[0]['content'][0]['text']) ? $items[0]['content'][0]['text'] : 'Citation Agent did not return text';

        // Add the sub-agent's answer as assistant result back to Manager's conversation
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'sub-agent', // or 'sub-agent', 'agent' or 'assistant'
            'content' => $content,
        ]);
    }
}
