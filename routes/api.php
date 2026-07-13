<?php

use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TestimonialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - dikonsumsi oleh Frontend React TypeScript
|--------------------------------------------------------------------------
| Semua route di bawah ini publik (read-only) kecuali POST /contact.
| Tambahkan middleware throttle sesuai kebutuhan produksi.
*/

Route::prefix('v1')->group(function () {
    Route::get('/settings', SettingController::class . '@index');

    Route::get('/banners', [BannerController::class, 'index']);

    Route::get('/testimonials', [TestimonialController::class, 'index']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    Route::post('/contact', [ContactController::class, 'store']);
});
