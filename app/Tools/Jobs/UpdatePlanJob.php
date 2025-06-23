<?php

namespace App\Tools\Jobs;

use App\Tools\Jobs\ToolJob;
use App\Models\Conversation;
use App\Models\Chat;

class UpdatePlanJob extends ToolJob
{
    protected Conversation $conversation;
    private array $plan;

    public function __construct(Conversation $conversation, Chat $chat, array $plan)
    {
        parent::__construct($conversation, $chat);
        $this->conversation = $conversation;
        $this->plan = $plan;
    }

    public function handle(): void
    {
        $this->markJobStarted();

        try {
            $this->conversation->updatePlan($this->plan);

            // $this->conversation->chats()->create([
            //     'role' => 'system',
            //     'content' => 'Plan updated: ' . json_encode($this->plan, JSON_PRETTY_PRINT),
            //     'reasoning' => 'Agent updated the research and writing plan',
            // ]);

            $this->markJobCompleted([
                'plan' => $this->plan,
            ]);

            $this->continueConversation(
                "Plan updated. The plan is now: " . json_encode($this->plan, JSON_PRETTY_PRINT)
            );
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }
}
