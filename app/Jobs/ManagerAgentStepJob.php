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
     * @param string $sessionId   Session identifier for message grouping
     * @param string|null $userRequest The initial task the user wants the AI team to handle
     */
    public function __construct(
        public string $sessionId,
        public ?string $userRequest = null
    ) {}

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

        // 1. If user request, initialize conversation with system prompt and user request for Manager
        if ($this->userRequest) {
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

        // 2. Define available tools (sub-agents and optional webs search) for the Manager Agent
        $tools = [
            [
                'type' => 'web_search'
            ],
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

        // 3. Invoke Manager Agent
        // Assemble message hidtory for Manager agent
        $messages = $this->buildMessagesArray();

        // Call OpenAI Responses API for the Manager agents next response
        $agentResponse = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => $messages,
            'tools' => $tools,
            'temperature' => 0.7,
            // 'max_tokens' => 1000,
        ]);

        // The response may contain a message or a function call (or both)
        // (We assume the OpenAI PHP client returns a structured response; we convert to array for easier handleing)
        $items = $agentResponse->toArray()['output'] ?? [];
        Log::info('Manager Agent - Items: ' . json_encode($items));

        $shouldContinue = false;
        foreach ($items as $item) {
            if ($item['type'] === 'message') {
                // Model produced a direct message (potential final answer)
                $content = isset($items[0]['content'][0]['text']) ? $items[0]['content'][0]['text'] : '';

                // Store the message
                AgentMessage::create([
                    'session_id' => $this->sessionId,
                    'agent_name' => $managerName,
                    'role' => 'assistant',
                    'content' => $content,
                ]);

                // If we got a message with no function call, assume conversation might be done
                // (In compled flows, you might check if the Manager indicates completion explicitely.)
                $shouldContinue = false;
            }

            if ($item['type'] === 'function_call') {
                // Model wants to use a tool (calls a sub-agent)
                $funcName = $item['name'];
                $funcArgs = json_decode($item['arguments'], true);
                // Store the function call request
                AgentMessage::create([
                    'session_id' => $this->sessionId,
                    'agent_name' => $managerName,
                    'role' => 'function',
                    'function_name' => $funcName,
                    'function_args' => $funcArgs,
                ]);

                // Execute the appropriate sub-agent based on the function name
                if ($funcName === 'call_content_strategy_agent') {
                    $strategyQuery = $funcArgs['input'] ?? '';
                    $this->batch()?->add(new ContentStrategyAgentJob($this->sessionId, $strategyQuery));
                }
                if ($funcName === 'call_copywriting_agent') {
                    $copywritingQuery = $funcArgs['input'] ?? '';
                    $this->batch()?->add(new CopywritingAgentJob($this->sessionId, $copywritingQuery));
                }

                // After executing a tool, loop will continue and Manager will incorporate the new info in the next iteration.
                $shouldContinue = true;
            }
        } // end foreach item loop

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
    private function buildMessagesArray(): array
    {
        // Fetch all messages for this agent in current session
        $messages = [];
        $history = AgentMessage::where('session_id', $this->sessionId)
            ->orderBy('id')->get();

        // Rebuild each message in the conversation history for brevity
        // Supported values for role are 'assistant', 'system', 'developer', and 'user'
        foreach ($history as $msg) {
            if ($msg->role === 'function') {
                $messages[] = ['role' => 'assistant', 'content' => $msg->function_name,];
            } else {
                $messages[] = ['role' => $msg->role, 'content' => $msg->content];
            }

            // if ($msg->role === 'assistant' && $msg->content) {
            //     $messages[] = ['role' => 'assistant', 'content' => $msg->content];
            // } elseif ($msg->role === 'user') {
            //     $messages[] = ['role' => 'user', 'content' => $msg->content];
            // } elseif ($msg->role === 'system') {
            //     $messages[] = ['role' => 'system', 'content' => $msg->content];
            // }
        }

        Log::info('Manager Agent - Messages Array: ' . json_encode($messages));
        return $messages;
    }
}
