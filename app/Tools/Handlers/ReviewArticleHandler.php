<?php

namespace App\Tools\Handlers;

use App\Tools\Jobs\ReviewArticleJob;
use App\Tools\Handlers\ToolHandler;
use App\Models\Conversation;
use App\Models\Chat;

class ReviewArticleHandler extends ToolHandler
{
    protected string $name = 'view_article';

    protected array $definition = [
        'name' => 'view_article',
        'description' => 'View an article properties including title, content, outline, and status. When article context is provided, use that article_id.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'article_id' => [
                    'type' => 'integer',
                    'description' => 'The article ID to view (use the ID from context if available)',
                ],
            ],
            'required' => ['article_id'],
        ],
    ];

    public function validate(array $arguments): bool
    {
        if (!isset($arguments['article_id'])) {
            $this->validationError = 'Missing article_id in view_article function call';
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
