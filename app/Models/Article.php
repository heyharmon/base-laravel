<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'title',
        'outline',
        'current_version',
        'status',
    ];

    protected $casts = [
        'outline' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class);
    }

    public function getCurrentVersion(): ?ArticleVersion
    {
        return $this->versions()->where('version_number', $this->current_version)->first();
    }

    public function createNewVersion(string $content, ?string $changeSummary = null): ArticleVersion
    {
        $this->increment('current_version');

        return $this->versions()->create([
            'version_number' => $this->current_version,
            'content' => $content,
            'change_summary' => $changeSummary,
            'metadata' => [
                'word_count' => str_word_count($content),
                'created_at' => now(),
            ],
        ]);
    }

    public function updateContent(string $content, ?string $changeSummary = null): void
    {
        $this->createNewVersion($content, $changeSummary);
    }
}
