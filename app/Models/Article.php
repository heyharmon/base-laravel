<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'title',
        'outline',
        'content',
        'status',
    ];

    protected $casts = [
        'outline' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Update the article content
     */
    public function updateContent(string $content): void
    {
        $this->update(['content' => $content]);
    }

    /**
     * Get the word count of the article content
     */
    public function getWordCount(): int
    {
        return $this->content ? str_word_count($this->content) : 0;
    }
}
