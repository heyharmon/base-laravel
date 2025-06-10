<?php

namespace App\Jobs;

use App\Jobs\ArticleResearchJob;
use App\Jobs\ArticleStrategyJob;
use App\Jobs\ArticleWritingJob;
use App\Jobs\ArticleEditingJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BlogManagerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $topic)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $research = dispatch_sync(new ArticleResearchJob($this->topic));
        $strategy = dispatch_sync(new ArticleStrategyJob($this->topic, $research));
        $draft = dispatch_sync(new ArticleWritingJob($this->topic, $strategy));
        dispatch_sync(new ArticleEditingJob($draft));
    }
}
