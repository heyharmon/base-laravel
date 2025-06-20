<?php

namespace App\Agents;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use App\Models\AgentMessage;

/**
 * Executes the Search Agent.
 *
 * The agent logs a user request and system prompt, calls the LLM, stores the
 * assistant's response, and can be cancelled when the batch is stopped.
 */
class SearchAgent implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $timeout = 300;

    private static string $systemPrompt = <<<TXT
You are a search agent, expert in searching the web for information.
When given a search task you will search the web for information and return a summary of your findings.
TXT;

    /**
     * @param string $sessionId  The session identifier
     * @param string $task  Prompt passed to the agent
     */
    public function __construct(
        public string $sessionId,
        public string $task
    ) {}

    /**
     * Run the agent and store its response.
     */
    public function handle(): void
    {
        $agentName = 'Search Agent';

        // Exit early if the user cancelled the batch
        if ($this->batch()?->cancelled()) {
            return;
        }

        // 1. Store system and initial user prompt
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => null,
            'role' => 'system',
            'content' => self::$systemPrompt,
        ]);
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => null,
            'role' => 'user',
            'content' => 'Search for: ' . $this->task,
        ]);

        // 2. Optionally refine the search query using the LLM (if query is complex or long) potentially splitting into multiple sub-queries
        if (str_word_count($this->task) > 5) {
            $refinePrompt = "You are a search strategist. Rewrite or split the user query into one or more specific queries to find relevant and reliable info.\nUser query: \"{$this->task}\"";
            $refineResponse = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',  // use a model to refine query
                'messages' => [
                    ['role' => 'system', 'content' => ''],
                    ['role' => 'user', 'content' => $refinePrompt],
                ],
                'temperature' => 0.2
            ]);
            $suggested = trim($refineResponse['choices'][0]['message']['content'] ?? '');
            if (!empty($suggested)) {
                // If multiple queries suggested (e.g. separated by newlines), use them
                $queries = preg_split('/\r?\n/', $suggested, -1, PREG_SPLIT_NO_EMPTY);
                $queries = array_map('trim', $queries);
                if (count($queries) > 1) {
                    Log::info("SearchAgent: split query into sub-queries: " . implode(' | ', $queries));
                }
            }
        }
        $queries = $queries ?? [$this->task];

        // 3. Perform web searches for each query using Firecrawl Search API
        $allResults = [];
        $firecrawlApiKey = config('services.firecrawl.key');
        foreach ($queries as $q) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $firecrawlApiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.firecrawl.dev/v1/search', [
                'query' => $q,
                'limit' => 5, // Maximum number of results to return
                'lang' => 'en', // Language code for search results
                'country' => 'US', // Country code for search results
                'timeout' => 30000, // Timeout in milliseconds (30 seconds)
                'scrapeOptions' => [
                    'formats' => ['markdown'], // Get content in markdown format
                    'timeout' => 30000
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['data'] ?? [];

                // Transform Firecrawl results to match expected structure
                $transformedResults = [];
                foreach ($results as $result) {
                    $transformedResults[] = [
                        'url' => $result['url'] ?? '',
                        'name' => $result['metadata']['title'] ?? 'Result',
                        'snippet' => $result['metadata']['description'] ?? '',
                        'content' => $result['markdown'] ?? '', // Full scraped content
                    ];
                }

                $allResults = array_merge($allResults, $transformedResults);
            } else {
                Log::warning("Search Agent: Firecrawl API call failed for query [$q]: " . $response->body());
            }
        }

        // 4. Filter & rank results for reputable domains (e.g. .gov, .edu, major news sites)
        $topResults = array_slice($allResults, 0, 5);  // take top 5 results to summarize

        // 5. Summarize the search results using the LLM
        $summaryInput = "I found the following information:\n";
        foreach ($topResults as $res) {
            $summaryInput .= "- " . ($res['name'] ?? 'Result') . ": "
                . ($res['snippet'] ?? '') . "\n";
        }
        $summaryInput .= "\nWrite a brief summary of these findings.";
        $summaryResponse = OpenAI::chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => self::$systemPrompt],
                ['role' => 'user', 'content' => $summaryInput]
            ],
            'temperature' => 0.7,
        ]);
        $summaryText = $summaryResponse['choices'][0]['message']['content'] ?? '(No summary)';

        // 6. Store the summary as the SearchAgent's answer
        AgentMessage::create([
            'session_id' => $this->sessionId,
            'agent_name' => $agentName,
            'role' => 'sub-agent',
            'content' => $summaryText
        ]);
    }
}
