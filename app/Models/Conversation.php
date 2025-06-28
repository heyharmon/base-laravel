<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'openai_response_id'
    ];

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }
}
