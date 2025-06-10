<?php

namespace App\Jobs;

use App\Models\AgentMemory;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunAgent implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $agentName;
    protected $agentRole;
    protected $incomingMessage;

    public function __construct($agentName, $agentRole, $incomingMessage)
    {
        $this->agentName = $agentName;
        $this->agentRole = $agentRole;
        $this->incomingMessage = $incomingMessage;
    }

    public function handle(OpenAIService $openAIService)
    {
        // Load memory
        $memory = AgentMemory::firstOrCreate(
            ['agent_name' => $this->agentName],
            ['memory' => []]
        );

        // Append incoming message
        $conversation = $memory->memory ?? [];
        $conversation[] = ['role' => 'user', 'content' => $this->incomingMessage];

        // Build messages
        $messages = [
            ['role' => 'system', 'content' => $this->agentRole],
        ];

        foreach ($conversation as $msg) {
            $messages[] = $msg;
        }

        // Call OpenAI Chat API
        $response = $openAIService->client()->chat()->create([
            'model' => 'gpt-4o',
            'messages' => $messages,
            'temperature' => 0.7,
        ]);

        $reply = $response->choices[0]->message->content;

        // Append agent reply to memory
        $conversation[] = ['role' => 'assistant', 'content' => $reply];
        $memory->update(['memory' => $conversation]);

        // Trigger next agents if needed
        $this->dispatchNextAgent($reply);
    }

    protected function dispatchNextAgent($reply)
    {
        $sequence = [
            'manager' => 'strategist',
            'strategist' => 'writer',
            'writer' => null, // End of cycle
        ];

        $nextAgent = $sequence[$this->agentName] ?? null;

        if ($nextAgent) {
            dispatch(new RunAgent(
                $nextAgent,
                config('agents.roles')[$nextAgent],
                $reply
            ));
        }
    }
}
