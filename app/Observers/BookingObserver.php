<?php

namespace App\Observers;

use App\Models\Booking;
use App\Notifications\NewBookingNotification;
use Illuminate\Support\Facades\Notification;

class BookingObserver
{
    /**
     * Notifikasi admin CUMA dipicu untuk booking dari 'form' (customer isi
     * sendiri di website, tanpa admin tau duluan). Booking dengan source
     * 'admin'/'whatsapp'/'maintenance' TIDAK memicu notifikasi -- karena
     * itu dibuat/diketahui admin sendiri.
     */
    public function created(Booking $booking): void
    {
        if ($booking->source !== 'form') {
            return;
        }

        try {
            Notification::route('mail', config('notifications.admin_email'))
                ->notify(new NewBookingNotification($booking));
        } catch (\Throwable $e) {
            // Gagal kirim notifikasi TIDAK BOLEH bikin proses booking ikut gagal.
            report($e);
        }
    }
}