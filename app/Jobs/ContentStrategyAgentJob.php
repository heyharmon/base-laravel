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

class ContentStrategyAgentJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 30;

    public function __construct(public string $sessionId, public string $strategyTask)
    {
    }

    public function handle(): void
    {
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
            'tools' => [[ 'type' => 'web_search' ]],
            'temperature' => 0.7,
        ]);

        $output = $apiResponse->toArray()['output'] ?? $apiResponse->toArray()['output_text'] ?? '';
        $contentStrategyAnswer = is_string($output) ? $output : '[Content strategy agent did not return text]';
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'assistant',
            'content' => $contentStrategyAnswer,
        ]);
    }
}
