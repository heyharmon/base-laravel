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

        // Find the most recent completed function call
        $lastCompletedChat = $this->conversation->chats()
            ->whereNotNull('function_response')
            ->where('job_status', 'completed')
            ->latest()
            ->first();

        $fullContext = array_merge($this->context, [
            'context' => 'job_completed',
        ]);

        if ($lastCompletedChat) {
            $fullContext['completed_function'] = true;
            $fullContext['function_name'] = $lastCompletedChat->function_name;
            $fullContext['function_arguments'] = $lastCompletedChat->function_arguments;
            $fullContext['function_response'] = $lastCompletedChat->function_response;
            $fullContext['additional_data'] = $lastCompletedChat->web_search_results;
        }

        $openai->sendMessage($this->conversation, $this->message, $fullContext);
    }
}
