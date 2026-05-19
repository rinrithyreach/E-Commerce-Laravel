<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminVendorController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Vendor\VendorApplicationController;
use App\Http\Controllers\Vendor\VendorProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// public routes

// Deep Link Callback URL hit by your Flutter app to confirm verification
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->name('api.verification.verify');

// category catalog for public

Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
});

// product public endpoints

Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{product}', [ProductController::class, 'show']);
});


Route::prefix('auth')->group(function (){
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-reset-otp', [AuthController::class, 'verifyResetOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/logout',[AuthController::class,'logout'])->middleware('auth:sanctum');
});





Route::middleware('auth:sanctum')->group(function (){

    Route::post('/vendor/apply',[VendorApplicationController::class,'apply']);
});


// vendor endpoints

Route::prefix('vendor')->middleware(['auth:sanctum', 'role:vendor,sanctum'])->group(function () {
    Route::prefix('products')->group(function () {
        Route::get('/',[VendorProductController::class,'index']);
        Route::post('/',[VendorProductController::class,'store']);
        Route::get('/{product}',[VendorProductController::class,'show']);
        Route::post('/{product}',[VendorProductController::class,'update']);
        Route::delete('/{product}',[VendorProductController::class,'destroy']);
        Route::post('/{product}/restore', [VendorProductController::class, 'restore']);
    });

});

// admin endpoints

Route::prefix('admin')->middleware(['auth:sanctum', 'role:super-admin,sanctum'])->group(function () {

    Route::prefix('vendors')->group(function () {
        Route::get('/', [AdminVendorController::class, 'index']);
        Route::get('/{vendor}', [AdminVendorController::class, 'show']);
        Route::post('/{vendor}/approve', [AdminVendorController::class, 'approve']);
        Route::post('/{vendor}/reject', [AdminVendorController::class, 'reject']);
    });

    Route::prefix('notifications')->group(function (){
        Route::get("/",[AdminNotificationController::class,'index']);
        Route::patch("/{id}/mark-as-read", [AdminNotificationController::class, 'markAsRead']);
    });

    Route::prefix('categories')->group(function () {
        Route::get('/',[AdminCategoryController::class,'index']);
        Route::post('/',[AdminCategoryController::class,'store']);
        Route::post('/{category}',[AdminCategoryController::class,'update']);
        Route::delete('/{category}', [AdminCategoryController::class,'destroy']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/',[AdminProductController::class,'index']);
        Route::post('/disable/{product}',[AdminProductController::class,'disable']);
        Route::post('/enable/{product}',[AdminProductController::class,'enable']);
        Route::get('/disabled',[AdminProductController::class,'getAllDisables']);
    });

});






