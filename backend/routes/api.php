<?php

use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::prefix('comments')->controller(CommentController::class)->group(function () {
    Route::get('/', 'index')->middleware('throttle:60,1');
    Route::post('/', 'store')->middleware('throttle:10,1');
    Route::get('{comment}/replies', 'replies')->middleware('throttle:60,1');
});
