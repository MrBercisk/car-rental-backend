<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'Unit Fisik (Plat Nomor)';

    protected static ?string $modelLabel = 'Unit';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('license_plate')
                ->label('Plat Nomor')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(20),

            TextInput::make('color')
                ->label('Warna')
                ->maxLength(50)
                ->placeholder('contoh: Putih, Hitam, Silver'),

            Select::make('condition_status')
                ->label('Kondisi')
                ->options([
                    'active' => 'Aktif',
                    'maintenance' => 'Servis / Maintenance',
                    'inactive' => 'Non-aktif',
                ])
                ->default('active')
                ->required(),

            TextInput::make('sort_order')
                ->label('Urutan Prioritas')
                ->numeric()
                ->default(0)
                ->helperText('Angka lebih kecil = diprioritaskan lebih dulu saat sistem auto-assign unit ke booking baru.'),

            Textarea::make('notes')
                ->label('Catatan')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('license_plate')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable(),

                TextColumn::make('license_plate')
                    ->label('Plat Nomor')
                    ->searchable(),

                TextColumn::make('color')
                    ->label('Warna')
                    ->placeholder('-'),

                TextColumn::make('condition_status')
                    ->label('Kondisi')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(40)
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Unit'),
            ])
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