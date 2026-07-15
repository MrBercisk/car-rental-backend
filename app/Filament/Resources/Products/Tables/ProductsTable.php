<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('images')
                    ->label('Foto')
                    ->getStateUsing(fn (Product $record) => $record->thumbnail)
                    ->square(),

                TextColumn::make('name')
                    ->label('Nama Mobil')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->sortable(),

                TextColumn::make('transmission')
                    ->label('Transmisi')
                    ->badge(),

                TextColumn::make('seat_capacity')
                    ->label('Kursi')
                    ->suffix(' org'),

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

                TernaryFilter::make('is_available')
                    ->label('Tersedia'),

                TernaryFilter::make('is_featured')
                    ->label('Unggulan'),
                TrashedFilter::make(),
            ])
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
