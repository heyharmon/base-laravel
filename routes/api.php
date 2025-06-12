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

Route::get('/feeds/{feed}', [\App\Http\Controllers\FeedController::class, 'show']);
