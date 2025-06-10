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

        $response = OpenAI::responses()->create([
            'model' => 'gpt-4o',
            'input' => $messages,
            'tools' => [
                [
                    'type' => 'function',
                    'name' => 'store_article',
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
                    'name' => 'update_article',
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
                    'name' => 'fetch_article',
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
        if (!empty($response->content->tool_calls)) {
            foreach ($response->content->tool_calls as $call) {
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

            $messages[] = [
                'role' => 'assistant',
                'content' => $response->content->text,
                'tool_calls' => $response->content->tool_calls
            ];
            $messages = array_merge($messages, $toolResponses);

            $response = OpenAI::responses()->create([
                'model' => 'gpt-4o',
                'input' => $messages,
            ]);
        }

        // Format the response to match what the frontend expects
        $formattedResponse = [
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => $response->content->text ?? ''
                    ]
                ]
            ]
        ];

        return response()->json($formattedResponse);
    }
}
