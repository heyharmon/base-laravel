<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentMemory extends Model
{
    protected $fillable = ['agent_name', 'memory'];

    protected $casts = [
        'memory' => 'array',
    ];
}
