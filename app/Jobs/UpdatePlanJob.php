<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Conversation;

class UpdatePlanJob extends BaseAgentJob
{
    private array $plan;

    public function __construct(Conversation $conversation, Chat $chat, array $plan)
    {
        parent::__construct($conversation, $chat);
        $this->plan = $plan;
    }

    public function handle(): void
    {
        $this->markJobStarted();

        try {
            $this->conversation->updatePlan($this->plan);

            $this->markJobCompleted(['plan' => $this->plan]);

            $this->continueConversation('Plan updated.');
        } catch (\Exception $e) {
            $this->markJobFailed($e);
            throw $e;
        }
    }
}
