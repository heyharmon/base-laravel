<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks the association between a session of agent messages and the
 * corresponding queue batch id so we can cancel all jobs for that session.
 */
class AgentSession extends Model
{
    protected $fillable = [
        'session_id',
        'batch_id',
    ];
}
