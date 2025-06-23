<?php

namespace App\Tools\Handlers;

use App\Models\Conversation;
use App\Models\Chat;
use App\Tools\Jobs\WebSearchJob;

class WebSearchHandler extends ToolHandler
{
    protected string $name = 'web_search';

    protected array $definition = [
        'name' => 'web_search',
        'description' => 'Search the web for information',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The search query',
                ],
            ],
            'required' => ['query'],
        ],
    ];

    public function validate(array $arguments): bool
    {
        if (!isset($arguments['query']) || empty($arguments['query'])) {
            $this->validationError = 'Missing or empty query in web_search function call';
            return false;
        }
        return true;
    }

    public function handle(Conversation $conversation, Chat $chat, array $arguments): void
    {
        WebSearchJob::dispatch($conversation, $chat, $arguments['query']);
    }
}
