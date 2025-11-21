<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WordPressApiController;
use App\Http\Controllers\NLPController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Route::post('/query', [ChatbotController::class, 'processQuery']);
// Route::post('/query', [WordPressApiController::class, 'handleQuery']);

// Route::post('/nlp-query', [NLPController::class, 'handleQuery']);

Route::post('/query', [NLPController::class, 'handleQuery']);