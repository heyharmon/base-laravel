<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'youtube_channel_id'];

    public function feeds(): BelongsToMany
    {
        return $this->belongsToMany(Feed::class);
    }
}
