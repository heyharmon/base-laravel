<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

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

Route::get('/chats', [ChatController::class, 'index']);
Route::post('/chats', [ChatController::class, 'store']);
