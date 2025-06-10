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
 * Coordinator job that manages the overall multi-agent workflow.
 *
 * Each execution represents a single step of the manager agent. The manager
 * may schedule new sub-agent jobs and re-dispatch itself for the next step
 * using Laravel's batch feature.
 */
class ManagerAgentStepJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 30;

    /**
     * @param string      $sessionId   Session identifier for message grouping
     * @param string|null $userRequest Optional prompt used on the first step
     */
    public function __construct(public string $sessionId, public ?string $userRequest = null) {}

    /**
     * Execute one step of the manager agent.
     *
     * The manager may respond with messages or function calls to invoke other
     * agents. Detected function calls are queued as new jobs within the same
     * batch. If the batch is cancelled, the step exits early.
     */
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
        Log::info('Manager Agent - API Response: ' . json_encode($apiResponse));

        $items = $apiResponse->toArray()['output'] ?? [];
        Log::info('Manager Agent - Items: ' . json_encode($items));

        $shouldContinue = false;
        foreach ($items as $item) {
            if ($item['type'] === 'message') {
                $content = $item['content'] ?? '';
                AgentMessage::create([
                    'session_id' => $this->sessionId,
                    'agent_name' => $managerName,
                    'role' => 'assistant',
                    'content' => $content,
                ]);
            }
            if ($item['type'] === 'function_call') {
                $funcName = $item['name'];
                $funcArgs = json_decode($item['arguments'], true);
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

    /**
     * Rebuild the conversation history for the given agent.
     *
     * Messages are pulled from the database and formatted for the API
     * request so the agent can maintain context between steps.
     */
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
