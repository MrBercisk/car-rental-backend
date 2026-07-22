<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get as UtilitiesGet;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pembayaran';

    protected static ?string $modelLabel = 'Pembayaran';

    /**
     * Hitung sisa tagihan
     * harga paket - (total dp/pelunasan/penyesuaian - refund)
     * Return null kalau booking belum ada harga paket (gak ada acuan buat dibatasi).
     */
    protected function getSisaTagihan(): ?int
    {
        $booking = $this->getOwnerRecord();

        if (! $booking->package_price) {
            return null;
        }

        $sudahDibayar = $booking->payments()
            ->whereIn('type', ['dp', 'pelunasan', 'penyesuaian'])
            ->sum('amount');

        $refund = $booking->payments()
            ->where('type', 'refund')
            ->sum('amount');

        return (int) $booking->package_price - ((int) $sudahDibayar - (int) $refund);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Jenis')
                ->options([
                    'dp' => 'DP',
                    'pelunasan' => 'Pelunasan',
                    'refund' => 'Refund',
                    'penyesuaian' => 'Penyesuaian / Koreksi',
                ])
                ->required()
                ->live(),

            TextInput::make('amount')
                ->label('Nominal')
                ->prefix('Rp')
                ->numeric()
                ->required()
                ->minValue(1)
                ->live(onBlur: true)
                ->helperText(function (UtilitiesGet $get) {
                    $sisa = $this->getSisaTagihan();

                    if ($sisa === null) {
                        return 'Booking ini belum punya harga paket, nominal tidak dibatasi otomatis.';
                    }

                    $type = $get('type');
                    if ($type === 'refund') {
                        return 'Refund tidak dibatasi sisa tagihan.';
                    }

                    return 'Sisa tagihan saat ini: Rp ' . number_format(max(0, $sisa), 0, ',', '.');
                })
                ->rules(function (UtilitiesGet $get) {
                    return [
                        function (string $attribute, $value, \Closure $fail) use ($get) {
                            $type = $get('type');

                            // Refund & booking tanpa harga paket tidak dibatasi
                            if ($type === 'refund') {
                                return;
                            }

                            $sisa = $this->getSisaTagihan();

                            if ($sisa === null) {
                                return;
                            }

                            if ($value > $sisa) {
                                $fail(
                                    'Nominal melebihi sisa tagihan. Sisa tagihan saat ini: Rp '
                                    . number_format(max(0, $sisa), 0, ',', '.')
                                );
                            }
                        },
                    ];
                }),

            DatePicker::make('paid_at')
                ->label('Tanggal Bayar')
                ->required()
                ->default(now()),

            Select::make('method')
                ->label('Metode')
                ->required()
                ->options([
                    'transfer' => 'Transfer Bank',
                    'cash' => 'Tunai',
                    'qris' => 'QRIS',
                    'gateway' => 'Payment Gateway',
                ]),

            FileUpload::make('proof_path')
                ->label('Bukti Bayar')
                ->image()
                ->directory('booking-payment-proofs'),

            Textarea::make('note')
                ->label('Catatan')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'dp' => 'warning',
                        'pelunasan' => 'success',
                        'refund' => 'danger',
                        'penyesuaian' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('method')
                    ->label('Metode')
                    ->placeholder('-'),

                TextColumn::make('recordedBy.name')
                    ->label('Dicatat oleh')
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Diinput pada')
                    ->dateTime('d M Y H:i'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Catat Pembayaran')
                    ->mutateFormDataUsing(function (array $data) {
                       $data['recorded_by'] = auth()->guard()->id();

                        return $data;
                    })
                    ->after(function () {
                     
                        // refresh total terbayar tanpa reload manual
                        $this->dispatch('booking-payment-updated');
                    }),
            ])
            ->recordActions([
            
            ]);
    }
}