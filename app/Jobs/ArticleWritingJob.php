<?php

namespace App\Jobs;

use App\Models\Chat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;

class ArticleWritingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $topic, public string $strategy)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): string
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-3.5-turbo',
            'input' => "You are a blog writer. Write a draft article.\n\nTopic: {$this->topic}\nStrategy: {$this->strategy}",
        ]);

        $content = $response->outputText ?? '';

        Chat::create([
            'role' => 'writing_agent',
            'content' => $content,
        ]);

        return $content;
    }
}
