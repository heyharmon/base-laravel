<?php

namespace App\Services\AI\FunctionHandlers;

use App\Models\Conversation;
use App\Models\Chat;
use App\Jobs\ReviewArticleJob;

class ReviewArticleHandler extends FunctionHandler
{
    protected string $name = 'review_article';

    protected array $definition = [
        'name' => 'review_article',
        'description' => 'Review an article for coherence, accuracy, and completeness',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'article_id' => [
                    'type' => 'integer',
                    'description' => 'The article ID to review',
                ],
            ],
            'required' => ['article_id'],
        ],
    ];

    public function validate(array $arguments): bool
    {
        if (!isset($arguments['article_id'])) {
            $this->validationError = 'Missing article_id in review_article function call';
            return false;
        }
        return true;
    }

    public function handle(Conversation $conversation, Chat $chat, array $arguments): void
    {
        ReviewArticleJob::dispatch(
            $conversation,
            $chat,
            $arguments['article_id']
        );
    }
}
