<?php

use Illuminate\Support\Facades\Route;
use App\Jobs\RunAgent;
use App\Models\AgentMemory;

Route::get('/conversation', function () {
    $memories = AgentMemory::all();

    return view('conversation', compact('memories'));
});


Route::get('/start-collaboration', function () {
    // Clear previous memory
    AgentMemory::truncate();

    // Kick off with Product Manager
    dispatch(new RunAgent(
        'manager',
        config('agents.roles')['manager'],
        'Write a tweet about SpaceX'
    ));

    return redirect('/conversation');
});

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
