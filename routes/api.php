<?php

use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminStockController;
use App\Http\Controllers\Admin\AdminTicketController;
use App\Http\Controllers\AI\AIDesignController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Customization\CustomizationAssetController;
use App\Http\Controllers\Customization\CustomizationController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Payments\StripeController;
use App\Http\Controllers\Support\ContactController;
use Illuminate\Support\Facades\Route;

/**
 * Public health check, used by the `infra/deploy-prod.sh` script and monitoring probes.
 *
 * @unauthenticated
 *
 * @response 200 {"status": "ok", "env": "production"}
 */
Route::get('/health', fn () => response()->json(['status' => 'ok', 'env' => app()->environment()]));

// Public auth
Route::post('/login', LoginController::class)->middleware('throttle:10,1')->name('login');
Route::post('/register', RegisterController::class)->middleware('throttle:10,1')->name('register');
Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:5,1')->name('password.email');
Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:5,1')->name('password.reset');

// Public data
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show'])->where('slug', '[A-Za-z0-9\-]+');

// Stripe webhook (public)
Route::post('/stripe/webhook', [StripeController::class, 'webhook']);

// Contact Form (public)
Route::post('/contact', ContactController::class)->middleware('throttle:5,1');

// Cart (public: works for both guests, via X-Guest-Cart-Token, and authenticated users)
Route::get('/cart', [CartController::class, 'show']);
Route::post('/cart/items', [CartController::class, 'add']);
Route::patch('/cart/items/{item}', [CartController::class, 'update']);
Route::delete('/cart/items/{item}', [CartController::class, 'destroy']);

// Customization (public: works for both guests, via X-Guest-Cart-Token, and authenticated users — AI generation stays login-only, see below)
Route::post('/customization/sessions', [CustomizationController::class, 'store']);
Route::get('/customization/sessions/{customizationSession}', [CustomizationController::class, 'show']);
Route::patch('/customization/sessions/{customizationSession}', [CustomizationController::class, 'update']);
Route::post('/customization/assets/logo', [CustomizationAssetController::class, 'uploadLogo'])->middleware('throttle:20,10');
Route::post('/customization/assets/image', [CustomizationAssetController::class, 'uploadImage'])->middleware('throttle:20,10');

// Checkout (public: guest checkout works via X-Guest-Cart-Token, same pattern as /cart)
Route::post('/payment/intent', [StripeController::class, 'createPaymentIntent'])->middleware('throttle:3,1');

// Checkout (public: guest checkout works via X-Guest-Cart-Token, same pattern as /cart)
Route::post('/payment/intent', [StripeController::class, 'createPaymentIntent'])->middleware('throttle:3,1');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', VerificationController::class)->name('me');
    Route::patch('/me', ProfileController::class)->name('profile.update');
    Route::post('/logout', LogoutController::class)->name('logout');

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);

    // AI (each call hits the paid OpenAI image API — capped per user to bound cost/abuse)
    Route::post('/ai/designs/generate', AIDesignController::class)->middleware('throttle:8,60');
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    /**
     * Admin ping
     *
     * @response 200 {"ok": true}
     * @response 403 {"message": "Forbidden"}
     */
    Route::get('/ping', fn () => response()->json(['ok' => true]));
    Route::get('/stats', [AdminOrderController::class, 'stats']);

    // Products
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::get('/products/{product}', [AdminProductController::class, 'show']);
    Route::patch('/products/{product}', [AdminProductController::class, 'update']);
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
    Route::patch('/products/{product}/toggle-active', [AdminProductController::class, 'toggleActive']);

    // Orders
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

    // Stock
    Route::get('/stock', [AdminStockController::class, 'list']);
    Route::get('/products/{product}/stock', [AdminStockController::class, 'index']);
    Route::post('/products/{product}/stock', [AdminStockController::class, 'store']);
    Route::patch('/stock/lots/{lot}/adjust', [AdminStockController::class, 'adjust']);
    Route::get('/products/{product}/stock/movements', [AdminStockController::class, 'movements']);

    // Support tickets
    Route::get('/tickets', [AdminTicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show']);
});
