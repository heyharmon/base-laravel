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
 * Executes the copywriting agent in its own queued job.
 *
 * Stores prompts and the resulting assistant output while respecting batch
 * cancellation.
 */
class CopywritingAgentJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 30;

    /**
     * @param string $sessionId      The session identifier
     * @param string $copywritingTask Prompt sent to the copywriting agent
     */
    public function __construct(public string $sessionId, public string $copywritingTask) {}

    /**
     * Invoke the copywriting agent and record the assistant message.
     */
    public function handle(): void
    {
        // Skip processing if the batch has been cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        $agentName = 'Copywriting Agent';
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'system',
            'content' => AgentPrompts::$copywritingSystem,
        ]);
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'user',
            'content' => $this->copywritingTask,
        ]);
        $messages = [
            ['role' => 'system', 'content' => AgentPrompts::$copywritingSystem],
            ['role' => 'user', 'content' => $this->copywritingTask],
        ];

        $apiResponse = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => $messages,
            'tools' => [['type' => 'web_search']],
            'temperature' => 0.7,
        ]);
        Log::info('Copywriter Agent - API Response: ' . json_encode($apiResponse));

        $outputArr = $apiResponse->toArray()['output'] ?? [];
        $copywritingAnswer = '[Copywriting agent did not return text]';
        if (is_array($outputArr) && isset($outputArr[0]['content'][0]['text'])) {
            $copywritingAnswer = $outputArr[0]['content'][0]['text'];
        }
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'assistant',
            'content' => $copywritingAnswer,
        ]);
    }
}
