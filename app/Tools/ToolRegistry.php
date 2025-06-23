<?php

namespace App\Tools;

use App\Tools\Handlers\ToolHandler;
use App\Tools\Handlers\UpdatePlanHandler;
use App\Tools\Handlers\WebSearchHandler;
use App\Tools\Handlers\FetchWebpageHandler;
use App\Tools\Handlers\CreateArticleHandler;
use App\Tools\Handlers\WriteArticleSectionHandler;
use App\Tools\Handlers\ReviewArticleHandler;

class ToolRegistry
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

    private function registerHandler(ToolHandler $handler): void
    {
        $this->handlers[$handler->getName()] = $handler;
    }

    public function getHandler(string $functionName): ?ToolHandler
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
