<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'agent_plan',
        'total_tokens_used',
        'total_cost',
        'status',
    ];

    protected $casts = [
        'agent_plan' => 'array',
        'total_cost' => 'decimal:4',
    ];

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function updatePlan(array $plan): void
    {
        $this->update(['agent_plan' => $plan]);
    }

    public function addTokenUsage(int $tokens, float $cost): void
    {
        $this->increment('total_tokens_used', $tokens);
        $this->increment('total_cost', $cost);
    }

    public function getActiveJobs()
    {
        return $this->chats()
            ->whereNotNull('job_id')
            ->whereIn('job_status', ['pending', 'processing'])
            ->get();
    }
}
