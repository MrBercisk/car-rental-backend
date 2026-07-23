<?php

namespace App\Filament\Resources\Packages\Tables;

use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Paket')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                TextColumn::make('duration_value')
                    ->label('Durasi')
                    ->formatStateUsing(fn ($state, $record) => $state . ' ' . ($record->duration_unit === 'hour' ? 'Jam' : 'Hari')),

                TextColumn::make('car_packages_count')
                    ->label('Dipakai di Mobil')
                    ->counts('carPackages')
                    ->badge(),

                TextColumn::make('driver_fee')
                    ->label('Biaya Supir')
                    ->prefix('Rp')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}