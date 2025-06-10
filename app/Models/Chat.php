<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chat extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'role', // The role of the chat (user, agent)
        'content', // The content of the chat
        'metadata', // Any additional metadata
        'annotations', // Web search annotations/citations
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'annotations' => 'array',
    ];
    
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
