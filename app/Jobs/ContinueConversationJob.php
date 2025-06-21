<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ContinueConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    private Conversation $conversation;
    private string $message;

    public function __construct(Conversation $conversation, string $message)
    {
        $this->conversation = $conversation;
        $this->message = $message;
    }

    public function handle(OpenAIService $openai): void
    {
        $activeJobs = $this->conversation->getActiveJobs();
        if ($activeJobs->count() > 0) {
            $this->release(30);
            return;
        }

        $openai->sendMessage($this->conversation, $this->message, [
            'context' => 'job_completed',
        ]);
    }
}
