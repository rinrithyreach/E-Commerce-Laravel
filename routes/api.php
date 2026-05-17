<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WishlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->name('api.verification.verify');

Route::post('/payments/stripe/webhook', [PaymentController::class, 'stripeWebhook']);

Route::middleware('throttle:api')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/stores/{store}', [StoreController::class, 'show']);
    Route::get('/reviews', [ReviewController::class, 'index']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => response()->json([
        'status' => true,
        'data' => [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'role' => $request->user()->getRoleNames()->first(),
            'permissions' => $request->user()->getAllPermissions()->pluck('name'),
            'is_verified' => $request->user()->hasVerifiedEmail(),
        ],
    ]));
});

Route::middleware(['auth:sanctum', 'throttle:api', 'role:customer|vendor|super-admin'])->prefix('customer')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'customer']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::apiResource('addresses', AddressController::class)->except(['create', 'edit']);

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::put('/cart/{cart}', [CartController::class, 'update']);
    Route::delete('/cart/{cart}', [CartController::class, 'destroy']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem']);

    Route::apiResource('wishlist', WishlistController::class)->except(['create', 'edit']);
    Route::delete('/wishlist/product/{product}', [WishlistController::class, 'removeProduct']);

    Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show']);
    Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);
    Route::apiResource('refunds', RefundController::class)->only(['index', 'store']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show']);
    Route::apiResource('shipments', ShippingController::class)->only(['index']);
    Route::apiResource('reviews', ReviewController::class)->except(['create', 'edit']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::apiResource('notifications', NotificationController::class)->only(['index', 'show', 'update', 'destroy']);
});

Route::middleware(['auth:sanctum', 'throttle:api', 'role:vendor|super-admin'])->prefix('vendor')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'vendor']);
    Route::apiResource('stores', StoreController::class)->except(['create', 'edit']);
    Route::apiResource('products', ProductController::class)->except(['create', 'edit']);
    Route::apiResource('product-variants', ProductVariantController::class)->except(['create', 'edit']);
    Route::apiResource('inventory', InventoryController::class)->only(['index', 'store', 'show']);
    Route::get('/orders', [OrderController::class, 'vendorIndex']);
    Route::patch('/orders/{order}', [OrderController::class, 'update']);
    Route::apiResource('shipments', ShippingController::class)->only(['index', 'update']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show']);
});

Route::middleware(['auth:sanctum', 'throttle:api', 'role:super-admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin']);
    Route::apiResource('vendors', VendorController::class)->except(['create', 'edit']);
    Route::apiResource('categories', CategoryController::class)->except(['create', 'edit']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::apiResource('payments', PaymentController::class)->except(['create', 'edit', 'destroy']);
    Route::apiResource('refunds', RefundController::class)->only(['index', 'store', 'update']);
    Route::apiResource('coupons', CouponController::class)->except(['create', 'edit']);
    Route::post('/coupons/validate', [CouponController::class, 'validateCode']);
    Route::apiResource('shipments', ShippingController::class)->only(['index', 'update']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show']);
});
