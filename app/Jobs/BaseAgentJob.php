<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class BaseAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    protected Conversation $conversation;
    protected Chat $chat;

    public function __construct(Conversation $conversation, Chat $chat)
    {
        $this->conversation = $conversation;
        $this->chat = $chat;
    }

    protected function markJobStarted(): void
    {
        $this->chat->update([
            'job_id' => $this->job->getJobId(),
            'job_status' => 'processing',
        ]);
    }

    protected function markJobCompleted(array $result): void
    {
        $this->chat->update([
            'job_status' => 'completed',
            'function_response' => $result,
        ]);
    }

    protected function markJobFailed(\Exception $e): void
    {
        $this->chat->update([
            'job_status' => 'failed',
            'function_response' => [
                'error' => $e->getMessage(),
            ],
        ]);
    }

    protected function continueConversation(string $message): void
    {
        ContinueConversationJob::dispatch($this->conversation, $message);
    }
}
