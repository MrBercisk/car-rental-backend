<?php

namespace App\Providers;

use App\Models\Booking;
use App\Observers\BookingObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('contact', function (Request $request) {
            $key = $request->ip() . '|' . strtolower((string) $request->input('email', ''));

            return Limit::perMinutes(10, 3)->by($key);
        });

        // batas limit per ip untuk chat ai per menit 6
        RateLimiter::for('assistant-chat', function(Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        // batas limit per ip untuk book per jam 5
        RateLimiter::for('booking-submit', function(Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        // batas limit per ip untuk book per jam 5
        RateLimiter::for('booking-cancel', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });


        Booking::observe(BookingObserver::class);
    }
}