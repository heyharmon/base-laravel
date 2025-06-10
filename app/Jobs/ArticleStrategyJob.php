<?php

namespace App\Jobs;

use App\Models\Chat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;

class ArticleStrategyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $topic, public string $research)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): string
    {
        $response = OpenAI::responses()->create([
            'model' => 'gpt-3.5-turbo',
            'input' => "You are an article strategist. Propose an outline and keywords.\n\nTopic: {$this->topic}\nResearch: {$this->research}",
        ]);

        $content = $response->outputText ?? '';

        Chat::create([
            'role' => 'strategy_agent',
            'content' => $content,
        ]);

        return $content;
    }
}
