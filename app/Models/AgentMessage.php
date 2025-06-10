<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentMessage extends Model
{
    protected $fillable = ['session_id', 'agent_name', 'role', 'content', 'function_name', 'function_args'];

    protected $casts = [
        'function_args' => 'array',
    ];
}
