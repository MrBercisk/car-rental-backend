<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->data;

        $overlap = Booking::overlapping(
            $data['product_unit_id'],
            $data['start_date'],
            $data['end_date'],
        )->exists();

        if ($overlap) {
            Notification::make()
                ->title('Tanggal bentrok')
                ->body('Unit mobil ini sudah ada booking aktif di rentang tanggal tersebut.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}