<?php

namespace App\Jobs;

use App\Models\Chat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;

class ArticleEditingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $article)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): string
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-3.5-turbo',
            'input' => "You are an editor. Improve style and grammar.\n\n{$this->article}",
        ]);

        $content = $response->outputText ?? '';

        Chat::create([
            'role' => 'editing_agent',
            'content' => $content,
        ]);

        return $content;
    }
}
