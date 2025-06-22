<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any?}', function () {
    if (app()->environment('testing')) {
        return 'ok';
    }

    return view('app');
})->where('any', '.*');
