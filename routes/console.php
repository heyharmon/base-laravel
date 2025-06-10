<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\BlogManagerJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('blog:generate {topic}', function (string $topic) {
    dispatch(new BlogManagerJob($topic));
    $this->info('Blog generation dispatched.');
})->purpose('Generate a blog article using agents');
