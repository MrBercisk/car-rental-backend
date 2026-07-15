<?php

use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TestimonialController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/settings', SettingController::class . '@index')->name('settings.index');

    Route::get('/banners', [BannerController::class, 'index'])->name('banners.index');

    Route::get('/testimonials', [TestimonialController::class, 'index'])->name('testimonials.index');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{slug}', [CategoryController::class, 'show'])
        ->where('slug', '[a-z0-9\-]+')
        ->name('categories.show');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{slug}', [ProductController::class, 'show'])
        ->where('slug', '[a-z0-9\-]+')
        ->name('products.show');

    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:contact')
        ->name('contact.store');
});