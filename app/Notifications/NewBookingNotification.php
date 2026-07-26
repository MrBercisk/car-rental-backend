<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewBookingNotification extends Notification
{
    public function __construct(protected Booking $booking)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return config('notifications.admin_email_enabled') ? ['mail'] : [];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $b = $this->booking->loadMissing('unit.product');
        $unitLabel = $b->unit
            ? "{$b->unit->product?->name} ({$b->unit->license_plate})"
            : 'Mobil';

        return (new MailMessage)
            ->subject("Booking Baru Masuk - {$unitLabel}")
            ->greeting('Ada reservasi baru! 🎉')
            ->line("Mobil: {$unitLabel}")
            ->line("Tanggal: {$b->start_date->format('d M Y')} - {$b->end_date->format('d M Y')}")
            ->line('Paket: ' . ($b->package_label ?? '-'))
            ->line('Dengan Supir: ' . ($b->with_driver ? 'Ya' : 'Tidak'))
            ->line('Total: Rp ' . number_format($b->total_price, 0, ',', '.'))
            ->line("Nama Customer: {$b->customer_name}")
            ->line("No. HP: {$b->customer_phone}")
            ->action('Lihat Booking di Admin', url("/admin/bookings/{$b->id}/edit"))
            ->line('Segera konfirmasi ke customer ya.');
    }
}