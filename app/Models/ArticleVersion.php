<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'version_number',
        'content',
        'change_summary',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
