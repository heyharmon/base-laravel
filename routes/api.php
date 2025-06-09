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

Route::post('/chat', [\App\Http\Controllers\AgentController::class, 'chat']);
Route::apiResource('articles', \App\Http\Controllers\ArticleController::class);
