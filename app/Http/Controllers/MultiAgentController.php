<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Jobs\ManagerAgentStepJob;

class MultiAgentController extends Controller
{
    public function startSession(Request $request)
    {
        $request->validate(['task' => 'required|string']);
        $sessionId = (string) Str::uuid();
        $task = $request->input('task');

        $batch = Bus::batch([
            new ManagerAgentStepJob($sessionId, $task),
        ])->dispatch();

        AgentSession::create([
            'session_id' => $sessionId,
            'batch_id' => $batch->id,
        ]);

        return redirect()->route('view-session', ['id' => $sessionId]);
    }

    public function viewSession($id)
    {
        // Fetch all messages for this session to display
        $messages = AgentMessage::where('session_id', $id)->orderBy('created_at')->get();
        return view('session', ['messages' => $messages, 'sessionId' => $id]);
    }

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
