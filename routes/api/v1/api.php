<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;

// Registering, Logging In/Logging Out Routes

Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});
Route::middleware('auth:sanctum', 'ability:user:only,admin:only')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum', 'ability:user:only,admin:only')->post('/token/refresh', [AuthController::class, 'refreshToken']);

// User Routes

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum', 'ability:client:only,admin:only');
