<?php

use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DokuNotificationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TestimonialController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // setting
    Route::get('/settings', SettingController::class . '@index')->name('settings.index');

    // banners
    Route::get('/banners', [BannerController::class, 'index'])->name('banners.index');

    // testi
    Route::get('/testimonials', [TestimonialController::class, 'index'])->name('testimonials.index');
    Route::post('/testimonials', [TestimonialController::class, 'store']);

    // kategori
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{slug}', [CategoryController::class, 'show'])
        ->where('slug', '[a-z0-9\-]+')
        ->name('categories.show');

    // produk
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{slug}', [ProductController::class, 'show'])
        ->where('slug', '[a-z0-9\-]+')
        ->name('products.show');

    Route::get('/products/{slug}/availability', [ProductController::class, 'availability'])
    ->where('slug', '[a-z0-9\-]+');
 
    Route::post('/bookings/payment', [BookingController::class, 'payNow']);
    
    // Webhook
    Route::post('/doku/notification', [DokuNotificationController::class, 'handle']);
    

    // boooking
    Route::get('/booking-config', [BookingController::class, 'config']);
    Route::post('/bookings', [BookingController::class, 'store'])
        ->middleware('throttle:booking-submit');

    Route::get('/bookings/invoice/{invoiceNumber}', [BookingController::class, 'showByInvoice']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);

    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
    ->middleware('throttle:booking-cancel');
    // kontak
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:contact')
        ->name('contact.store');
});