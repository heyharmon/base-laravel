<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'function_name',
        'function_arguments',
        'function_response',
        'reasoning',
        'web_search_results',
        'prompt_tokens',
        'completion_tokens',
        'cost',
        'job_id',
        'job_status',
    ];

    protected $casts = [
        'function_arguments' => 'array',
        'function_response' => 'array',
        'web_search_results' => 'array',
        'cost' => 'decimal:4',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function calculateCost(): void
    {
        $promptCost = ($this->prompt_tokens / 1000) * 0.0025;
        $completionCost = ($this->completion_tokens / 1000) * 0.01;
        $this->cost = $promptCost + $completionCost;
        $this->save();
    }

    public function isJobRunning(): bool
    {
        return $this->job_id && in_array($this->job_status, ['pending', 'processing']);
    }
}
