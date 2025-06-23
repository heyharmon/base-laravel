<?php

namespace App\Tools\Handlers;

use App\Tools\Jobs\FetchWebpageJob;
use App\Tools\Handlers\ToolHandler;
use App\Models\Conversation;
use App\Models\Chat;

class FetchWebpageHandler extends ToolHandler
{
    protected string $name = 'fetch_webpage';

    protected array $definition = [
        'name' => 'fetch_webpage',
        'description' => 'Fetch and extract content from a webpage',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'The URL to fetch',
                ],
            ],
            'required' => ['url'],
        ],
    ];

    public function validate(array $arguments): bool
    {
        if (!isset($arguments['url']) || empty($arguments['url'])) {
            $this->validationError = 'Missing url in fetch_webpage function call';
            return false;
        }
        return true;
    }

    public function handle(Conversation $conversation, Chat $chat, array $arguments): void
    {
        FetchWebpageJob::dispatch($conversation, $chat, $arguments['url']);
    }
}
