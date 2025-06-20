<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MultiAgentController;

Route::get('/', function () {
    return view('chat');
});

Route::post('/start-session', [MultiAgentController::class, 'startSession'])->name('start-session');
Route::get('/view-session/{id}', [MultiAgentController::class, 'viewSession'])->name('view-session');
Route::post('/session/{id}/reply', [MultiAgentController::class, 'userReply'])->name('session.reply');
Route::post('/stop-session/{id}', [MultiAgentController::class, 'stopSession'])->name('stop-session');

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
