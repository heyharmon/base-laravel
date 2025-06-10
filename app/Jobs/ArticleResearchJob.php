<?php

namespace App\Jobs;

use App\Models\Chat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;

class ArticleResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $topic)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): string
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-3.5-turbo',
            'input' => "You are an expert research assistant. Provide concise research on the topic.\n\nResearch the following blog article topic: {$this->topic}",
        ]);

        $content = $response->outputText ?? '';

        Chat::create([
            'role' => 'research_agent',
            'content' => $content,
        ]);

        return $content;
    }
}
