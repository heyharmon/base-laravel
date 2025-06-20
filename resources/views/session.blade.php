{{-- Shows progress and controls for a single multi-agent session --}}
<h1>Multi-Agent Session: {{ $sessionId }}</h1>

<form method="POST" action="{{ route('stop-session', ['id' => $sessionId]) }}">
    @csrf
    <!-- Allows the user to cancel the batch of agent jobs -->
    <button type="submit">Stop</button>
</form>

@if($messages->isEmpty())
    <p>Session in progress... (If this page was loaded immediately, the agents are still working. Refresh periodically.)</p>
@endif

<!-- Conversation Messages -->
@foreach($messages as $msg)
    <p>
      <strong>{{ $msg->agent_name }} ({{ $msg->role }}):</strong>
      {{ $msg->role === 'function'
           ? '⚡ [Function Call] ' . $msg->function_name . '(' . json_encode($msg->function_args) . ')'
           : nl2br(e($msg->content))
      }}
    </p>
@endforeach

{{-- End of conversation --}}
@if(!$messages->isEmpty() && $messages->last()->agent_name === 'Manager' && $messages->last()->role === 'assistant')

@endif

<!-- User Reply Form (disable if session completed) -->
{{-- @if($batch && !$batch->finished()) --}}
<form action="{{ route('session.reply', $sessionId) }}" method="POST" class="reply-form">
    @csrf
    <input type="text" name="message" placeholder="Type a clarification or question..." size="50"/>
    <button type="submit">
        Send
    </button>
</form>
{{-- @endif --}}

<!-- New Session Form (disable if session is running) -->
{{-- @if(!$batch || $batch->finished())
<p><em>--- End of conversation. Final answer above. ---</em></p>
<form method="POST" action="/start-session">
    @csrf
    <label>New Task for Agents:</label><br>
    <input type="text" name="task" size="50" placeholder="e.g. 'Design a mobile app for online shopping'">
    <button type="submit">Start New Session</button>
</form>
@endif --}}
