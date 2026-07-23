<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\Package;
use App\Models\Product;
use App\Models\Booking;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid as ComponentsGrid;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unit.product.name')
                    ->label('Mobil')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('unit.license_plate')
                    ->label('Plat Nomor')
                    ->searchable(),

                TextColumn::make('customer_name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Selesai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('package_label')
                    ->label('Paket')
                    ->placeholder('-'),

                TextColumn::make('with_driver')
                    ->label('Supir')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Dengan Supir' : 'Lepas Kunci')
                    ->color(fn (bool $state) => $state ? 'info' : 'gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'gray',
                        'dp' => 'warning',
                        'lunas' => 'success',
                        'confirmed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('total_price')
                    ->label('Total Harga')
                    ->money('IDR')
                    ->sortable(false), // accessor, gak ada kolom di DB

                TextColumn::make('amount_paid')
                    ->label('Terbayar')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->color(fn (string $state) => $state === 'maintenance' ? 'warning' : 'gray'),

                TextColumn::make('payment_gateway')
                    ->label('Gateway')
                    ->badge()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('locked_at')
                    ->label('Lock')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('gray'),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options([
                        'pending' => 'Pending',
                        'dp' => 'DP',
                        'lunas' => 'Lunas',
                        'confirmed' => 'Dikonfirmasi',
                        'cancelled' => 'Dibatalkan',
                    ]),

                SelectFilter::make('source')
                    ->label('Sumber')
                    ->options([
                        'rental' => 'Rental',
                        'maintenance' => 'Servis',
                    ]),

                SelectFilter::make('unit_product')
                    ->label('Mobil')
                    ->options(fn () => Product::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        $query->whereHas('unit', fn ($q) => $q->where('product_id', $data['value']));
                    }),

                SelectFilter::make('package_id')
                    ->label('Paket')
                    ->options(fn () => Package::pluck('name', 'id'))
                    ->searchable(),

                TernaryFilter::make('with_driver')
                    ->label('Dengan Supir')
                    ->boolean()
                    ->trueLabel('Dengan Supir')
                    ->falseLabel('Lepas Kunci')
                    ->placeholder('Semua'),

                TernaryFilter::make('locked_at')
                    ->label('Status Lock')
                    ->nullable()
                    ->trueLabel('Terkunci')
                    ->falseLabel('Tidak Terkunci')
                    ->placeholder('Semua')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('locked_at'),
                        false: fn (Builder $query) => $query->whereNull('locked_at'),
                        blank: fn (Builder $query) => $query,
                    ),

                SelectFilter::make('payment_gateway')
                    ->label('Payment Gateway')
                    ->options(fn () => Booking::query()
                        ->whereNotNull('payment_gateway')
                        ->distinct()
                        ->pluck('payment_gateway', 'payment_gateway')),

                // Filter rentang total harga (package_price + surcharge -- dihitung manual
                // di query karena total_price cuma accessor, bukan kolom DB).
                Filter::make('total_price_range')
                    ->label('Rentang Total Harga')
                    ->form([
                        ComponentsGrid::make(2)->schema([
                            TextInput::make('total_from')
                                ->label('Dari')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('0'),
                            TextInput::make('total_until')
                                ->label('Sampai')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Tanpa batas'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['total_from'] ?? null,
                                fn (Builder $q, $value) => $q->whereRaw(
                                    'COALESCE(package_price, 0) + (CASE WHEN with_driver = 1 THEN COALESCE(driver_surcharge_price, 0) ELSE 0 END) >= ?',
                                    [$value]
                                )
                            )
                            ->when(
                                $data['total_until'] ?? null,
                                fn (Builder $q, $value) => $q->whereRaw(
                                    'COALESCE(package_price, 0) + (CASE WHEN with_driver = 1 THEN COALESCE(driver_surcharge_price, 0) ELSE 0 END) <= ?',
                                    [$value]
                                )
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['total_from'] ?? null) {
                            $indicators[] = 'Total dari Rp ' . number_format((float) $data['total_from'], 0, ',', '.');
                        }

                        if ($data['total_until'] ?? null) {
                            $indicators[] = 'Total s/d Rp ' . number_format((float) $data['total_until'], 0, ',', '.');
                        }

                        return $indicators;
                    }),

                // Filter rentang nominal terbayar
                Filter::make('amount_paid_range')
                    ->label('Rentang Terbayar')
                    ->form([
                        ComponentsGrid::make(2)->schema([
                            TextInput::make('paid_from')
                                ->label('Dari')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('0'),
                            TextInput::make('paid_until')
                                ->label('Sampai')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Tanpa batas'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['paid_from'] ?? null,
                                fn (Builder $q, $value) => $q->where('amount_paid', '>=', $value)
                            )
                            ->when(
                                $data['paid_until'] ?? null,
                                fn (Builder $q, $value) => $q->where('amount_paid', '<=', $value)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['paid_from'] ?? null) {
                            $indicators[] = 'Terbayar dari Rp ' . number_format((float) $data['paid_from'], 0, ',', '.');
                        }

                        if ($data['paid_until'] ?? null) {
                            $indicators[] = 'Terbayar s/d Rp ' . number_format((float) $data['paid_until'], 0, ',', '.');
                        }

                        return $indicators;
                    }),

                // Filter rentang tanggal booking (overlap start_date/end_date)
                Filter::make('date_range')
                    ->label('Rentang Tanggal Booking')
                    ->form([
                        ComponentsGrid::make(2)->schema([
                            DatePicker::make('date_from')
                                ->label('Dari')
                                ->native(false),
                            DatePicker::make('date_until')
                                ->label('Sampai')
                                ->native(false),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $q, $value) => $q->where('end_date', '>=', $value)
                            )
                            ->when(
                                $data['date_until'] ?? null,
                                fn (Builder $q, $value) => $q->where('start_date', '<=', $value)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['date_from'] ?? null) {
                            $indicators[] = 'Dari ' . \Illuminate\Support\Carbon::parse($data['date_from'])->format('d M Y');
                        }

                        if ($data['date_until'] ?? null) {
                            $indicators[] = 'Sampai ' . \Illuminate\Support\Carbon::parse($data['date_until'])->format('d M Y');
                        }

                        return $indicators;
                    }),

                TrashedFilter::make(),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}