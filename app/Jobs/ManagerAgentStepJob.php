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

class ManagerAgentStepJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 30;

    public function __construct(public string $sessionId, public ?string $userRequest = null)
    {
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $managerName = 'Manager';

        if ($this->userRequest !== null) {
            AgentMessage::create([
                'session_id' => $this->sessionId,
                'agent_name' => $managerName,
                'role' => 'system',
                'content' => AgentPrompts::$managerSystem,
            ]);
            AgentMessage::create([
                'session_id' => $this->sessionId,
                'agent_name' => $managerName,
                'role' => 'user',
                'content' => $this->userRequest,
            ]);
        }

        $tools = [
            ['type' => 'web_search'],
            [
                'type' => 'function',
                'name' => 'call_content_strategy_agent',
                'description' => 'Pass a prompt to the Content Strategy Agent',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'input' => ['type' => 'string'],
                    ],
                    'required' => ['input'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'call_copywriting_agent',
                'description' => 'Pass a prompt to the Copywriting Agent',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'input' => ['type' => 'string'],
                    ],
                    'required' => ['input'],
                ],
            ],
        ];

        $messages = $this->buildMessagesArray($managerName);
        $apiResponse = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => $messages,
            'tools' => $tools,
            'temperature' => 0.7,
        ]);

        $items = $apiResponse->toArray()['items'] ?? [];
        $shouldContinue = false;
        foreach ($items as $item) {
            if ($item['type'] === 'message') {
                $content = $item['message']['content'] ?? '';
                AgentMessage::create([
                    'session_id' => $this->sessionId,
                    'agent_name' => $managerName,
                    'role' => 'assistant',
                    'content' => $content,
                ]);
            }
            if ($item['type'] === 'function_call') {
                $funcName = $item['function_call']['name'];
                $funcArgs = $item['function_call']['arguments'] ?? [];
                AgentMessage::create([
                    'session_id' => $this->sessionId,
                    'agent_name' => $managerName,
                    'role' => 'function',
                    'function_name' => $funcName,
                    'function_args' => $funcArgs,
                ]);

                if ($funcName === 'call_content_strategy_agent') {
                    $strategyQuery = $funcArgs['input'] ?? '';
                    $this->batch()?->add(new ContentStrategyAgentJob($this->sessionId, $strategyQuery));
                }
                if ($funcName === 'call_copywriting_agent') {
                    $copywritingQuery = $funcArgs['input'] ?? '';
                    $this->batch()?->add(new CopywritingAgentJob($this->sessionId, $copywritingQuery));
                }
                $shouldContinue = true;
            }
        }

        if ($shouldContinue) {
            $this->batch()?->add(new ManagerAgentStepJob($this->sessionId));
        }
    }

    private function buildMessagesArray(string $agentName): array
    {
        $messages = [];
        $history = AgentMessage::where('session_id', $this->sessionId)
            ->where('agent_name', $agentName)
            ->orderBy('id')->get();
        foreach ($history as $msg) {
            if ($msg->role === 'assistant' && $msg->content) {
                $messages[] = ['role' => 'assistant', 'content' => $msg->content];
            } elseif ($msg->role === 'user') {
                $messages[] = ['role' => 'user', 'content' => $msg->content];
            } elseif ($msg->role === 'system') {
                $messages[] = ['role' => 'system', 'content' => $msg->content];
            }
        }
        return $messages;
    }
}
