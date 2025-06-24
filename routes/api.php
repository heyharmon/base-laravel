<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

// Chat routes
Route::get('/conversations', [ChatController::class, 'listConversations']);
Route::post('/conversations', [ChatController::class, 'createConversation']);
Route::get('/conversations/{conversation}', [ChatController::class, 'getConversation']);
Route::put('/conversations/{conversation}/context', [ChatController::class, 'updateContext']);
Route::post('/conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);

// Article routes (for frontend)
Route::apiResource('articles', ArticleController::class);

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
