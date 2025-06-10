<h1>Multi-Agent Session: {{ $sessionId }}</h1>

<form method="POST" action="{{ route('stop-session', ['id' => $sessionId]) }}">
    @csrf
    <button type="submit">Stop</button>
</form>

@if($messages->isEmpty())
    <p>Session in progress... (If this page was loaded immediately, the agents are still working. Refresh periodically.)</p>
@endif

@foreach($messages as $msg)
    <p>
      <strong>{{ $msg->agent_name }} ({{ $msg->role }}):</strong>
      {{ $msg->role === 'function'
           ? '🤖 [Function Call] ' . $msg->function_name . '(' . json_encode($msg->function_args) . ')'
           : $msg->content
      }}
    </p>
@endforeach

@if(!$messages->isEmpty() && $messages->last()->agent_name === 'Manager' && $messages->last()->role === 'assistant')
    <p><em>--- End of conversation. Final answer above. ---</em></p>
@endif

<form method="POST" action="/start-session">
    @csrf
    <label>New Task for Agents:</label><br>
    <input type="text" name="task" size="50" placeholder="e.g. 'Design a mobile app for online shopping'">
    <button type="submit">Start New Session</button>
</form>
