<?php

namespace App\Tools\Handlers;

use App\Tools\Jobs\ReviewArticleJob;
use App\Tools\Handlers\ToolHandler;
use App\Models\Conversation;
use App\Models\Chat;

class ReviewArticleHandler extends ToolHandler
{
    protected string $name = 'review_article';

    protected array $definition = [
        'name' => 'review_article',
        'description' => 'Review an article for coherence, accuracy, and completeness. When article context is provided, use that article_id.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'article_id' => [
                    'type' => 'integer',
                    'description' => 'The article ID to review (use the ID from context if available)',
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
