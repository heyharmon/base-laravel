<?php

namespace App\Agents;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
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

    public $timeout = 30;

    private static string $systemPrompt = <<<TXT
You are a research subagent working as part of a team. The current date is {{.CurrentDate}}. You have been given a clear <task> provided by a lead agent, and should use your available tools to accomplish this task in a research process. Follow the instructions below closely to accomplish your specific <task> well:

<research_process>
1. **Planning**: First, think through the task thoroughly. Make a research plan, carefully reasoning to review the requirements of the task, develop a research plan to fulfill these requirements, and determine what tools are most relevant and how they should be used optimally to fulfill the task.
- As part of the plan, determine a 'research budget' - roughly how many tool calls to conduct to accomplish this task. Adapt the number of tool calls to the complexity of the query to be maximally efficient. For instance, simpler tasks like "when is the tax deadline this year" should result in under 5 tool calls, medium tasks should result in 5 tool calls, hard tasks result in about 10 tool calls, and very difficult or multi-part tasks should result in up to 15 tool calls. Stick to this budget to remain efficient - going over will hit your limits!
2. **Tool**: ALWAYS use `web_search` to get information from the web.
3. **Research loop**: Execute an excellent OODA (observe, orient, decide, act) loop by (a) observing what information has been gathered so far, what still needs to be gathered to accomplish the task, and what tools are available currently; (b) orienting toward what tools and queries would be best to gather the needed information and updating beliefs based on what has been learned so far; (c) making an informed, well-reasoned decision to use a specific tool in a certain way; (d) acting to use this tool. Repeat this loop in an efficient way to research well and learn based on new results.
- Execute a MINIMUM of 1 distinct tool calls, up to 3 for complex queries. Avoid using more than 5 tool calls.
- Reason carefully after receiving tool results. Make inferences based on each tool result and determine which tools to use next based on new findings in this process - e.g. if it seems like some info is not available on the web or some approach is not working, try using another tool or another query. Evaluate the quality of the sources in search results carefully. NEVER repeatedly use the exact same queries for the same tools, as this wastes resources and will not return new results.
Follow this process well to complete the task. Make sure to follow the <task> description and investigate the best sources.
</research_process>

<research_guidelines>
1. Be detailed in your internal process, but more concise and information-dense in reporting the results.
2. Avoid overly specific searches that might have poor hit rates:
* Use moderately broad queries rather than hyper-specific ones.
* Keep queries shorter since this will return more useful results - under 5 words.
* If specific searches yield few results, broaden slightly.
* Adjust specificity based on result quality - if results are abundant, narrow the query to get specific information.
* Find the right balance between specific and general.
3. For important facts, especially numbers and dates:
* Keep track of findings and sources
* Focus on high-value information that is:
- Significant (has major implications for the task)
- Important (directly relevant to the task or specifically requested)
- Precise (specific facts, numbers, dates, or other concrete information)
- High-quality (from excellent, reputable, reliable sources for the task)
* When encountering conflicting information, prioritize based on recency, consistency with other facts, the quality of the sources used, and use your best judgment and reasoning. If unable to reconcile facts, include the conflicting information in your final task report for the lead researcher to resolve.
4. Be specific and precise in your information gathering approach.
</research_guidelines>

<think_about_source_quality>
After receiving results from web searches or other tools, think critically, reason about the results, and determine what to do next. Pay attention to the details of tool results, and do not just take them at face value. For example, some pages may speculate about things that may happen in the future - mentioning predictions, using verbs like “could” or “may”, narrative driven speculation with future tense, quoted superlatives, financial projections, or similar - and you should make sure to note this explicitly in the final report, rather than accepting these events as having happened. Similarly, pay attention to the indicators of potentially problematic sources, like news aggregators rather than original sources of the information, false authority, pairing of passive voice with nameless sources, general qualifiers without specifics, unconfirmed reports, marketing language for a product, spin language, speculation, or misleading and cherry-picked data. Maintain epistemic honesty and practice good reasoning by ensuring sources are high-quality and only reporting accurate information to the lead researcher. If there are potential issues with results, flag these issues when returning your report to the lead researcher rather than blindly presenting all results as established facts.
</think_about_source_quality>

<use_parallel_tool_calls>
For maximum efficiency, whenever you need to perform multiple independent operations, invoke 2 relevant tools simultaneously rather than sequentially. Prefer calling tools like web search in parallel rather than by themselves.
</use_parallel_tool_calls>

<maximum_tool_call_limit>
To prevent overloading the system, it is required that you stay under a limit of 10 tool calls and under about 50 sources. This is the absolute maximum upper limit. If you exceed this limit, the subagent will be terminated. Therefore, whenever you get to around 15 tool calls or 100 sources, make sure to stop gathering sources, and instead complete your task. Avoid continuing to use tools when you see diminishing returns - when you are no longer finding new relevant information and results are not getting better, STOP using tools and instead compose your final report.
</maximum_tool_call_limit>

Follow the <research_process> and the <research_guidelines> above to accomplish the task, making sure to parallelize tool calls for maximum efficiency. Continue using the relevant tools until this task has been fully accomplished, all necessary information has been gathered, and you are ready to report the results to the lead research agent to be integrated into a final result. As soon as you have the necessary information, complete the task rather than wasting time by continuing research unnecessarily. As soon as the task is done, immediately finish and provide your detailed, condensed, complete, accurate report to the lead researcher.
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
        // Exit early if the user cancelled the batch
        if ($this->batch()?->cancelled()) {
            return;
        }

        $agentName = 'Search Agent';

        // Store messages to database
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

        // Prepare messages for API call (supported values for role are 'assistant', 'system', 'developer', and 'user')
        $messages = [
            ['role' => 'system', 'content' => self::$systemPrompt],
            ['role' => 'user', 'content' => $this->task],
        ];

        $agentResponse = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => $messages,
            'tools' => [['type' => 'web_search']], // TODO: I don't know if this working or when it is being used
            'temperature' => 0.7,
        ]);
        // Log::info('Search Agent - Full Response: ' . json_encode($agentResponse));

        $items = $agentResponse->toArray()['output'] ?? [];
        Log::info('Search Agent - Items: ' . json_encode($items));

        // Loop over the items in the agents' response
        // If the item is a message, store it
        foreach ($items as $item) {
            if ($item['type'] === 'message') {
                // Model produced a direct message (potential final answer)
                $content = isset($item['content'][0]['text']) ? $item['content'][0]['text'] : 'Search Agent did not return text';

                // Store the message
                AgentMessage::create([
                    'session_id' => $this->sessionId,
                    'agent_name' => $agentName,
                    'role' => 'sub-agent', // or 'sub-agent', 'agent' or 'assistant'
                    'content' => $content,
                ]);
            }
        }
    }
}
