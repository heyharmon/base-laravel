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
use App\Agents\SearchAgent;

/**
 * Coordinator agent that manages the overall multi-agent workflow.
 *
 * Each execution represents a single step of the manager agent. The manager
 * may schedule new sub-agent jobs and re-dispatch itself for the next step
 * using Laravel's batch feature.
 */
class ManagerAgent implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 30;

    private static string $systemPrompt = <<<TXT
You are a manager agent coordinating a team of sub-agents.
Your job is to take the users request and come up with a list of tasks that will satisfy the users request, then delegate each task to the appropriate sub-agent.
Always start by sharing your plan and task list with the user, then automatically delegate each task to the appropriate sub-agent.
After receiving a response from a sub-agent, evaluate their response and update your task list then delegate the next task to the appropriate sub-agent.
You have access to the following sub-agents as tools:
- Search Agent: An expert in searching the web for information and writing a summary of the findings. Give this agent one search query to research at a time. Do not delegate more than 3 searches to the Search Agent.
- Copywriting Agent: An expert in writing content. Use this agent when you are ready to have a piece of content written.
- Citation Agent: An expert in generating citations for a given text. Use this agent when you have a piece of content you need to add citations to.
Once task list is complete and the users request is satisfied, end the workflow.
TXT;

    /**
     * @param string $sessionId  Session identifier for message grouping
     * @param string|null $task   The initial task the user wants the AI team to handle
     */
    public function __construct(
        public string $sessionId,
        public ?string $task = null
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

        $managerName = 'Manager Agent';

        // 1. If user request, initialize conversation with system prompt and user request for Manager
        if ($this->task) {
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
        }

        // 2. Define available tools (sub-agents and optional webs search) for the Manager Agent
        $tools = [
            // [
            //     'type' => 'web_search'
            // ],
            [
                'type' => 'function',
                'name' => 'call_search_agent',
                'description' => 'Pass a task to the Search Agent',
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
                'description' => 'Pass a task to the Copywriting Agent',
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
                'name' => 'call_citation_agent',
                'description' => 'Pass a task to the Citation Agent',
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
        // Assemble message history for Manager agent
        $messages = $this->buildMessagesArray();

        // Call OpenAI Responses API for the Manager agents next response
        $agentResponse = OpenAI::responses()->create([
            'model' => 'o4-mini',
            'input' => $messages,
            'tools' => $tools,
        ]);

        // The response may contain a message or a function call (or both)
        // (We assume the OpenAI PHP client returns a structured response; we convert to array for easier handleing)
        $items = $agentResponse->toArray()['output'] ?? [];
        Log::info('Manager Agent - Items: ' . json_encode($items));

        // Loop over the items in the agents' response
        // If the item is a message, store it
        // If the item is a function call, execute the function
        $shouldContinue = false;
        foreach ($items as $item) {
            if ($item['type'] === 'message') {
                // Model produced a direct message (potential final answer)
                $content = isset($item['content'][0]['text']) ? $item['content'][0]['text'] : 'Manager Agent did not return text';

                // Store the message
                AgentMessage::create([
                    'session_id' => $this->sessionId,
                    'agent_name' => $managerName,
                    'role' => 'assistant',
                    'content' => $content,
                ]);

                // If we got a message with no function call, assume conversation might be done
                // (In completed flows, you might check if the Manager indicates completion explicitely.)
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
                if ($funcName === 'call_search_agent') {
                    $input = $funcArgs['input'] ?? '';
                    $this->batch()?->add(new SearchAgent($this->sessionId, $input));
                }
                if ($funcName === 'call_copywriting_agent') {
                    $input = $funcArgs['input'] ?? '';
                    $this->batch()?->add(new CopywritingAgent($this->sessionId, $input));
                }
                if ($funcName === 'call_citation_agent') {
                    $input = $funcArgs['input'] ?? '';
                    $this->batch()?->add(new CitationAgent($this->sessionId, $input));
                }

                // After executing a tool, loop will continue and Manager will incorporate the new info in the next iteration.
                $shouldContinue = true;
            }
        } // end foreach item loop

        if ($shouldContinue) {
            // When a sub-agent is called, the Manager Agent should be called again to continue the conversation
            // This allows the manager agent to evaluate the sub-agent's response and continue the conversation
            $this->batch()?->add(new ManagerAgent($this->sessionId));
        } else {
            // Log the result of buildMessagesArray
            Log::info('Will not continue - Full messages array for debugging: ' . json_encode($this->buildMessagesArray()));
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
        $history = AgentMessage::where('session_id', $this->sessionId)->orderBy('id')->get();

        // Rebuild each message in the conversation history for brevity
        // Supported values for role are 'assistant', 'system', 'developer', and 'user'
        foreach ($history as $msg) {
            if ($msg->role === 'function') {
                $messages[] = ['role' => 'assistant', 'content' => 'You (Manager Agent) called the sub-agent function: ' . $msg->function_name];
            } else if ($msg->role === 'sub-agent') {
                $messages[] = ['role' => 'assistant', 'content' => 'The sub-agent "' . $msg->agent_name . '" returned: ' . $msg->content];
            } else {
                $messages[] = ['role' => $msg->role, 'content' => $msg->content];
            }
        }

        return $messages;
    }
}
