<?php

namespace App\Tools\Handlers;

use App\Models\Conversation;
use App\Models\Chat;

class ToolHandler
{
    protected string $name;
    protected array $definition;
    protected ?string $validationError = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefinition(): array
    {
        return $this->definition;
    }

    public function validate(array $arguments): bool
    {
        // Override in child classes
        return true;
    }

    public function getValidationError(): ?string
    {
        return $this->validationError;
    }

    public function handle(Conversation $conversation, Chat $chat, array $arguments): void
    {
        // Override in child classes
    }
}
