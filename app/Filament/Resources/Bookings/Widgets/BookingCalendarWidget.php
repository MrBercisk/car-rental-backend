<?php

namespace App\Filament\Resources\Bookings\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Guava\Calendar\Filament\CalendarWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BookingCalendarWidget extends CalendarWidget
{
    /**
     * Aktifkan event klik -- tanpa ini, klik ke event gak ngapa-ngapain.
     */
    protected bool $eventClickEnabled = true;

    /**
     * FullCalendar/vkurko minta event dalam rentang tanggal yang lagi
     * ditampilkan ($info->start / $info->end) -- filter di database,
     * jangan ambil semua booking sekaligus.
     */
    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        return Booking::query()
            ->with(['unit.product'])
            ->where('start_date', '<=', $info->end)
            ->where('end_date', '>=', $info->start)
            ->get()
            ->map(function (Booking $booking) {
                return CalendarEvent::make($booking)
                    ->title($this->buildEventTitle($booking))
                    ->start($booking->start_date)
                    // all-day event: end bersifat eksklusif, jadi ditambah
                    // 1 hari supaya tanggal end_date ikut kehitung tampil.
                    ->end($booking->end_date->copy()->addDay())
                    ->backgroundColor($this->getColorForBooking($booking));
            })
            ->all();
    }

    /**
     * Klik event -> langsung redirect ke halaman edit booking yang sudah
     * kita bangun (bukan modal edit bawaan package), karena form booking
     * kita punya banyak section & relation manager yang gak cocok
     * dipaksa masuk ke modal generik.
     */
    protected function onEventClick(EventClickInfo $info, Model $event, ?string $action = null): void
    {
        $this->redirect(BookingResource::getUrl('edit', ['record' => $event]));
    }

    protected function buildEventTitle(Booking $booking): string
    {
        $mobil = $booking->unit?->product?->name ?? 'Mobil';
        $plat = $booking->unit?->license_plate ?? '-';

        if ($booking->source === 'maintenance') {
            return "🔧 Servis: {$mobil} ({$plat})";
        }

        $nama = $booking->customer_name ?: 'Tanpa nama';

        return "{$mobil} ({$plat}) - {$nama}";
    }

    protected function getColorForBooking(Booking $booking): string
    {
        if ($booking->source === 'maintenance') {
            return '#f97316'; // oranye -- blokir servis
        }

        return match ($booking->status) {
            'pending' => '#9ca3af',   // abu
            'dp' => '#eab308',        // kuning
            'lunas' => '#22c55e',     // hijau
            'confirmed' => '#3b82f6', // biru
            'cancelled' => '#ef4444', // merah
            default => '#9ca3af',
        };
    }
}