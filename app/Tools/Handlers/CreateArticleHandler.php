<?php

namespace App\Tools\Handlers;

use App\Tools\Jobs\CreateArticleJob;
use App\Tools\Handlers\ToolHandler;
use App\Models\Conversation;
use App\Models\Chat;

class CreateArticleHandler extends ToolHandler
{
    protected string $name = 'create_article';

    protected array $definition = [
        'name' => 'create_article',
        'description' => 'Create a new article with title and outline',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'The article title',
                ],
                'outline' => [
                    'type' => 'array',
                    'description' => 'The article outline structure',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'Section title'
                            ],
                            'subsections' => [
                                'type' => 'array',
                                'description' => 'Optional subsections',
                                'items' => [
                                    'type' => 'string'
                                ]
                            ]
                        ]
                    ]
                ],
            ],
            'required' => ['title', 'outline'],
        ],
    ];

    public function validate(array $arguments): bool
    {
        if (!isset($arguments['title']) || empty($arguments['title'])) {
            $this->validationError = 'Missing or empty title in create_article function call';
            return false;
        }
        if (!isset($arguments['outline']) || !is_array($arguments['outline']) || empty($arguments['outline'])) {
            $this->validationError = 'Missing or invalid outline in create_article function call';
            return false;
        }
        return true;
    }

    public function handle(Conversation $conversation, Chat $chat, array $arguments): void
    {
        CreateArticleJob::dispatch(
            $conversation,
            $chat,
            $arguments['title'],
            $arguments['outline']
        );
    }
}
