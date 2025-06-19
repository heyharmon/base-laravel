<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Agents\ManagerAgent;

/**
 * Controller exposing routes to start, view and stop multi-agent sessions.
 */
class MultiAgentController extends Controller
{
    /**
     * Begin a new multi-agent session and dispatch the first manager step.
     */
    public function startSession(Request $request)
    {
        $request->validate(['task' => 'required|string']);
        $sessionId = (string) Str::uuid();
        $task = $request->input('task');

        $batch = Bus::batch([
            new ManagerAgent($sessionId, $task),
        ])->dispatch();

        AgentSession::create([
            'session_id' => $sessionId,
            'batch_id' => $batch->id,
        ]);

        return redirect()->route('view-session', ['id' => $sessionId]);
    }

    /**
     * Display all messages that have been generated for a session.
     */
    public function viewSession($id)
    {
        // Fetch all messages for this session to display
        $messages = AgentMessage::where('session_id', $id)->orderBy('created_at')->get();
        return view('session', ['messages' => $messages, 'sessionId' => $id]);
    }

    /**
     * Cancel all queued jobs for the given session via batch cancellation.
     */
    public function stopSession($id)
    {
        $session = AgentSession::where('session_id', $id)->first();
        if ($session) {
            if ($batch = Bus::findBatch($session->batch_id)) {
                $batch->cancel();
            }
        }
        return redirect()->route('view-session', ['id' => $id]);
    }
}
