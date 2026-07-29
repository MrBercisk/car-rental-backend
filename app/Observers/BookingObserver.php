<?php

namespace App\Observers;

use App\Models\Booking;
use App\Notifications\NewBookingNotification;
use Illuminate\Support\Facades\Notification;

class BookingObserver
{
   
    /* notiifkasi email cuma dari form user */
    public function created(Booking $booking): void
    {
        if ($booking->source !== 'form') {
            return;
        }

        try {
            Notification::route('mail', config('notifications.admin_email'))
                ->notify(new NewBookingNotification($booking));
        } catch (\Throwable $e) {
            // Gagal kirim notifikasi tidak bikin proses booking ikut gagal.
            report($e);
        }
    }
}