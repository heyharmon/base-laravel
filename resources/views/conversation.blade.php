<!DOCTYPE html>
<html>
<head>
    <title>Multi-Agent Conversation</title>
</head>
<body>
    <h1>Multi-Agent Collaboration</h1>

    @foreach ($memories as $memory)
        <h2>{{ ucfirst(str_replace('_', ' ', $memory->agent_name)) }}</h2>
        <ul>
            @foreach ($memory->memory as $msg)
                <li><strong>{{ $msg['role'] }}:</strong> {{ $msg['content'] }}</li>
            @endforeach
        </ul>
    @endforeach

    <a href="/start-collaboration">Restart Collaboration</a>
</body>
</html>
