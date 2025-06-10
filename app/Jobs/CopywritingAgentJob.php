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

class CopywritingAgentJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 30;

    public function __construct(public string $sessionId, public string $copywritingTask)
    {
    }

    public function handle(): void
    {
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
            'tools' => [[ 'type' => 'web_search' ]],
            'temperature' => 0.7,
        ]);

        $output = $apiResponse->toArray()['output'] ?? $apiResponse->toArray()['output_text'] ?? '';
        $copywritingAnswer = is_string($output) ? $output : '[Copywriting agent did not return text]';
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'assistant',
            'content' => $copywritingAnswer,
        ]);
    }
}
