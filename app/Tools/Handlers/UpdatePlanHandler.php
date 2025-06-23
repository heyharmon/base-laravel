<?php

namespace App\Tools\Handlers;

use App\Tools\Jobs\UpdatePlanJob;
use App\Tools\Handlers\ToolHandler;
use App\Models\Conversation;
use App\Models\Chat;

class UpdatePlanHandler extends ToolHandler
{
    protected string $name = 'update_plan';

    protected array $definition = [
        'name' => 'update_plan',
        'description' => 'Update the current research and writing plan with specific steps and progress',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'plan' => [
                    'type' => 'object',
                    'description' => 'The updated plan object containing steps, goals, or progress. Must not be empty.',
                    'properties' => [
                        'steps' => [
                            'type' => 'array',
                            'description' => 'List of steps or tasks in the plan',
                            'items' => ['type' => 'string']
                        ],
                        'goal' => [
                            'type' => 'string',
                            'description' => 'The main goal or objective'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Current status of the plan'
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Additional notes or context'
                        ]
                    ],
                    'minProperties' => 1
                ],
            ],
            'required' => ['plan'],
        ],
    ];

    public function validate(array $arguments): bool
    {
        if (!isset($arguments['plan']) || empty($arguments['plan'])) {
            $this->validationError = 'Missing or empty plan in update_plan function call';
            return false;
        }
        return true;
    }

    public function handle(Conversation $conversation, Chat $chat, array $arguments): void
    {
        UpdatePlanJob::dispatch($conversation, $arguments['plan']);
    }
}
