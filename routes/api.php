<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function (){
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout',[AuthController::class,'logout'])->middleware('auth:sanctum');
});

// Deep Link Callback URL hit by your Flutter app to confirm verification
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->name('api.verification.verify');
