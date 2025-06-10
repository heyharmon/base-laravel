<div class="flex flex-col items-center justify-center min-h-screen bg-neutral-100">
    <form method="POST" action="{{ route('start-session') }}" class="bg-neutral-50 p-8 rounded shadow-md w-full max-w-md">
        @csrf
        <div class="mb-4">
            <label for="task" class="block text-neutral-700 text-sm font-bold mb-2">Task</label>
            <input id="task" name="task" type="text" required class="shadow appearance-none border rounded w-full py-2 px-3 text-neutral-700 leading-tight focus:outline-none focus:shadow-outline" />
        </div>
        <button type="submit" class="bg-neutral-800 hover:bg-neutral-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Start Session</button>
    </form>
</div>
