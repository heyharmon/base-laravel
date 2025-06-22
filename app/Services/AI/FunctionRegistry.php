<?php

namespace App\Services\AI;

use App\Services\AI\FunctionHandlers\FunctionHandler;
use App\Services\AI\FunctionHandlers\UpdatePlanHandler;
use App\Services\AI\FunctionHandlers\WebSearchHandler;
use App\Services\AI\FunctionHandlers\FetchWebpageHandler;
use App\Services\AI\FunctionHandlers\CreateArticleHandler;
use App\Services\AI\FunctionHandlers\WriteArticleSectionHandler;
use App\Services\AI\FunctionHandlers\ReviewArticleHandler;

class FunctionRegistry
{
    private array $handlers = [];

    public function __construct()
    {
        // Register all handlers during construction
        $this->registerHandler(new UpdatePlanHandler());
        $this->registerHandler(new WebSearchHandler());
        $this->registerHandler(new FetchWebpageHandler());
        $this->registerHandler(new CreateArticleHandler());
        $this->registerHandler(new WriteArticleSectionHandler());
        $this->registerHandler(new ReviewArticleHandler());
    }

    private function registerHandler(FunctionHandler $handler): void
    {
        $this->handlers[$handler->getName()] = $handler;
    }

    public function getHandler(string $functionName): ?FunctionHandler
    {
        return $this->handlers[$functionName] ?? null;
    }

    public function getAllDefinitions(): array
    {
        $definitions = [];
        foreach ($this->handlers as $handler) {
            $definitions[] = $handler->getDefinition();
        }
        return $definitions;
    }
}
