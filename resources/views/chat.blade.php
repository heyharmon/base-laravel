<div>
    <form method="POST" action="{{ route('start-session') }}">
        @csrf
        <div class="mb-4">
            <label for="task">Task</label>
            <input id="task" name="task" type="text" required size="50"/>
        </div>
        <br>
        <button type="submit">Start Session</button>
    </form>

    <p>Write a tweet on how to make legit mexican style salsa.</p>
    <p>Write a 2,000 page article detailing what generative engine optimization (GEO) is and how to succeed at it.</p>
</div>
