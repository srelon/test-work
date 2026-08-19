<?php

use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::prefix('comments')->controller(CommentController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
});
