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
    private array $context;

    public function __construct(Conversation $conversation, string $message, array $context = [])
    {
        $this->conversation = $conversation;
        $this->message = $message;
        $this->context = $context;
    }

    public function handle(OpenAIService $openai): void
    {
        $activeJobs = $this->conversation->getActiveJobs();
        if ($activeJobs->count() > 0) {
            $this->release(30);
            return;
        }

        // Merge job_completed context with any article context
        $fullContext = array_merge($this->context, [
            'context' => 'job_completed',
        ]);

        $openai->sendMessage($this->conversation, $this->message, $fullContext);
    }
}
