<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/conversations', [ConversationController::class, 'index']);
Route::post('/conversations', [ConversationController::class, 'store']);
Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
Route::post('/conversations/{conversation}/message', [ConversationController::class, 'sendMessage']);
Route::get('/conversations/{conversation}/stats', [ConversationController::class, 'stats']);

Route::get('/conversations/{conversation}/articles', [ArticleController::class, 'index']);
Route::get('/conversations/{conversation}/articles/{article}', [ArticleController::class, 'show']);
Route::get('/conversations/{conversation}/articles/{article}/version/{version}', [ArticleController::class, 'version']);
Route::get('/conversations/{conversation}/articles/{article}/export', [ArticleController::class, 'export']);

Route::get('/conversations/{conversation}/chats', [ChatController::class, 'index']);
Route::get('/conversations/{conversation}/chats/{chat}', [ChatController::class, 'show']);
Route::get('/conversations/{conversation}/chats/{chat}/job-status', [ChatController::class, 'jobStatus']);
