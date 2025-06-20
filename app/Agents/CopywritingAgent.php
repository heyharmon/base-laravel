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
 * Executes the Copywriting Agent.
 *
 * The agent logs a user request and system prompt, calls the LLM, stores the
 * assistant's response, and can be cancelled when the batch is stopped.
 */
class CopywritingAgent implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 300;

    private static string $systemPrompt = <<<TXT
You are a copywriter agent, expert in writing.
When given a copywriting task, you will write high-quality, thoughtful copy for the request.
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
        $agentName = 'Copywriting Agent';

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
        ]);

        $items = $agentResponse->toArray()['output'] ?? [];
        Log::info('Copywriting Agent - Items: ' . json_encode($items));

        // Model produced a direct message (potential final answer)
        $content = isset($items[0]['content'][0]['text']) ? $items[0]['content'][0]['text'] : 'Copywriting Agent did not return text';

        // Add the sub-agent's answer as assistant result back to Manager's conversation
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'sub-agent', // or 'sub-agent', 'agent' or 'assistant'
            'content' => $content,
        ]);
    }
}
