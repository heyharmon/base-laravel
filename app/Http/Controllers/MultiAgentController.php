<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\AgentMessage;
use App\Jobs\RunManagerAgent;

class MultiAgentController extends Controller
{
    public function startSession(Request $request)
    {
        $request->validate(['task' => 'required|string']);
        $sessionId = (string) Str::uuid();  // generate a unique session ID
        $task = $request->input('task');
        // Dispatch the manager agent job
        RunManagerAgent::dispatch($sessionId, $task);
        // Redirect to view the session (could also redirect and use polling to check completion)
        return redirect()->route('view-session', ['id' => $sessionId]);
    }

    public function viewSession($id)
    {
        // Fetch all messages for this session to display
        $messages = AgentMessage::where('session_id', $id)->orderBy('created_at')->get();
        return view('session', ['messages' => $messages, 'sessionId' => $id]);
    }
}
