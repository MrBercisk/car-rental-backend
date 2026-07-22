<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

  
    #[On('booking-payment-updated')]
    public function refreshAfterPayment(): void
    {
        $this->record = $this->record->fresh();
        $this->fillForm();
    }

    protected function beforeSave(): void
    {
        $data = $this->data;

        $overlap = Booking::overlapping(
            $data['product_unit_id'],
            $data['start_date'],
            $data['end_date'],
            $this->record->id,
        )->exists();

        if ($overlap) {
            Notification::make()
                ->title('Tanggal bentrok')
                ->body('Unit mobil ini sudah ada booking aktif lain di rentang tanggal tersebut.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}