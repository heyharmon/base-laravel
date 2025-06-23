<?php

namespace App\Tools\Handlers;

use App\Tools\Jobs\WriteArticleSectionJob;
use App\Tools\Handlers\ToolHandler;
use App\Models\Conversation;
use App\Models\Chat;

class WriteArticleSectionHandler extends ToolHandler
{
    protected string $name = 'write_article_section';

    protected array $definition = [
        'name' => 'write_article_section',
        'description' => 'Write or update a section of an article. When article context is provided, use that article_id.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'article_id' => [
                    'type' => 'integer',
                    'description' => 'The article ID (use the ID from context if available)',
                ],
                'section' => [
                    'type' => 'string',
                    'description' => 'The section identifier',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The content for this section',
                ],
            ],
            'required' => ['article_id', 'section', 'content'],
        ],
    ];

    public function validate(array $arguments): bool
    {
        if (!isset($arguments['article_id'])) {
            $this->validationError = 'Missing article_id in write_article_section function call';
            return false;
        }
        if (!isset($arguments['section'])) {
            $this->validationError = 'Missing section in write_article_section function call';
            return false;
        }
        if (!isset($arguments['content'])) {
            $this->validationError = 'Missing content in write_article_section function call';
            return false;
        }
        return true;
    }

    public function handle(Conversation $conversation, Chat $chat, array $arguments): void
    {
        WriteArticleSectionJob::dispatch(
            $conversation,
            $chat,
            $arguments['article_id'],
            $arguments['section'],
            $arguments['content']
        );
    }
}
