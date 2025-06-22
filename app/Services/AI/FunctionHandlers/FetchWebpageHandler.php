<?php

namespace App\Services\AI\FunctionHandlers;

use App\Models\Conversation;
use App\Models\Chat;
use App\Jobs\FetchWebpageJob;

class FetchWebpageHandler extends FunctionHandler
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
