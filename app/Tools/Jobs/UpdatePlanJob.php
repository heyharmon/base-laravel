<?php

namespace App\Tools\Jobs;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdatePlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Conversation $conversation;
    private array $plan;

    public function __construct(Conversation $conversation, array $plan)
    {
        $this->conversation = $conversation;
        $this->plan = $plan;
    }

    public function handle(): void
    {
        $this->conversation->updatePlan($this->plan);
        $this->conversation->chats()->create([
            'role' => 'system',
            'content' => 'Plan updated: ' . json_encode($this->plan, JSON_PRETTY_PRINT),
            'reasoning' => 'Agent updated the research and writing plan',
        ]);
    }
}
