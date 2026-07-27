<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Product;
use App\Models\CarPackage;
use App\Models\ProductUnit;
use App\Models\Setting;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            ComponentsSection::make('Detail Sewa')
                ->columns(2)
                ->schema([
                    Select::make('product_id_temp')
                        ->label('Mobil (Model)')
                        ->options(fn () => Product::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->dehydrated(false)
                        ->disabled(fn (?Booking $record) => $record?->isLocked())
                        ->afterStateHydrated(function ($component, ?Booking $record) {
                            if ($record?->unit) {
                                $component->state($record->unit->product_id);
                            }
                        })
                        ->afterStateUpdated(fn ($set) => $set('product_unit_id', null)),

                    Select::make('product_unit_id')
                        ->label('Unit / Plat Nomor')
                        ->options(function ($get) {
                            $productId = $get('product_id_temp');
                            if (! $productId) {
                                return [];
                            }

                            return ProductUnit::where('product_id', $productId)
                                ->active()
                                ->ordered()
                                ->get()
                                ->mapWithKeys(fn ($unit) => [
                                    $unit->id => $unit->license_plate . ($unit->color ? " - {$unit->color}" : ''),
                                ]);
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabled(fn ($get, ?Booking $record) => ! $get('product_id_temp') || $record?->isLocked())
                        ->helperText('Hanya unit berstatus Aktif yang muncul. Pengecekan tabrakan tanggal dilakukan saat simpan.'),

                    Select::make('package_id')
                        ->label('Paket Sewa')
                        ->options(function ($get) {
                            $productId = $get('product_id_temp');
                            if (! $productId) {
                                return [];
                            }

                            return CarPackage::with('package')
                                ->where('product_id', $productId)
                                ->get()
                                ->pluck('package.name', 'package_id');
                        })
                        ->searchable()
                        ->live()
                        ->disabled(fn ($get) => ! $get('product_id_temp'))
                        ->afterStateUpdated(function ($state, $get, $set) {
                            if (! $state || ! $get('product_id_temp')) {
                                return;
                            }

                            $productPackage = CarPackage::with('package')
                                ->where('product_id', $get('product_id_temp'))
                                ->where('package_id', $state)
                                ->first();

                            if ($productPackage) {
                                $set('package_label', $productPackage->package->name);
                                $set('package_price', $productPackage->price);
                            }

                            // Paket berubah -> kalau toggle supir lagi aktif,
                            // biaya supir ikut disesuaikan ke tarif paket yang baru.
                            static::syncDriverFee($get, $set);
                        }),

                    DatePicker::make('start_date')
                        ->label('Tanggal Mulai')
                        ->required()
                        ->disabled(fn (?Booking $record) => $record?->isLocked()),

                    DatePicker::make('end_date')
                        ->label('Tanggal Selesai')
                        ->disabled()
                        ->afterOrEqual('start_date')
                        ->disabled(fn (?Booking $record) => $record?->isLocked()),

                    TextInput::make('package_label')
                        ->label('Nama Paket (snapshot)')
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Terisi otomatis dari pilihan paket. Tidak berubah walau harga master diubah nanti.'),

                    TextInput::make('package_price')
                        ->label('Harga Paket (snapshot)')
                        ->prefix('Rp')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Nilai historis, tidak mengikuti perubahan harga master.'),

                    Toggle::make('with_driver')
                        ->label('Sewa dengan Supir')
                        ->live()
                        ->disabled(fn (?Booking $record) => $record?->isLocked())
                        ->afterStateUpdated(function ($get, $set) {
                            static::syncDriverFee($get, $set);
                        }),

                    TextInput::make('driver_surcharge_price')
                        ->label('Biaya Supir (snapshot)')
                        ->prefix('Rp')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->visible(fn ($get) => (bool) $get('with_driver'))
                        ->helperText('Diambil dari biaya supir paket terkait (atau default Pengaturan kalau paket belum diisi). Nilai historis, tidak berubah walau diedit nanti.'),

                    Placeholder::make('total_price_display')
                        ->label('Total Harga Sewa')
                        ->content(function ($get) {
                            $package = (int) ($get('package_price') ?? 0);
                            $driver = $get('with_driver') ? (int) ($get('driver_surcharge_price') ?? 0) : 0;

                            return 'Rp ' . number_format($package + $driver, 0, ',', '.');
                        })
                        ->columnSpanFull(),
                ]),

            ComponentsSection::make('Status & Sumber Booking')
                ->columns(3)
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'pending' => 'Pending',
                            'dp' => 'DP',
                            'lunas' => 'Lunas',
                            'confirmed' => 'Dikonfirmasi',
                            'cancelled' => 'Dibatalkan',
                        ])
                        ->required()
                        ->default('pending')
                        ->disabled(fn (?Booking $record) => $record?->isLocked()),

                    Select::make('source')
                        ->label('Sumber')
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'form' => 'Form Website',
                            'admin' => 'Input Admin',
                            'maintenance' => 'Blokir Servis / Maintenance',
                        ])
                        ->required()
                        ->live()
                        ->default('admin')
                        ->helperText('Pilih "Blokir Servis" untuk mengunci tanggal unit tanpa booking customer (mis. mobil masuk bengkel).'),

                    Placeholder::make('amount_paid_display')
                        ->label('Total Terbayar (cache)')
                        ->content(fn (?Booking $record) => 'Rp ' . number_format($record?->amount_paid ?? 0, 0, ',', '.'))
                        ->helperText('Dihitung otomatis dari tab Pembayaran. Tidak bisa diedit langsung di sini.'),
                ]),

            ComponentsSection::make('Data Pelanggan')
                ->columns(2)
                ->schema([
                    TextInput::make('customer_name')
                        ->label('Nama Pelanggan')
                        ->maxLength(255),

                    TextInput::make('customer_phone')
                        ->label('No. HP / WhatsApp')
                        ->tel()
                        ->maxLength(50),

                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            ComponentsSection::make('Bukti Pembayaran Manual')
                ->columns(1)
                ->visible(fn () => config('booking.payment_proof_enabled'))
                ->schema([
                    FileUpload::make('payment_proof_path')
                        ->label('Upload Bukti Transfer')
                        ->image()
                        ->directory('booking-proofs')
                        ->disabled(fn (?Booking $record) => $record?->isLocked()),
                ]),

            ComponentsSection::make('Payment Gateway')
                ->columns(2)
                ->visible(fn () => config('booking.gateway_enabled'))
                ->description('Field ini terisi otomatis lewat webhook gateway. Tidak untuk diedit manual.')
                ->schema([
                    TextInput::make('payment_gateway')->label('Gateway')->disabled(),
                    TextInput::make('gateway_order_id')->label('Order ID')->disabled(),
                    TextInput::make('gateway_transaction_id')->label('Transaction ID')->disabled(),
                    TextInput::make('gateway_status')->label('Status Gateway')->disabled(),
                    TextInput::make('gateway_payment_method')->label('Metode Bayar')->disabled(),
                    TextInput::make('gross_amount')->label('Gross Amount')->prefix('Rp')->numeric()->disabled(),
                ]),

            ComponentsSection::make('Closing')
                ->columns(1)
                ->schema([
                    DateTimePicker::make('locked_at')
                        ->label('Kunci Booking (Closing)')
                        ->helperText('Setelah dikunci, tanggal/status/harga tidak bisa diubah lagi lewat form ini. Koreksi setelah closing harus lewat entri baru di tab Pembayaran.')
                        ->native(false),
                ]),
        ]);
    }

    /**
     * Hitung ulang driver_surcharge_price berdasarkan paket yang lagi dipilih
     */
    protected static function syncDriverFee($get, $set): void
    {
        if (! $get('with_driver')) {
            $set('driver_surcharge_price', 0);
 
            return;
        }
 
        $packageId = $get('package_id');
        $fee = 0;
 
        if ($packageId) {
            $package = Package::find($packageId);
            $fee = $package?->effective_driver_fee ?? 0;
        }
 
        if ($fee <= 0) {
            // Belum ada paket dipilih, atau paket itu & Settings dua-duanya kosong.
            $fee = (int) Setting::get('driver_surcharge', 0);
        }
 
        $set('driver_surcharge_price', (int) $fee);
    }
    protected static function syncEndDate($get, $set): void
    {
        $startDate = $get('start_date');
        $packageId = $get('package_id');
        if (!$startDate || !$packageId) return;

        $package = \App\Models\Package::find($packageId);
        if (!$package) return;

        $start = \Carbon\Carbon::parse($startDate);
        $end = $package->duration_unit === 'hour'
            ? $start->copy()
            : $start->copy()->addDays(max(0, $package->duration_value - 1));

        $set('end_date', $end->toDateString());
    }

}