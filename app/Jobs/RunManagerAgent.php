<?php

namespace App\Jobs;

use App\Agents\AgentPrompts;
use App\Models\AgentMessage;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use OpenAI\Laravel\Facades\OpenAI;

class RunManagerAgent implements ShouldQueue
{
    use Queueable;

    public string $sessionId;
    public string $userRequest;  // The initial task the user wants the AI team to handle

    public function __construct(string $sessionId, string $userRequest)
    {
        $this->sessionId = $sessionId;
        $this->userRequest = $userRequest;
    }

    public function handle()
    {
        $managerName = "Manager";

        // 1. Initialize conversation memory with system prompt and user request for Manager
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $managerName,
            'role' => 'system',
            'content' => AgentPrompts::$managerSystem
        ]);
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $managerName,
            'role' => 'user',
            'content' => $this->userRequest
        ]);

        // 2. Define available tools (sub-agents and optional web search) for the Manager agent:
        $tools = [
            [
                "type" => "web_search" // built-in tool: allows web browsing for info
            ],
            [
                "type" => "function",
                "name" => "call_content_strategy_agent",
                "description" => "Pass a prompt to the Content Strategy Agent for content strategy-related tasks. Input should contain the content strategy question or task.",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "input" => ["type" => "string", "description" => "The content strategy task or question for the Content Strategy Agent."]
                    ],
                    "required" => ["input"]
                ]
            ],
            [
                "type" => "function",
                "name" => "call_copywriting_agent",
                "description" => "Pass a prompt to the Copywriting Agent for copywriting tasks. Input should contain the copywriting question or task.",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "input" => ["type" => "string", "description" => "The copywriting task or question for the Copywriting Agent."]
                    ],
                    "required" => ["input"]
                ]
            ]
        ];

        // 3. Enter an interaction loop for the Manager agent
        $conversationComplete = false;
        while (!$conversationComplete) {
            // Assemble message history for Manager from DB
            $messages = $this->buildMessagesArray($managerName);

            // Call OpenAI Responses API for the Manager agent's next response
            $apiResponse = OpenAI::responses()->create([
                'model'       => 'gpt-4o',   // using a GPT-4 model optimized for tools
                'input'    => $messages,
                'tools'       => $tools,
                'temperature' => 0.7,
                // 'max_tokens' etc. could be set as needed
            ]);

            // The response may contain a message or a function call (or both)
            $items = $apiResponse->toArray()['items'] ?? [];
            // (We assume the OpenAI PHP client returns a structured response; we convert to array for easier handling.)

            foreach ($items as $item) {
                if ($item['type'] === 'message') {
                    // Model produced a direct message (potential final answer)
                    $content = $item['message']['content'] ?? '';
                    AgentMessage::create([
                        'session_id' => $this->sessionId,
                        'agent_name' => $managerName,
                        'role' => 'assistant',
                        'content' => $content
                    ]);
                    // If we got a message with no function call, assume conversation might be done
                    // (In complex flows, you might check if the Manager indicates completion explicitly.)
                    $conversationComplete = true;
                }
                if ($item['type'] === 'function_call') {
                    // Model wants to use a tool (calls a sub-agent)
                    $funcName = $item['function_call']['name'];
                    $funcArgs = $item['function_call']['arguments'] ?? [];
                    // Log the function call request
                    AgentMessage::create([
                        'session_id'   => $this->sessionId,
                        'agent_name'   => $managerName,
                        'role'         => 'function',
                        'function_name' => $funcName,
                        'function_args' => $funcArgs
                    ]);

                    // Execute the appropriate sub-agent based on function name
                    if ($funcName === 'call_content_strategy_agent') {
                        $strategyQuery = $funcArgs['input'] ?? '';
                        $strategyReply = $this->runContentStrategyAgent($strategyQuery);
                        // Add the sub-agent's answer as function result back to Manager's conversation
                        AgentMessage::create([
                            'session_id' => $this->sessionId,
                            'agent_name' => $managerName,
                            'role'       => 'assistant',  // adding as if manager received this from function
                            'content'    => $strategyReply,
                        ]);
                    } elseif ($funcName === 'call_copywriting_agent') {
                        $copywritingQuery = $funcArgs['input'] ?? '';
                        $copywritingReply = $this->runCopywritingAgent($copywritingQuery);
                        AgentMessage::create([
                            'session_id' => $this->sessionId,
                            'agent_name' => $managerName,
                            'role'       => 'assistant',
                            'content'    => $copywritingReply,
                        ]);
                    }
                    // After executing the tool, loop will continue and Manager will incorporate the new info in next iteration.
                }
            } // end foreach item
        } // end while loop (manager finished)
    }

    private function buildMessagesArray(string $agentName): array
    {
        // Fetch all messages for this agent in current session
        $messages = [];
        $history = AgentMessage::where('session_id', $this->sessionId)
            ->where('agent_name', $agentName)
            ->orderBy('id')->get();
        foreach ($history as $msg) {
            if ($msg->role === 'function') {
                // Skip logging function request to model (the model knows it made the call)
                // Or we could format it as assistant content saying e.g. "<function call made>"
                continue;
            }
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

    private function runContentStrategyAgent(string $strategyTask): string
    {
        $agentName = "Content Strategy Agent";
        // Initialize content strategy conversation (system + task as user)
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'system',
            'content' => AgentPrompts::$contentStrategySystem
        ]);
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'user',
            'content' => $strategyTask
        ]);
        // Content strategy agent may use web_search as well for inspiration
        $tools = [["type" => "web_search"]];
        $messages = $this->buildMessagesArray($agentName);
        $apiResponse = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => $messages,
            'tools' => $tools,
            'temperature' => 0.7
        ]);
        $output = $apiResponse->toArray()['output'] ?? $apiResponse->toArray()['output_text'] ?? null;
        // Assume the content strategy agent provides a direct answer in one step (for simplicity)
        $contentStrategyAnswer = is_string($output) ? $output : "[Content strategy agent did not return text]";
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'assistant',
            'content' => $contentStrategyAnswer
        ]);
        return $contentStrategyAnswer;
    }

    private function runCopywritingAgent(string $copywritingTask): string
    {
        $agentName = "Copywriting Agent";
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'system',
            'content' => AgentPrompts::$copywritingSystem
        ]);
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'user',
            'content' => $copywritingTask
        ]);
        $tools = [["type" => "web_search"]];
        $messages = $this->buildMessagesArray($agentName);
        $apiResponse = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => $messages,
            'tools' => $tools,
            'temperature' => 0.7
        ]);
        $output = $apiResponse->toArray()['output'] ?? $apiResponse->toArray()['output_text'] ?? null;
        $copywritingAnswer = is_string($output) ? $output : "[Copywriting agent did not return text]";
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'assistant',
            'content' => $copywritingAnswer
        ]);
        return $copywritingAnswer;
    }
}
