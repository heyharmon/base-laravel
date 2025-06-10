<?php

namespace App\Jobs;

use App\Models\Chat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;

class ChatResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $message)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): string
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-3.5-turbo',
            'input' => $this->message,
        ]);

        $content = $response->outputText ?? '';

        Chat::create([
            'role' => 'assistant',
            'content' => $content,
        ]);

        return $content;
    }
}
