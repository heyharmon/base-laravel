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

use App\Http\Controllers\FeedController;

Route::get('/feeds', [FeedController::class, 'index']);
Route::post('/feeds', [FeedController::class, 'store']);
Route::put('/feeds/{feed}', [FeedController::class, 'update']);
Route::delete('/feeds/{feed}', [FeedController::class, 'destroy']);
Route::get('/feeds/{feed}', [FeedController::class, 'show']);
