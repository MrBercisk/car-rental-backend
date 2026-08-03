<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\CarPackage;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid as ComponentsGrid;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // hitung units & packages sekali lewat query, dipakai kolom badge di bawah
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['units', 'packages']))
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('Foto')
                    ->disk('public')
                    ->square(),

                TextColumn::make('name')
                    ->label('Nama Mobil')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('brand')
                    ->label('Brand')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->sortable(),

                TextColumn::make('transmission')
                    ->label('Transmisi')
                    ->badge(),

                TextColumn::make('fuel_type')
                    ->label('BBM')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('seat_capacity')
                    ->label('Kursi')
                    ->suffix(' org')
                    ->sortable(),

                // tandai mobil yang belum punya unit fisik sama sekali (bisa ke-booking tapi gak bakal ada mobilnya)
                TextColumn::make('units_count')
                    ->label('Unit')
                    ->badge()
                    ->formatStateUsing(fn (int $state) => $state > 0 ? $state . ' unit' : 'Belum ada unit')
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger')
                    ->sortable(),

                // tandai mobil yang belum punya paket harga sama sekali (gak bisa di-booking sampai ada paket)
                TextColumn::make('packages_count')
                    ->label('Paket')
                    ->badge()
                    ->formatStateUsing(fn (int $state) => $state > 0 ? $state . ' paket' : 'Belum ada paket')
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger')
                    ->sortable(),

                IconColumn::make('is_available')
                    ->label('Tersedia')
                    ->boolean(),

                IconColumn::make('is_featured')
                    ->label('Unggulan')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),

                // Options di-generate dari kolom Product langsung (bukan query terpisah
                // ke tabel lain), jadi ringan -- cukup distinct dari kolom yang di-select.
                SelectFilter::make('transmission')
                    ->label('Transmisi')
                    ->options(fn () => Product::query()
                        ->whereNotNull('transmission')
                        ->distinct()
                        ->orderBy('transmission')
                        ->pluck('transmission', 'transmission')),

                SelectFilter::make('fuel_type')
                    ->label('Bahan Bakar')
                    ->options(fn () => Product::query()
                        ->whereNotNull('fuel_type')
                        ->distinct()
                        ->orderBy('fuel_type')
                        ->pluck('fuel_type', 'fuel_type')),

                SelectFilter::make('brand')
                    ->label('Brand')
                    ->options(fn () => Product::query()
                        ->whereNotNull('brand')
                        ->distinct()
                        ->orderBy('brand')
                        ->pluck('brand', 'brand'))
                    ->searchable(),

                // Rentang kapasitas kursi -- filter angka biasa, langsung ke kolom
                // ter-index (bukan hitung ulang / subquery).
                Filter::make('seat_capacity_range')
                    ->label('Rentang Kursi')
                    ->form([
                        ComponentsGrid::make(2)->schema([
                            TextInput::make('seats_from')
                                ->label('Min')
                                ->numeric()
                                ->placeholder('0'),
                            TextInput::make('seats_until')
                                ->label('Max')
                                ->numeric()
                                ->placeholder('Tanpa batas'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['seats_from'] ?? null,
                                fn (Builder $q, $value) => $q->where('seat_capacity', '>=', $value)
                            )
                            ->when(
                                $data['seats_until'] ?? null,
                                fn (Builder $q, $value) => $q->where('seat_capacity', '<=', $value)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['seats_from'] ?? null) {
                            $indicators[] = 'Kursi min ' . $data['seats_from'];
                        }

                        if ($data['seats_until'] ?? null) {
                            $indicators[] = 'Kursi max ' . $data['seats_until'];
                        }

                        return $indicators;
                    }),

                // Rentang harga paket -- pakai whereHas ke product_packages (tabel kecil,
                // relasi langsung by product_id yang sudah ter-index), bukan join manual.
                Filter::make('package_price_range')
                    ->label('Rentang Harga Paket')
                    ->form([
                        ComponentsGrid::make(2)->schema([
                            TextInput::make('price_from')
                                ->label('Dari')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('0'),
                            TextInput::make('price_until')
                                ->label('Sampai')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('Tanpa batas'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'] ?? null,
                                fn (Builder $q, $value) => $q->whereHas(
                                    'packages',
                                    fn ($p) => $p->where('price', '>=', $value)
                                )
                            )
                            ->when(
                                $data['price_until'] ?? null,
                                fn (Builder $q, $value) => $q->whereHas(
                                    'packages',
                                    fn ($p) => $p->where('price', '<=', $value)
                                )
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['price_from'] ?? null) {
                            $indicators[] = 'Harga dari Rp ' . number_format((float) $data['price_from'], 0, ',', '.');
                        }

                        if ($data['price_until'] ?? null) {
                            $indicators[] = 'Harga s/d Rp ' . number_format((float) $data['price_until'], 0, ',', '.');
                        }

                        return $indicators;
                    }),

                // Punya unit aktif atau tidak -- whereHas ringan, cuma exists check.
                TernaryFilter::make('has_active_unit')
                    ->label('Punya Unit Aktif')
                    ->placeholder('Semua')
                    ->trueLabel('Ada Unit Aktif')
                    ->falseLabel('Tidak Ada Unit Aktif')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('units', fn ($q) => $q->where('condition_status', 'active')),
                        false: fn (Builder $query) => $query->whereDoesntHave('units', fn ($q) => $q->where('condition_status', 'active')),
                        blank: fn (Builder $query) => $query,
                    ),

                // Filter cepat buat cari mobil yang sama sekali belum punya unit fisik
                TernaryFilter::make('has_any_unit')
                    ->label('Punya Unit (Apapun Kondisinya)')
                    ->placeholder('Semua')
                    ->trueLabel('Ada Unit')
                    ->falseLabel('Belum Ada Unit')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('units'),
                        false: fn (Builder $query) => $query->whereDoesntHave('units'),
                        blank: fn (Builder $query) => $query,
                    ),

                // Filter cepat buat cari mobil yang belum punya paket harga
                TernaryFilter::make('has_any_package')
                    ->label('Punya Paket Harga')
                    ->placeholder('Semua')
                    ->trueLabel('Ada Paket')
                    ->falseLabel('Belum Ada Paket')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('packages'),
                        false: fn (Builder $query) => $query->whereDoesntHave('packages'),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('is_available')
                    ->label('Tersedia'),

                TernaryFilter::make('is_featured')
                    ->label('Unggulan'),

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