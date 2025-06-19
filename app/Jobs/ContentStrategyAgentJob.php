<?php

namespace App\Jobs;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use App\Models\AgentMessage;
use App\Agents\AgentPrompts;

/**
 * Handles execution of the Content Strategy agent in its own job.
 *
 * The job logs a user request and system prompt, calls the LLM, stores the
 * assistant's response, and can be cancelled when the batch is stopped.
 */
class ContentStrategyAgentJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 30;

    /**
     * @param string $sessionId    The associated session identifier
     * @param string $strategyTask  Prompt passed to the content strategy agent
     */
    public function __construct(public string $sessionId, public string $strategyTask) {}

    /**
     * Run the content strategy agent and store its response.
     */
    public function handle(): void
    {
        // Exit early if the user cancelled the batch
        if ($this->batch()?->cancelled()) {
            return;
        }

        $agentName = 'Content Strategy Agent';

        // Store messages to database
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'system',
            'content' => AgentPrompts::$contentStrategySystem,
        ]);
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'user',
            'content' => $this->strategyTask,
        ]);

        // Prepare messages for API call (supported values for role are 'assistant', 'system', 'developer', and 'user')
        $messages = [
            ['role' => 'system', 'content' => AgentPrompts::$contentStrategySystem],
            ['role' => 'user', 'content' => $this->strategyTask],
        ];

        $agentResponse = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => $messages,
            'tools' => [['type' => 'web_search']], // TODO: I don't know if this working or when it is being used
            'temperature' => 0.7,
        ]);

        $items = $agentResponse->toArray()['output'] ?? [];
        Log::info('Content Strategy Agent - Items: ' . json_encode($items));

        // Model produced a direct message
        $content = isset($items[0]['content'][0]['text']) ? $items[0]['content'][0]['text'] : 'Content strategy agent did not return text';

        // Add the sub-agent's answer as assistant result back to Manager's conversation
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'assistant', // or 'sub-agent', 'agent' or 'assistant'
            'content' => $content,
        ]);
    }
}
