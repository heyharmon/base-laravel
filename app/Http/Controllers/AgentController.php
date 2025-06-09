<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\Article;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        $messages = $request->input('messages', []);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => $messages,
            'tools' => [
                ['type' => 'web_search'],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'store_article',
                        'description' => 'Store a blog article',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'content' => ['type' => 'string'],
                            ],
                            'required' => ['title', 'content'],
                        ],
                    ],
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'update_article',
                        'description' => 'Update an existing blog article',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'title' => ['type' => 'string'],
                                'content' => ['type' => 'string'],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'fetch_article',
                        'description' => 'Fetch an article by id',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                ],
            ],
            'tool_choice' => 'auto',
        ]);

        $toolResponses = [];
        if (!empty($response->choices[0]->message->toolCalls)) {
            foreach ($response->choices[0]->message->toolCalls as $call) {
                $data = json_decode($call->function->arguments, true);

                switch ($call->function->name) {
                    case 'store_article':
                        $article = Article::create($data);
                        $toolResponses[] = [
                            'tool_call_id' => $call->id,
                            'role' => 'tool',
                            'name' => $call->function->name,
                            'content' => $article->toJson(),
                        ];
                        break;
                    case 'update_article':
                        $article = Article::findOrFail($data['id']);
                        $article->update($data);
                        $toolResponses[] = [
                            'tool_call_id' => $call->id,
                            'role' => 'tool',
                            'name' => $call->function->name,
                            'content' => $article->toJson(),
                        ];
                        break;
                    case 'fetch_article':
                        $article = Article::findOrFail($data['id']);
                        $toolResponses[] = [
                            'tool_call_id' => $call->id,
                            'role' => 'tool',
                            'name' => $call->function->name,
                            'content' => $article->toJson(),
                        ];
                        break;
                }
            }

            $messages[] = $response->choices[0]->message->toArray();
            $messages = array_merge($messages, $toolResponses);

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => $messages,
            ]);
        }

        return response()->json($response->toArray());
    }
}
