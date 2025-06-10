<?php

namespace App\Jobs;

use App\Agents\AgentPrompts;
use App\Models\AgentMessage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;

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
        $messages = [
            ['role' => 'system', 'content' => AgentPrompts::$contentStrategySystem],
            ['role' => 'user', 'content' => $this->strategyTask],
        ];

        $apiResponse = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => $messages,
            'tools' => [['type' => 'web_search']],
            'temperature' => 0.7,
        ]);

        $outputArr = $apiResponse->toArray()['output'] ?? [];
        $contentStrategyAnswer = '[Content strategy agent did not return text]';
        if (is_array($outputArr) && isset($outputArr[0]['content'][0]['text'])) {
            $contentStrategyAnswer = $outputArr[0]['content'][0]['text'];
        }
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'assistant',
            'content' => $contentStrategyAnswer,
        ]);
    }
}
