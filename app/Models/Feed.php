<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feed extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class);
    }
}
