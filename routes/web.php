<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MultiAgentController;

Route::get('/', function () {
    return view('chat');
});

Route::post('/start-session', [MultiAgentController::class, 'startSession'])->name('start-session');
Route::get('/view-session/{id}', [MultiAgentController::class, 'viewSession'])->name('view-session');
// Endpoint used by the Stop button to cancel outstanding jobs for a session
Route::post('/stop-session/{id}', [MultiAgentController::class, 'stopSession'])->name('stop-session');

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
