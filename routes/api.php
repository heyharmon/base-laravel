<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\WebSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/conversations', [ConversationController::class, 'index']);
Route::post('/conversations', [ConversationController::class, 'store']);
Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
Route::post('/conversations/{conversation}/message', [ConversationController::class, 'sendMessage']);
Route::get('/conversations/{conversation}/stats', [ConversationController::class, 'stats']);

Route::get('/conversations/{conversation}/chats', [ChatController::class, 'index']);
Route::get('/conversations/{conversation}/chats/{chat}', [ChatController::class, 'show']);
Route::get('/conversations/{conversation}/chats/{chat}/job-status', [ChatController::class, 'jobStatus']);

Route::get('/articles', [ArticleController::class, 'index']);
Route::post('/articles', [ArticleController::class, 'store']);
Route::get('/articles/{article}', [ArticleController::class, 'show']);
Route::put('/articles/{article}', [ArticleController::class, 'update']);
Route::delete('/articles/{article}', [ArticleController::class, 'destroy']);
Route::get('/articles/{article}/export', [ArticleController::class, 'export']);

Route::post('/web-search', [WebSearchController::class, 'search']);
Route::post('/fetch-webpage', [WebSearchController::class, 'fetchPage']);
