<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentSession extends Model
{
    protected $fillable = [
        'session_id',
        'batch_id',
    ];
}
