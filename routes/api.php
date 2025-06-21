<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'Hello world';
});

Route::get('/colors', function () {
    return [
        'red',
        'green',
        'blue',
        'yellow',
        'purple',
        'orange',
    ];
});

use App\Http\Controllers\Api\ConversationController;

Route::post('/conversations', [ConversationController::class, 'store']);
Route::get('/conversations/{conversation}/chats', [ConversationController::class, 'chats']);
Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
