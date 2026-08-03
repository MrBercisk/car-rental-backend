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


            ComponentsSection::make('1. Pilih Mobil & Jadwal Sewa')
                ->description('Pilih mobil, unit, dan paket sewa. Tanggal selesai & harga akan terisi otomatis.')
                ->icon('heroicon-o-truck')
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
                        ->placeholder('Pilih mobil dulu di atas')
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
                        ->placeholder('Pilih mobil dulu di atas')
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

                            // Paket berubah -> biaya supir & end_date ikut disesuaikan
                            static::syncDriverFee($get, $set);
                            static::syncEndDate($get, $set);
                        }),

                    TextInput::make('package_price')
                        ->label('Harga Paket')
                        ->prefix('Rp')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Terisi otomatis dari paket yang dipilih. Nilai ini tersimpan permanen (tidak berubah walau harga master diedit nanti).'),

                    DatePicker::make('start_date')
                        ->label('Tanggal Mulai')
                        ->required()
                        ->live()
                        ->native(false)
                        ->disabled(fn (?Booking $record) => $record?->isLocked())
                        ->afterStateUpdated(function ($get, $set) {
                            static::syncEndDate($get, $set);
                        }),

                    DatePicker::make('end_date')
                        ->label('Tanggal Selesai')
                        ->native(false)
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Otomatis: Tanggal Mulai + durasi paket. Tidak bisa diedit manual.'),

                    TextInput::make('package_label')
                        ->label('Nama Paket (tersimpan)')
                        ->disabled()
                        ->dehydrated()
                        ->columnSpanFull()
                        ->helperText('Catatan internal — nama paket ini tidak akan berubah walau nama/harga master diedit di kemudian hari.'),
                ]),


            ComponentsSection::make('2. Opsi Tambahan (Supir & Antar Jemput)')
                ->description('Centang / isi kalau ada biaya tambahan selain paket dasar.')
                ->icon('heroicon-o-plus-circle')
                ->columns(2)
                ->schema([
                    Toggle::make('with_driver')
                        ->label('Sewa dengan Supir')
                        ->live()
                        ->columnSpanFull()
                        ->disabled(fn (?Booking $record) => $record?->isLocked())
                        ->afterStateUpdated(function ($get, $set) {
                            static::syncDriverFee($get, $set);
                        }),

                    TextInput::make('driver_surcharge_price')
                        ->label('Biaya Supir')
                        ->prefix('Rp')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->visible(fn ($get) => (bool) $get('with_driver'))
                        ->columnSpanFull()
                        ->helperText('Otomatis dari biaya supir paket ini (atau tarif default kalau paket belum diisi).'),

                    TextInput::make('delivery_address')
                        ->label('Lokasi Antar')
                        ->placeholder('Kosongkan kalau customer ambil sendiri di garasi')
                        ->columnSpanFull()
                        ->helperText('Isi lokasi tujuan antar, misal: Bandara YIA, Kulon Progo.'),

                    TextInput::make('delivery_distance_km')
                        ->label('Jarak dari Garasi')
                        ->suffix('km')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.1)
                        ->live(onBlur: true)
                        ->disabled(fn (?Booking $record) => $record?->isLocked())
                        ->afterStateUpdated(function ($get, $set) {
                            static::syncDeliveryFee($get, $set);
                        })
                        ->helperText('Cek jaraknya di Google Maps, lalu isi angkanya di sini.'),

                    TextInput::make('delivery_fee_price')
                        ->label('Biaya Antar')
                        ->prefix('Rp')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('0–10 km Gratis · 10–20 km Rp50.000 · >20 km Rp5.000/km. Terhitung otomatis, tersimpan permanen.'),
                ]),

            ComponentsSection::make('3. Ringkasan Total Biaya')
                ->icon('heroicon-o-calculator')
                ->columns(1)
                ->schema([
                    Placeholder::make('total_price_display')
                        ->label('')
                        ->content(function ($get) {
                            $package = (int) ($get('package_price') ?? 0);
                            $driver = $get('with_driver') ? (int) ($get('driver_surcharge_price') ?? 0) : 0;
                            $delivery = (int) ($get('delivery_fee_price') ?? 0);
                            $total = $package + $driver + $delivery;

                            $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');

                            $lines = [
                                "Harga Paket: {$rp($package)}",
                            ];

                            if ($driver > 0) {
                                $lines[] = "Biaya Supir: {$rp($driver)}";
                            }

                            if ($delivery > 0) {
                                $lines[] = "Biaya Antar: {$rp($delivery)}";
                            }

                            $breakdown = implode(' + ', array_map(
                                fn ($n) => $rp($n),
                                array_filter([$package, $driver, $delivery], fn ($n) => $n > 0)
                            ));

                            return new \Illuminate\Support\HtmlString(
                                '<div class="space-y-1">'
                                . '<div class="text-sm text-gray-500">' . $breakdown . '</div>'
                                . '<div class="text-2xl font-bold">' . $rp($total) . '</div>'
                                . '</div>'
                            );
                        }),
                ]),

            ComponentsSection::make('4. Data Pelanggan')
                ->icon('heroicon-o-user')
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

            ComponentsSection::make('5. Status & Sumber Booking')
                ->icon('heroicon-o-clipboard-document-check')
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
                            'payment_gateway' => 'Payment Gateway',
                        ])
                        ->required()
                        ->live()
                        ->default('admin')
                        ->helperText('Pilih "Blokir Servis" untuk mengunci tanggal unit tanpa booking customer (mis. mobil masuk bengkel).'),

                    TextInput::make('gateway_order_id')
                        ->label('Nomor Invoice/Booking')
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Terisi otomatis saat booking dibuat. Prefix INV- = via payment gateway, BK- = manual/admin/WhatsApp. Kosong untuk Blokir Servis.'),

                    Placeholder::make('amount_paid_display')
                        ->label('Total Terbayar')
                        ->content(fn (?Booking $record) => 'Rp ' . number_format($record?->amount_paid ?? 0, 0, ',', '.'))
                        ->helperText('Dihitung otomatis dari tab Pembayaran. Tidak bisa diedit langsung di sini.'),
                ]),


            ComponentsSection::make('6. Bukti Pembayaran Manual')
                ->icon('heroicon-o-photo')
                ->columns(1)
                ->visible(fn () => config('booking.payment_proof_enabled'))
                ->schema([
                    FileUpload::make('payment_proof_path')
                        ->label('Upload Bukti Transfer')
                        ->image()
                        ->directory('booking-proofs')
                        ->disabled(fn (?Booking $record) => $record?->isLocked()),
                ]),

            ComponentsSection::make('7. Payment Gateway')
                ->icon('heroicon-o-credit-card')
                ->columns(2)
                ->visible(fn () => config('booking.gateway_enabled'))
                ->description('Field ini terisi otomatis lewat webhook gateway. Tidak untuk diedit manual.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('payment_gateway')->label('Gateway')->disabled(),
                    TextInput::make('gateway_transaction_id')->label('Transaction ID')->disabled(),
                    TextInput::make('gateway_status')->label('Status Gateway')->disabled(),
                    TextInput::make('gateway_payment_method')->label('Metode Bayar')->disabled(),
                    TextInput::make('gross_amount')->label('Gross Amount')->prefix('Rp')->numeric()->disabled(),
                ]),

            ComponentsSection::make('8. Kunci Booking (Closing)')
                ->icon('heroicon-o-lock-closed')
                ->description('Setelah dikunci, tanggal/status/harga tidak bisa diubah lagi lewat form ini. Koreksi setelah closing harus lewat entri baru di tab Pembayaran.')
                ->columns(1)
                ->schema([
                    DateTimePicker::make('locked_at')
                        ->label('Kunci Booking')
                        ->native(false),
                ]),
        ]);
    }

    /**
     * Hitung biaya antar jemput berdasarkan jarak (km) dari garasi.
     * 0–10 km   -> Gratis
     * 10–20 km  -> Rp50.000 flat
     * >20 km    -> Rp5.000 / km (dihitung dari total km, bukan hanya kelebihannya)
     */
    protected static function syncDeliveryFee($get, $set): void
    {
        $km = (float) ($get('delivery_distance_km') ?? 0);

        $fee = match (true) {
            $km <= 10 => 0,
            $km <= 20 => 50000,
            default => (int) round($km * 5000),
        };

        $set('delivery_fee_price', $fee);
    }

    /**
     * Hitung ulang driver_surcharge_price berdasarkan paket yang lagi dipilih.
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
            $fee = (int) Setting::get('driver_surcharge', 0);
        }

        $set('driver_surcharge_price', (int) $fee);
    }

    /**
     * Hitung ulang end_date otomatis dari start_date + durasi paket.
     * Paket 'hour' (12 Jam/24 Jam) -> tetap blok 1 hari kalender saja.
     * Paket 'day' -> start_date + (duration_value - 1) hari.
     */
    protected static function syncEndDate($get, $set): void
    {
        $startDate = $get('start_date');
        $packageId = $get('package_id');

        if (! $startDate || ! $packageId) {
            return;
        }

        $package = Package::find($packageId);

        if (! $package) {
            return;
        }

        $start = \Carbon\Carbon::parse($startDate);

        $end = $package->duration_unit === 'hour'
            ? $start->copy()
            : $start->copy()->addDays(max(0, $package->duration_value - 1));

        $set('end_date', $end->toDateString());
    }
}